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

    private function __constructor() {

    }

    public function createQuestionOrder(Unit $session) {
        $answeredIds = $this->getAnsweredIds($session);
        if ($job = Job::loadFromId($session->getJobId())) {
            $questionOrder = $job->getUserDefinedQuestionOrder();
            if (count($questionOrder) > 0) {
                // ユーザー定義の並び順
                $userDefinedOrder = $this->createUserDefinedOrderQuestions($answeredIds, $questionOrder);
                foreach ($userDefinedOrder as $i => $model) {
                    yield $i => $model;
                }
                return;
            }
        }
        // ランダムな並び順
        $randomizedQuestionOrder = $this->createRandomizeOrderQuestions($answeredIds);
        foreach ($randomizedQuestionOrder as $i => $model) {
            yield $i => $model;
        }
    }

    public static function buildFromModelDirectory($relativeModelDirectory) {
        $question = new Question();
        $question->buildModelSet($relativeModelDirectory);
        $question->removeInvalidModelSet();
        return $question;
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

    public static function parseQuestionOrderFromCSV($questionOrderCsv) {
        $questionOrders = array_map("str_getcsv", 
            array_map(function ($x) { return trim($x); }, explode("\n", $questionOrderCsv)));
        $result = [];
        foreach ($questionOrders as $orderRow) {
            if (empty($orderRow)) {
                continue;
            }
            list($modelFile) = $orderRow;
            $model = self::parseModelFilename($modelFile);
            if (!is_numeric($model->modelId)) {
                continue;
            }
            $result[] = [
                "id" => $model->modelId,
                "rotation" => $model->rotationId,
                "lod" => $model->lod,
            ];
        }
        return $result;
    }

    private function createUserDefinedOrderQuestions($answeredIds, $questionOrder) {
        $answeredIdMap = [];
        foreach ($answeredIds as $id) {
            $answeredIdMap[$id] = true;
        }
        $remainQuestions = array_filter($questionOrder, function ($model) use ($answeredIdMap) {
            $id = (int)$model["id"];
            // 未回答の回答データ && テストが可能なモデルID
            return !isset($answeredIdMap[$id]) 
                && isset($this->testAvailableModelIdMap[$id]);
        });

        $no = count($answeredIds);
        foreach ($remainQuestions as $i => $model) {
            yield ($no+$i) => $model;
        }
    }

    private function createRandomizeOrderQuestions($answeredIds) {
        // マスターデータのキーリスト(ModelID のリスト) だけに含まれる ModelID を得る
        // (残りテストが必要な　ModelID のリストを返す) 
        $remainTestModelIds = array_diff(
            // テスト対象のデータは、LOD0 + それ以外のLOD のモデルの最低 2 つ以上が存在していることが必須
            array_keys($this->testAvailableModelIdMap),
            $answeredIds);
        shuffle($remainTestModelIds); // ModelID のリストをシャッフル
        $no = count($answeredIds);
        foreach ($remainTestModelIds as $i => $modelId) {
            $rotationSet = $this->modelLodMap[$modelId];
            $rotationId = array_rand($rotationSet);

            $lodMapWithoutLodZero = $rotationSet[$rotationId];
            $lod = array_rand($lodMapWithoutLodZero);

            yield ($no+$i) => [
                "id" => $modelId,
                "rotation" => $rotationId,
                "lod" => $lod,
            ];
        }
    }

    public function answerProgress(Unit $unit) {
        $answeredCount = $unit->getAnsweredQuestionCount();
        $remainTestModelIds = count($this->modelLodMap) - $answeredCount;
        $progress = new \stdClass();
        $progress->remain = $remainTestModelIds;
        $progress->answered = $answeredCount;
        $progress->total = $progress->remain + $progress->answered;
        return $progress;
    }

    public function modelPath($modelId, $rotation, $lod) {
        $key = "$modelId-$lod-$rotation";
        if (isset($this->modelFileMap[$key])) {
            return $this->modelFileMap[$key];
        } else {
            return null;
        }
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
        $this->modelIdsOfContainLod0 = [];
        $this->modelLodMap = [];
        $this->modelFileMap = [];
        foreach ($modelFiles as $modelFile) {
            $model = self::parseModelFilename($modelFile);
            if (!$model) {
                continue;
            }

            $modelId = $model->modelId;
            $rotationId = $model->rotationId;
            $lod = $model->lod;

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
                if (!isset($lodMap[0])) {
                    // LOD0 が存在しない
                    $invalidRotationSet = true;
                    $this->invalidModelSet[] = [
                        "files" => $modelFiles,
                        "message" => "LOD Level 0 model not found.",
                    ];
                } else if (count($lodMap) < 2) {
                    // モデルが 2つ以上存在しない (LOD0 しかない)
                    $invalidRotationSet = true;
                    $this->invalidModelSet[] = [
                        "files" => $modelFiles,
                        "message" => "Only LOD Level 0 model exists.",
                    ];
                }
                // 無効なデータセットならテスト対象から除外
                if ($invalidRotationSet) {
                    unset($this->modelLodMap[$modelId][$rotationId]);
                    continue;
                }
                // LOD0 は比較先データとしては使用しない（常に比較元となるため)ので削除
                unset($this->modelLodMap[$modelId][$rotationId][0]);
            }
            if (count($this->modelLodMap[$modelId]) === 0) {
                // ローテーションセットが存在しない
                unset($this->modelLodMap[$modelId]);
                continue;
            }
            $this->testAvailableModelIdMap[$modelId] = true;
        }
    }

    private function getAnsweredIds(Unit $session) {
        // マスターデータのキーリスト(ModelID のリスト) だけに含まれる ModelID を得る
        // (残りテストが必要な　ModelID のリストを返す) 
        $answeredIds = [];
        foreach ($session->getJudgementData() as $data) {
            $answeredIds[] = $data["model_id"];
        }
        return $answeredIds;
    }

    private static function parseModelFilename($modelFile) {
        // <ModelID>_<RotationID>_<LOD>(...).gif|png|jpe?g
        if (!preg_match('#^(\d+)_(\d+)_(\d+).*\.(?:png|jpe?g|gif)$#u', basename($modelFile), $matches)) {
            return null;
        }
        list (, $modelId, $rotationId, $lod) = $matches;
        $model = new \StdClass();
        $model->modelId = (int)$modelId;
        $model->rotationId = (int)$rotationId;
        $model->lod = (int)$lod;
        return $model;
    }
}
