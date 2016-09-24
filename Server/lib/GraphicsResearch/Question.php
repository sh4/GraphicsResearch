<?php

namespace GraphicsResearch;

class Question {
    // ModelId => [lod1, lod2, ...]
    private $modelLodMap;
    // "<ModelID>-<Lod>-<Rotation>" => path1, ...
    private $modelFileMap;
    private $testAvailableModelIds;
    private $invalidModelSet;

    public function __construct($relativeModelDirectory) {
        $this->buildModelSet($relativeModelDirectory);
        $this->removeInvalidModelSet();
    }

    public function createRandomizeOrderQuestions(Unit $session) {
        // マスターデータのキーリスト(ModelID のリスト) だけに含まれる ModelID を得る
        // (残りテストが必要な　ModelID のリストを返す) 
        $answeredIds = [];
        foreach ($session->getJudgementData() as $data) {
            $answeredIds[] = (int)$data["id"];
        }
        // テスト対象のデータは、LOD0 + それ以外のLOD のモデルの最低 2 つ以上が存在していることが必須
        $remainTestModelIds = array_diff(
            $this->testAvailableModelIds,
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
        $answeredIdCount = count($unit->getJudgementData());
        $remainTestModelIds = count($this->modelLodMap) - $answeredIdCount;
        $progress = new \stdClass();
        $progress->remain = $remainTestModelIds;
        $progress->answered = $answeredIdCount;
        $progress->total = $progress->remain + $progress->answered;
        return $progress;
    }

    public function modelPath($modelId, $rotation, $lod) {
        return $this->modelFileMap["$modelId-$lod-$rotation"];
    }

    // テストに利用できない無効なデータセットを返す
    public function invalidModelInfos() {
        return $this->invalidModelSet;
    }

    public function availableModelCount() {
        return count($this->testAvailableModelIds);
    }

    private function buildModelSet($relativeModelDirectory) {
        $modelFiles = scandir($relativeModelDirectory, SCANDIR_SORT_ASCENDING);
        $this->modelIdsOfContainLod0 = [];
        $this->modelLodMap = [];
        $this->modelFileMap = [];
        foreach ($modelFiles as $modelFile) {
            // [SceneID_]<ModelID>_<RotationID>_<LOD>.gif|png|jpe?g
            if (!preg_match('#^(?:[^_]+_)?(\d+)_(\d+)_(\d+)\.(?:png|jpe?g|gif)$#u', basename($modelFile), $matches)) {
                continue;
            }

            list (, $modelId, $rotationId, $lod) = $matches;
            $modelId = (int)$modelId;
            $rotationId = (int)$rotationId;
            $lod = (int)$lod;

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
        $this->testAvailableModelIds = [];
        $this->invalidModelSet = [];

        foreach ($this->modelLodMap as $modelId => $rotationSet) {
            foreach ($rotationSet as $rotationId => $lodMap) {
                $invalidRotationSet = false;
                if (!isset($lodMap[0])) {
                    // LOD0 が存在しない
                    $invalidRotationSet = true;
                    $this->invalidModelSet[] = [
                        "file" => sprintf("%07d", $modelId)."_{$rotationId}_0.jpg",
                        "message" => "LOD Level 0 model not found.",
                    ];
                } else if (count($lodMap) < 2) {
                    // モデルが 2つ以上存在しない (LOD0 しかない)
                    $invalidRotationSet = true;
                    $this->invalidModelSet[] = [
                        "file" => sprintf("%07d", $modelId)."_{$rotationId}_X.jpg",
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
            $this->testAvailableModelIds[] = $modelId;
        }
    }
}
