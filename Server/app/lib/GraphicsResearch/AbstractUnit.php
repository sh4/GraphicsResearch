<?php

namespace GraphicsResearch;

abstract class AbstractUnit {
    private $workerId;
    private $jobId;
    private $unitId;
    private $verificationCode;

    const MaxWorkerIdLength = 32;

    public function getWorkerId() {
        return $this->workerId;
    }

    public function getJobId() {
        return $this->jobId;
    }

    public function getVerificationCode() {
        return $this->verificationCode;
    }

    public function getUnitId() {
        return $this->unitId;
    }

    public function setWorkerId($workerId) {
        if (!preg_match("#^[0-9a-z]+$#ui", $workerId)) {
            return;
        }
        if (strlen($workerId) > self::MaxWorkerIdLength) {
            return;
        }
        $this->workerId = $workerId;
    }

    abstract public function getRandomQuestionOrder($answerContext);

    abstract public function getAnswerProgress();

    // return [
    //   [
    //     "unit_id" => UnitID,
    //     "model_id" => ModelID,
    //     "rotation_id" => RotationID,
    //     "lod" => LOD,
    //     "is_same"=> Is same image,
    //     "worker_id" => WorkerID,
    //   ],
    //   ...
    // ]
    abstract public function getJudgementData();

    // $answers = [
    //   [
    //     "model_id" => ModelID,
    //     "lod" => judgeLOD,
    //     "rotation_id" => RotationID,
    //     "is_same" => true, // left/right on same image
    //     "worker_id" => Contributor ID (Woker ID),
    //   ], ...
    // ]
    abstract public function writeJudgeData($answers);

    protected function setJobId($jobId) {
        $this->jobId = $jobId;
    }

    protected function setVerificationCode($code) {
        $this->verificationCode = $code;
    }

    protected function setUnitId($unitId) {
        $this->unitId = $unitId;
    }

    protected function createAnswerProgress($total, $answered) {
        $progress = new \stdClass();
        $progress->remain = max(0, $total - $answered);
        $progress->answered = $answered;
        $progress->total = $total;
        $progress->ratio = 0.0;
        if ($total > 0) {
            $progress->ratio = min(1.0, $progress->answered / $progress->total);
        }
        $progress->completed = ($total - $answered) <= 0;
        return $progress;
    }
}

