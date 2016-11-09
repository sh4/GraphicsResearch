<?php

namespace GraphicsResearch;

class JobUnit extends AbstractUnit {
    private $updatedOn;
    private $createdOn;
    private $judgementData;
    private $answeredQuestions;
    private $questions;

    public function __construct($hash) {
        $unitId = $hash["unit_id"];
        if (!Crypto::isValidUniqueId($unitId)) {
            throw new \Exception("Invalid SessionID: $unitId");
        }
        $this->unitId = $unitId;
        $this->updatedOn = $hash["updated_on"] ? new \DateTime($hash["updated_on"]) : null;
        $this->createdOn = $hash["created_on"] ? new \DateTime($hash["created_on"]) : null;
        $this->judgementData = null;
        $this->workerId = "";
        $this->questions = null;
        $this->answeredQuestions = 0;
        if (isset($hash["answered_questions"])) {
            $this->answeredQuestions = (int)$hash["answered_questions"];
        }
        if (isset($hash["job_id"])) {
            $this->jobId = $hash["job_id"];
        }
        if (isset($hash["verification_code"])) {
            $this->verificationCode = $hash["verification_code"];
        }
    }

    public function getLastJudged() {
        return $this->updatedOn;
    }

    public function getRandomizeQuestionOrder() {
        // TODO: Quiz に指定された質問を job_quiz_unit の question_count 件数分差し込む (ランダム)
        return null;
    }

    public function getProgress() {
        return $this->getAnsweredQuestionCount() / $this->getTotalQuestionCount();
    }

    public function getTotalQuestionCount() {
        if ($this->questions === null) {
            if ($jobId = $this->getJobId()) {
                $this->questions = Job::getQuestionsPerUnitFromId($jobId);
            }
        }
        return $this->questions;
    }

    public function getAnsweredQuestionCount() {
        return $this->answeredQuestions;
    }

    public function getJudgementData() {
        if ($this->judgementData === null) {
            $this->judgementData = DB::instance()->each(
                "SELECT * FROM job_unit_judgement WHERE unit_id = ?", $this->getUnitId());
        }
        return $this->judgementData;
    }

    public function writeJudgeData($answers) {
        DB::instance()->transaction(function (DB $db) use ($answers) {
            $now = date('Y-m-d H;i:s');
            $db->insertMulti("job_unit_judgement", $answers);
            $db->execute("INSERT INTO job_unit
                (unit_id, job_id, created_on, answered_questions)
                VALUES (:unit_id, :job_id, :created_on, :answered_questions)
                ON DUPLICATE KEY UPDATE
                     answered_questions = answered_questions + :answered_questions
                    ,updated_on = :updated_on
            ", [
                "unit_id" => $this->getUnitId(),
                "job_id" => $this->getJobId(),
                "created_on" => $now,
                "updated_on" => $now,
                "answered_questions" => count($answers),
            ]);
            $this->answeredQuestions = $db->fetchOne("SELECT answered_questions FROM job_unit WHERE unit_id = ?", $this->getUnitId());
        });
    }

    public static function eachJudgementData($jobId = null) {
        if (is_numeric($jobId)) {
            $judgements = DB::instance()->each("SELECT * FROM job_unit_judgement WHERE job_id = ?", $jobId);
        } else {
            $judgements = DB::instance()->each("SELECT * FROM job_unit_judgement");
        }
        foreach ($judgements as $judgement) {
            if (empty($judgement["worker_id"])) {
                $judgement["worker_id"] = $judgement["unit_id"];
            }
            yield $judgement;
        }
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