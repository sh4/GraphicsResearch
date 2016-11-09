<?php

namespace GraphicsResearch;

abstract class AbstractUnit {
    protected $jobId;
    protected $unitId;
    protected $verificationCode;
    protected $workerId;

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

    abstract public function getRandomizeQuestionOrder();

    abstract public function getProgress();

    abstract public function getTotalQuestionCount();

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
}

