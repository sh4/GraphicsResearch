<?php

namespace GraphicsResearch\Page;

use GraphicsResearch\Form;
use GraphicsResearch\Question;
use GraphicsResearch\JobUnit;

class Download {
    public function __construct() {

    }

    public function echoCSV() {        
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=judgement.csv");
        
        $question = Question::buildFromModelDirectory(JUDGEMENT_IMAGES);
        $jobId = Form::get("jobId", "");
        // ヘッダ行を書き出し
        echo implode(",", [
            "WorkerID",
            "ModelID",
            "RotationID",
            "LOD",
            "IsBetterThanReferenceModel",
            "Filename",
        ]);
        echo "\r\n";
        // 回答データを書き出し
        foreach (JobUnit::eachJudgementData($jobId) as $judgement) {
            // 連想配列をローカル変数に展開
            extract($judgement);
            // ワーカーIDが未設定なら、正確ではないが UnitId を設定
            // （必ずしも単一の人物が回答したとは限らないため）
            if (empty($worker_id)) {
                $worker_id = $unit_id;
            }
            $modelPath = $question->modelPath($model_id, $rotation_id, $lod);
            echo implode(",", [
                $worker_id,
                $model_id,
                $rotation_id,
                $lod,
                $is_better_than_ref,
                basename($modelPath),
            ]);
            echo "\r\n";
        }
    }
}