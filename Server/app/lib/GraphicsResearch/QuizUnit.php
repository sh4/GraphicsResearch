<?php

namespace GraphicsResearch;

class QuizUnit extends AbstractUnit {
    private $judgementData;
    private $answeredQuestions;
    private $questionCount;

    public function __construct($hash) {
        $unitId = $hash["unit_id"];
        if (!Crypto::isValidUniqueId($unitId)) {
            throw new \Exception("Invalid SessionID: $unitId");
        }
        $this->setUnitId($unitId);
        $this->judgementData = null;
        $this->quizSessionId = "";
        $this->questionCount = 0;
        $this->answeredQuestions = null;
        if (isset($hash["question_count"])) {
            $this->questionCount = $hash["question_count"];
        }
        if (isset($hash["job_id"])) {
            $this->setJobId($hash["job_id"]);
        }
        if (isset($hash["verification_code"])) {
            $this->setVerificationCode($hash["verification_code"]);
        }
        if (isset($hash["quiz_sid"])) {
            $this->quizSessionId = $hash["quiz_sid"];
        }
    }

    public function setQuizSessionId($quizSessionId) {
        $this->quizSessionId = $quizSessionId;
    }

    public function isTestPassed(Job $job) {
        $missedCount = DB::instance()->fetchOne("
            SELECT COUNT(*)
            FROM
                job_quiz_unit_judgement AS judgement
                INNER JOIN job_quiz_unit_golden AS golden ON golden.id = judgement.golden_id 
            WHERE
                golden.job_id = :job_id
                AND judgement.quiz_sid = :quiz_sid
                AND judgement.is_correct = 0
        ", [
            "job_id" => $this->getJobId(),
            "quiz_sid" => $this->quizSessionId,
        ]);
        $requiredAccuracyRate = $job->getQuizAccuracyRate() / 100.0;
        $allowedMissCount = $this->questionCount - (int)($this->questionCount * $requiredAccuracyRate);
        return $missedCount <= $allowedMissCount;
    }

    public function getRandomQuestionOrder(Question $question, $answerContext) {
        // ジョブごとのクイズ回答データから未回答なものをランダムに列挙する 
        $remainQuestions = DB::instance()->fetchAll("
            SELECT golden.model_id, golden.rotation_id, golden.lod
            FROM
                job_quiz_unit_golden AS golden
                LEFT JOIN job_quiz_unit_judgement AS judgement 
                    ON judgement.golden_id = golden.id
                    AND judgement.quiz_sid = :quiz_sid
            WHERE
                golden.job_id = :job_id
                AND judgement.golden_id IS NULL
        ", [
            "job_id" => $this->getJobId(),
            "quiz_sid" => $this->quizSessionId,
        ]);
        $remainQuestionKeys = array_keys($remainQuestions);
        shuffle($remainQuestionKeys);
        foreach ($remainQuestionKeys as $key) {
            $q = $remainQuestions[$key];
            yield [
                "id" => $q["model_id"],
                "rotation" => $q["rotation_id"],
                "lod" => $q["lod"],
            ];
        }
    }

    public function getTotalQuestionCount(Question $question) {
        return (int)($this->questionCount / Job::crowdFlowerRowPerPage);
    }

    public function getAnsweredQuestionCount() {
        if ($this->answeredQuestions === null) {
            $this->answeredQuestions = (int)DB::instance()->fetchOne("
                SELECT COUNT(*) 
                FROM job_quiz_unit_judgement
                WHERE unit_id = ? AND quiz_sid = ?",
                [$this->getUnitId(), $this->quizSessionId]);
        }
        return $this->answeredQuestions;
    }

    public function getJudgementData() {
        if ($this->judgementData === null) {
            $this->judgementData = DB::instance()->each("
                SELECT *
                FROM job_quiz_unit_judgement
                WHERE unit_id = ? AND quiz_sid = ?",
                [$this->getUnitId(), $this->quizSessionId]);
        }
        return $this->judgementData;
    }

    public function writeJudgeData($answers) {
        $rows = [];
        $db = DB::instance();
        foreach ($answers as $answer) {
            $row = $db->fetchRow("
            SELECT * FROM job_quiz_unit_golden 
            WHERE 
                job_id = ? 
                AND model_id = ? 
                AND rotation_id = ? 
                AND lod = ?
            ", [
                $this->getJobId(), 
                $answer["model_id"],
                $answer["rotation_id"],
                $answer["lod"],
            ]);
            $isBetterThanRef = $row["is_better_than_ref"];
            $isCorrect = $isBetterThanRef !== null && $isBetterThanRef == $answer["is_better_than_ref"];
            $rows[] = [
                "golden_id" => $row["id"],
                "unit_id" => $this->getUnitId(),
                "is_correct" => $isCorrect,
                "worker_id" => $this->getWorkerId(),
                "quiz_sid" => $this->quizSessionId,
            ];
        }
        $db->insertMulti("job_quiz_unit_judgement", $rows);
    }

    public static function loadFromId($unitId) {
        $row = DB::instance()->fetchRow("SELECT * FROM job_quiz_unit WHERE unit_id = ?", $unitId);
        if ($row) {
            return new self($row);
        } else {
            return null;
        }
    }
}
