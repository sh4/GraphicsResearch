<?php

namespace GraphicsResearch;

class Question {
    // [ ModelId => [lod1, lod2, ...] ]
    private $modelLodMap;
    // [ "<ModelID>-<Lod>-<Rotation>" => path1, ... ]
    private $modelFileMap;
    // [ ModelId1 => true, ... ]
    private $testAvailableModelIdMap;
    private $invalidModelSet;
    private $lodSetCount;
    
    // [ "<ModelID>-<Lod>-<Rotation>" => path1, ... ]
    // ペイント用の回答データ
    private $maskFileMap;

    private static $defaultQuestion;

    const ReferenceTextureAlbedo = 1024;
    const ReferenceTextureNormal = 1024;

    const ReferenceLod = (self::ReferenceTextureAlbedo  << 16) | self::ReferenceTextureNormal;

    private function __constructor() {
        $this->lodSetCount = null;
    }

    public static function instance() {
        return self::buildFromModelDirectory(JUDGEMENT_IMAGES);
    }

    private static function buildFromModelDirectory($relativeModelDirectory) {
        if (self::$defaultQuestion === null) {
            $question = new Question();
            $question->buildModelSet($relativeModelDirectory);
            $question->removeInvalidModelSet();
            self::$defaultQuestion = $question;
        }
        return self::$defaultQuestion;
    }

    public static function removeModelFiles($relativeModelDirectory, $removeFiles) {
        $removedFiles = 0;
        foreach ($removeFiles as $rawFile) {
            $file = basename($rawFile);
            if (!preg_match('#\.(?:gif|png|jpe?g)$#iu', $file)) {
                continue;
            }
            $file = $relativeModelDirectory."/".$file;
            if (is_writable($file) && is_file($file)) {
                if (unlink($file)) {
                    $removedFiles++;
                }
            }
        }
        return $removedFiles;
    }

    public static function getModelFileWithPattern($relativeModelDirectory, $rawRemoveFilePattern) {
        $removeFilePattern = basename($rawRemoveFilePattern);
        if (empty($removeFilePattern)) {
            return [];
        }
        $removeFiles = [];
        foreach (glob($relativeModelDirectory."/".$removeFilePattern) as $file) {
            if (!preg_match('#\.(?:gif|png|jpe?g)$#iu', $file)) {
                continue;
            }
            $removeFiles[] = basename($file);
        }
        return $removeFiles;
    }

    public static function parseQuizQuestionDataFromCSV($quizQuestionsCsvText) {
        return self::parseQuestionOrderDataFromCSV($quizQuestionsCsvText);
    }

    public static function parseQuestionOrderDataFromCSV($questionsOrderCsvText) {
        $trimmedLines = array_map("trim", explode("\n", trim($questionsOrderCsvText)));
        $questions = array_map("str_getcsv", $trimmedLines);
        $result = [];
        foreach ($questions as $row) {
            if (empty($row)) {
                continue;
            }
            if (count($row) > 1) {
                list($modelFile, $isBetterThanRef) = $row;
            } else {
                list($modelFile) = $row;
                $isBetterThanRef = 0;
            }
            $model = self::parseModelFilename($modelFile);
            // 質問順序データもしくはクイズデータにリファレンスは含めないので、検出したらスキップする
            if ($model->lod == Question::ReferenceLod) {
                continue;
            }
            $result[] = [
                "model_id" => $model->modelId,
                "rotation_id" => $model->rotationId,
                "lod" => $model->lod,
                "is_same" => 0,
                "is_better_than_ref" =>  (int)$isBetterThanRef,
            ];
        }
        return $result;
    }

    public function createRandomOrderQuestions($answeredIds) {
        // マスターデータのキーリスト(ModelID のリスト) だけに含まれる ModelID を得る
        // (残りテストが必要な　ModelID のリストを返す) 
        $remainTestModelIds = array_diff(
            // テスト対象のデータは、LOD0 + それ以外の LOD モデルの最低 2 つ以上が存在していることが必須
            array_keys($this->testAvailableModelIdMap),
            $answeredIds);
        shuffle($remainTestModelIds); // ModelID のリストをシャッフル
        foreach ($remainTestModelIds as $modelId) {
            $rotationSet = $this->modelLodMap[$modelId];
            $rotationId = array_rand($rotationSet);

            $lodMapWithoutLodZero = $rotationSet[$rotationId];

            yield [
                "id" => $modelId,
                "rotation" => $rotationId,
                "lodMap" => $lodMapWithoutLodZero,
            ];
        }
    }

    public function totalModelCount() {
        return count($this->modelLodMap);
    }

    // LOD0 を除いた全モデル共通の LOD 数
    public function lodVariationCount() {
        return $this->lodSetCount;
    }

    public function modelPath($modelId, $rotation, $lod) {
        $key = "$modelId-$lod-$rotation";
        if (isset($this->modelFileMap[$key])) {
            return $this->modelFileMap[$key];
        } else {
            return null;
        }
    }

    public function maskPath($modelId, $rotation, $lod) {
        $key = "$modelId-$lod-$rotation";
        if (isset($this->maskFileMap[$key])) {
            return $this->maskFileMap[$key];
        } else {
            return null;
        }
    }

    public function lodList($modelId, $rotationId) {
        if (!isset(
            $this->modelLodMap[$modelId], 
            $this->modelLodMap[$modelId][$rotationId]))
        {
            return [];
        }
        return array_keys($this->modelLodMap[$modelId][$rotationId]);
    }

    // テストに利用できない無効なデータセットを返す
    public function invalidModelInfos() {
        return $this->invalidModelSet;
    }

    public function invalidModelFiles() {
        $modelFiles = [];
        foreach ($this->invalidModelSet as $infos) {
            foreach ($infos["files"] as $file) {
                $modelFiles[] = $file;
            }
        }
        return $modelFiles;
    }

    public function availableModelCount() {
        return count($this->testAvailableModelIdMap);
    }

    private function buildModelSet($relativeModelDirectory) {
        $modelFiles = scandir($relativeModelDirectory, SCANDIR_SORT_ASCENDING);
        $this->modelLodMap = [];
        $this->modelFileMap = [];
        $this->maskFileMap = [];
        foreach ($modelFiles as $modelFile) {
            $model = self::parseModelFilename($modelFile);
            if (!$model) {
                continue;
            }

            $modelId = $model->modelId;
            $rotationId = $model->rotationId;
            $lod = $model->lod;

            // データタイプの指定がある場合は、通常のモデルデータとしては扱わない
            if ($model->dataType) {
                switch ($model->dataType) {
                case "mask":
                    // マスクデータ
                    $this->maskFileMap["$modelId-$lod-$rotationId"] = "$relativeModelDirectory/$modelFile";
                    break;
                case "texture":
                    // テクスチャ比較用
                    break;
                default:
                    continue;
                }
            }

            if (!isset($this->modelLodMap[$modelId])) {
                $this->modelLodMap[$modelId] = [];
            }
            if (!isset($this->modelLodMap[$modelId][$rotationId])) {
                $this->modelLodMap[$modelId][$rotationId] = [];
            }
            // lod == 0 のテストデータは removeInvalidModelSet で削除される
            // (ここでデータ挿入をスキップしないのは、LOD0 がないデータ、LOD0 しかないデータを洗い出すため)
            $this->modelLodMap[$modelId][$rotationId][$lod] = true;
            $this->modelFileMap["$modelId-$lod-$rotationId"] = "$relativeModelDirectory/$modelFile";
        }
    }

    private function removeInvalidModelSet() {
        $this->testAvailableModelIdMap = [];
        $this->invalidModelSet = [];

        foreach ($this->modelLodMap as $modelId => $rotationSet) {
            foreach ($rotationSet as $rotationId => $lodMap) {
                $invalidRotationSet = false;
                $modelFiles = [];
                foreach ($lodMap as $lod => $_ignore) {
                    $modelFile = $this->modelPath($modelId, $rotationId, $lod);
                    if ($modelFile) {
                        $modelFiles[] = basename($modelFile);
                    }
                }
                if (!isset($lodMap[self::ReferenceLod])) {
                    // LOD0 が存在しない
                    $invalidRotationSet = true;
                    $this->invalidModelSet[] = [
                        "files" => $modelFiles,
                        "message" => "Reference texture not found.",
                    ];
                } else if (count($lodMap) < 2) {
                    // モデルが 2つ以上存在しない (LOD0 しかない)
                    $invalidRotationSet = true;
                    $this->invalidModelSet[] = [
                        "files" => $modelFiles,
                        "message" => "Only exist reference texture.",
                    ];
                }
                // 無効なデータセットならテスト対象から除外
                if ($invalidRotationSet) {
                    unset($this->modelLodMap[$modelId][$rotationId]);
                    continue;
                }

                // LOD0 は比較先データとしては使用しない（常に比較元となるため)ので削除
                unset($this->modelLodMap[$modelId][$rotationId][self::ReferenceLod]);

                // データセットの LOD 数が同一か比較
                $lodCount = count($lodMap) - 1;
                if ($this->lodSetCount === null) {
                    $this->lodSetCount = $lodCount;
                } else if ($this->lodSetCount !== $lodCount) {
                    // LOD の数がモデル間で異なる
                    $this->invalidModelSet[] = [
                        "files" => $modelFiles,
                        "message" => "LOD variation count mismatch: expected = ".($this->lodSetCount+1).", actual = ".($lodCount+1),
                    ];
                    unset($this->modelLodMap[$modelId][$rotationId]);
                    continue;
                }
            }
            if (count($this->modelLodMap[$modelId]) === 0) {
                // ローテーションセットが存在しない
                unset($this->modelLodMap[$modelId]);
                continue;
            }
            $this->testAvailableModelIdMap[$modelId] = true;
        }

        // マスクデータが存在する場合は、それに対応するモデルが存在することをチェック
        foreach ($this->maskFileMap as $key => $path) {
            if (!isset($this->modelFileMap[$key])) {
                $this->invalidModelSet[] = [
                    "files" => [basename($path)],
                    "message" => "Corresponding model not found.",
                ];
                unset($this->maskFileMap[$key]);
            }
        }
    }

    private static function parseModelFilename($textureFile) {
        // <CharacterName>_<ViewName>_<TextureResolutionAlbedo>_<TextureResolutionNormal>.gif|png|jpe?g
        if (!preg_match("#^([^_]+_[^_]+)_albedo(\d+)_normal(\d+).*?\.(?:png|jpe?g|gif)$#iu", basename($textureFile), $matches)) {
            return null;
        }
        list (, $characterAndViewName, $albedoRes, $normalRes) = $matches;
        $model = new \StdClass();
        $model->dataType = "texture";
        $model->modelId = $characterAndViewName;
        $model->rotationId = 0;
        $model->lod = (((int)$albedoRes) << 16) | ((int)$normalRes);
        return $model;
    }
}
