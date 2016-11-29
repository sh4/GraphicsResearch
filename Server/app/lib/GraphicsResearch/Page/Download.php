<?php

namespace GraphicsResearch\Page;

use GraphicsResearch\Form;
use GraphicsResearch\Question;
use GraphicsResearch\Job;
use GraphicsResearch\JobUnit;

class Download {
    public function __construct() {
    }

    public function echoData() {
        header("Content-Type: application/octet-stream");
        
        $jobId = Form::get("jobId", "");
        if (!$jobId) {
            Router::redirect("admin");
        }

        $job = Job::loadFromId($jobId);
        switch ($job->getTaskType()) {
        case Job::TaskType_Choice:
            header("Content-Disposition: attachment; filename=judgement.csv");
            $this->echoJobJudgementCSV($job);
            break;
        case Job::TaskType_Painting:
            header("Content-Disposition: attachment; filename=judgement.zip");
            $this->echoJobPaintingZip($job);
            break;
        default:
            header("HTTP/1.1 404 NotFound");
            break;
        }
    }

    private function echoJobPaintingZip(Job $job) {
        $paintingDir = dirname(__FILE__)."/../../../../".
            PAINTING_TASK_IMAGES.
            "/".$job->getJobId();
        $lastCwd = getcwd();
        chdir($paintingDir);
        passthru("zip -q -0 -r - .");
        chdir($lastCwd);
    }

    private function echoJobJudgementCSV(Job $job) {
        $question = Question::instance();

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
        foreach (JobUnit::eachJudgementData($job->getJobId()) as $judgement) {
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