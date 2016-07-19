<?php

namespace GraphicsResearch;

class Question {
    // ModelId => [lod1, lod2, ...]
    private $modelLodMap;
    // ModelID => [lod1 => path1, lod2 => path2, ...]
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
        shuffle($remainTestModelIds);
        $no = count($answeredIds);
        foreach ($remainTestModelIds as $i => $modelId) {
            yield ($no+$i) => ["id" => $modelId, "lod" => $this->modelLodMap[$modelId]];
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

    public function modelPath($modelId, $lod) {
        return $this->modelFileMap[(int)$modelId][(int)$lod];
    }

    private function buildModelGroup($relativeModelDirectory) {
        $modelFiles = scandir($relativeModelDirectory, SCANDIR_SORT_ASCENDING);
        $this->modelLodMap = [];
        $this->modelFileMap = [];
        foreach ($modelFiles as $modelFile) {
            // [SceneID_]<ModelID>_<LOD>.gif|png|jpe?g
            if (!preg_match('#^(?:[^_]+_)?(\d+)_(\d+)\.(?:png|jpe?g|gif)$#u', basename($modelFile), $matches)) {
                continue;
            }

            list (, $modelId, $lod) = $matches;
            $modelId = (int)$modelId;
            $lod = (int)$lod;

            if (!isset($this->modelLodMap[$modelId])) {
                $this->modelLodMap[$modelId] = [];
            }
            // LOD が 0 同士の組み合わせを生成するなら if の条件文をコメントアウトする
            if ($lod != 0) {
                $this->modelLodMap[$modelId][] = $lod;
            }
            if (!isset($this->modelFileMap[$modelId])) {
                $this->modelFileMap[$modelId] = [];
            }
            $this->modelFileMap[$modelId][$lod] = "$relativeModelDirectory/$modelFile";
        }
    }
}
