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

    public function getAnsweredIds() {
        $answeredIds = [];
        foreach ($this->getJudgementData() as $data) {
            $answeredIds[] = $data["model_id"];
        }
        return $answeredIds;
    }

    abstract public function getRandomQuestionOrder(Question $question, $answerContext);

    abstract public function getTotalQuestionCount(Question $question);

    abstract public function getAnsweredQuestionCount();

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
}

