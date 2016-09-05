<?php

namespace GraphicsResearch;

class Question {
    // ModelId => [lod1, lod2, ...]
    private $modelLodMap;
    // "<ModelID>-<Lod>-<Rotation>" => path1, ...
    private $modelFileMap;

    public function __construct($relativeModelDirectory) {
        $this->buildModelGroup($relativeModelDirectory);
    }

    public function createRandomizeOrderQuestions(Unit $session) {
        // マスターデータのキーリスト(ModelID のリスト) だけに含まれる ModelID を得る
        // (残りテストが必要な　ModelID のリストを返す) 
        $answeredIds = [];
        foreach ($session->getJudgementData() as $data) {
            $answeredIds[] = (int)$data["id"];
        }
        $remainTestModelIds = array_diff(array_keys($this->modelLodMap), $answeredIds);
        shuffle($remainTestModelIds); // ModelID のリストをシャッフル
        $no = count($answeredIds);
        foreach ($remainTestModelIds as $i => $modelId) {
            $lodMap = $this->modelLodMap[$modelId];
            $lod = array_rand($lodMap);
            $rotation = $lodMap[$lod][array_rand($lodMap[$lod])];
            yield ($no+$i) => [
                "id" => $modelId,
                "rotation" => $rotation,
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

    private function buildModelGroup($relativeModelDirectory) {
        $modelFiles = scandir($relativeModelDirectory, SCANDIR_SORT_ASCENDING);
        $this->modelLodMap = [];
        $this->modelFileMap = [];
        foreach ($modelFiles as $modelFile) {
            // [SceneID_]<ModelID>_<RotationID>_<LOD>.gif|png|jpe?g
            if (!preg_match('#^(?:[^_]+_)?(\d+)_(\d+)_(\d+)\.(?:png|jpe?g|gif)$#u', basename($modelFile), $matches)) {
                continue;
            }

            list (, $modelId, $rotationId, $lod) = $matches;
            $modelId = (int)$modelId;
            $lod = (int)$lod;
            $rotationId = (int)$rotationId;

            if (!isset($this->modelLodMap[$modelId])) {
                $this->modelLodMap[$modelId] = [];
            }
            // LOD が 0 同士の組み合わせを生成するなら if の条件文をコメントアウトする
            if ($lod != 0) {
                if (!isset($this->modelLodMap[$modelId][$lod])) {
                    $this->modelLodMap[$modelId][$lod] = [];
                }
                $this->modelLodMap[$modelId][$lod][] = $rotationId;
            }
            $this->modelFileMap["$modelId-$lod-$rotationId"] = "$relativeModelDirectory/$modelFile";
        }
    }
}
