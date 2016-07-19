<?php

namespace GraphicsResearch;

class Unit {
    private $unitId;
    private $jobId;
    private $verificationCode;
    private $updatedOn;
    private $createdOn;
    private $judgementData;

    public function __construct($hash) {
        $unitId = $hash["unit_id"];
        if (!Crypto::isValidUniqueId($unitId)) {
            throw new \Exception("Invalid SessionID: $unitId");
        }
        $this->unitId = $unitId;
        $this->updatedOn = $hash["updated_on"] ? new \DateTime($hash["updated_on"]) : null;
        $this->createdOn = $hash["created_on"] ? new \DateTime($hash["created_on"]) : null;
        $this->judgementData = [];
        if (isset($hash["judgement_data_json"])) {
            $this->judgementData = json_decode($hash["judgement_data_json"], true);
        }
        if (isset($hash["job_id"])) {
            $this->jobId = $hash["job_id"];
        }
        if (isset($hash["verification_code"])) {
            $this->verificationCode = $hash["verification_code"];
        }
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

    public function getlastJudged() {
        return $this->updatedOn;
    }

    public function getJudgementData() {
        return $this->judgementData;
    }

    // $answers = [ ModelID => ["id" => ModelID, "lod" => judgeLOD, "judge" => judge(yes/no)], ...]
    public function writeJudgeData($answers) {
        foreach ($this->judgementData as $answer) {
            $modelId = $answer["id"];
            if (isset($answers[$modelId])) {
                unset($answers[$modelId]);
            }
        }
        foreach ($answers as $modelId => $answerData) {
            $this->judgementData[] = $answerData;
        }

        $now = date('Y-m-d H;i:s');
        DB::instance()->execute("INSERT INTO job_unit
            (unit_id, job_id, created_on, answered_questions, judgement_data_json) 
            VALUES (:unit_id, :job_id, :created_on, :answered_questions, :judgement_data_json) 
            ON DUPLICATE KEY UPDATE
                answered_questions = :answered_questions
                ,judgement_data_json = :judgement_data_json
                ,updated_on = :updated_on
         ", [
            "unit_id" => $this->getUnitId(),
            "job_id" => $this->getJobId(),
            "created_on" => $now,
            "updated_on" => date("Y-m-d H:i:s"),
            "answered_questions" => count($this->judgementData),
            "judgement_data_json" => json_encode($this->judgementData),
        ]);
    }

    public static function loadFromId($unitId) {
        $row = DB::instance()->fetchRow("SELECT * FROM job_unit WHERE unit_id = ?", $unitId);
        if ($row) {
            return new self($row);
        } else {
            return null;
        }
    }

    public static function createNewSession() {
        $unitId = Crypto::CreateUniqueId(16);
        $now = date("Y-m-d H:i:s");
        return new self([
            "unit_id" => $unitId,
            "created_on" => $now,
            "updated_on" => $now,
        ]);
    }
}

