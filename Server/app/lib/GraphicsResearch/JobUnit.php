<?php

namespace GraphicsResearch;

class JobUnit extends AbstractUnit {
    private $updatedOn;
    private $createdOn;
    private $judgementData;
    private $answeredQuestions;
    private $progress;
    private $answerGroupId;

    public function __construct($hash) {
        $unitId = $hash["unit_id"];
        if (!Crypto::isValidUniqueId($unitId)) {
            throw new \Exception("Invalid SessionID: $unitId");
        }
        $this->setUnitId($unitId);
        $this->updatedOn = $hash["updated_on"] ? new \DateTime($hash["updated_on"]) : null;
        $this->createdOn = $hash["created_on"] ? new \DateTime($hash["created_on"]) : null;
        $this->judgementData = null;
        $this->progress = null;

        $this->answeredQuestions = 0;
        if (isset($hash["answered_questions"])) {
            $this->answeredQuestions = (int)$hash["answered_questions"];
        }
        $this->answerGroupId = null;
        if (isset($hash["answer_group_id"])) {
            $this->answerGroupId = $hash["answer_group_id"];
        }

        if (isset($hash["job_id"])) {
            $this->setJobId($hash["job_id"]);
        }
        if (isset($hash["verification_code"])) {
            $this->setVerificationCode($hash["verification_code"]);
        }
    }

    public function getLastJudged() {
        return $this->updatedOn;
    }

    public function getRandomQuestionOrder($answerContext) {
        $question = Question::instance(); 
        $onlyOneLod = false; // ランダムにリストアップした単一 LOD のみ表示する

        if ($answerContext) {
            // ペイントモードが有効なら単一 LOD の列挙を行う
            $onlyOneLod = $answerContext->isPaintMode();

            $lastAnswer = $answerContext->getLastAnswer();
            $answeredLods = $answerContext->getAnsweredLods();
            if ($lastAnswer !== null 
                && count($answeredLods) > 0
                && !$onlyOneLod)
            {
                $modelId = $lastAnswer["model_id"];
                $rotationId = $lastAnswer["rotation_id"];
                $lodLists = $question->lodList($modelId, $rotationId);
                // 残り回答が必要な LOD のリストを得る
                $judgeLods = array_diff($lodLists, $answeredLods);
                shuffle($judgeLods);
                foreach ($judgeLods as $lod) {
                    yield [
                        "id" => $modelId,
                        "rotation" => $rotationId,
                        "lod" => $lod,
                    ];
                }
            }
        }
        // 回答完了済み ModelID のリスト (テスト対象から除外するため)
        $answeredIds = $this->getFinishedAnsweredIds($question);
        // ランダムな並び順
        $questionOrder = $question->createRandomOrderQuestions($answeredIds);
        // あるモデルにおけるランダムなローテーションについて、LOD をランダムな順番で列挙
        foreach ($questionOrder as $order) {
            $lodLists = array_keys($order["lodMap"]);
            shuffle($lodLists);
            foreach ($lodLists as $lod) {
                yield [
                    "id" => $order["id"],
                    "rotation" => $order["rotation"],
                    "lod" => $lod,
                ];
                if ($onlyOneLod) {
                    // 単一 LOD のみのリストアップで次のモデルの列挙を行う
                    break;
                }
            }
        }
    }

    public function getAnswerProgress() {
        if ($this->progress !== null) {
            return $this->progress;
        }
        $answered = $this->answeredQuestions;
        if ($jobId = $this->getJobId()) {
            // ジョブが指定されている場合はモードによって必要回答数が異なる
            $job = Job::loadFromIdWithCache($jobId);
            $total = $job->getTotalQuestionPerUnit();
        } else {
            // ジョブが未指定の場合はデフォルトの回答モード (全回答モード)
            $question = Question::instance();
            $total = $question->totalModelCount() * $question->lodVariationCount();
        }
        return ($this->progress = $this->createAnswerProgress($total, $answered));
    }

    public function getJudgementData() {
        if ($this->judgementData === null) {
            $this->judgementData = DB::instance()->each(
                "SELECT * FROM job_unit_judgement WHERE unit_id = ?", $this->getUnitId());
        }
        return $this->judgementData;
    }

    public function setAnswerGroupId($answerGroupId) {
        $this->answerGroupId = $answerGroupId;
    }

    public function getAnswerGroupId() {
        return $this->answerGroupId;
    }

    public function getPaintingFilePath($judgementId) {
        $judgement = DB::instance()->fetchRow("
            SELECT * FROM job_unit_judgement WHERE id = ? 
        ", $judgementId);
        return $this->getPaintingFilePathFromJudgement($judgement);
    }

    public function getPaintingFilePathFromJudgement($judgement) {
        $question = Question::instance();
        $modelFilename = basename($question->modelPath(
            $judgement["model_id"], 
            $judgement["rotation_id"], 
            $judgement["lod"]));
        return PAINTING_TASK_IMAGES . "/" .
               $this->getJobId() . "/" .
               $this->getUnitId() . "/" . 
               preg_replace('#\.jpe?g$#ui', ".png", $modelFilename);        
    }

    public function writeJudgeData($answers) {
        return DB::instance()->transaction(function (DB $db) use ($answers) {
            $now = date('Y-m-d H;i:s');
            $judgementIds = $db->insertMulti("job_unit_judgement", $answers);
            $db->execute("INSERT INTO job_unit
                (unit_id, job_id, created_on, answered_questions, verification_code, answer_group_id)
                VALUES (:unit_id, :job_id, :created_on, :add_answered_questions, :verification_code, :answer_group_id)
                ON DUPLICATE KEY UPDATE
                     answered_questions = answered_questions + :add_answered_questions
                    ,updated_on = :updated_on
                    ,answer_group_id = :answer_group_id
            ", [
                "unit_id" => $this->getUnitId(),
                "job_id" => $this->getJobId(),
                "created_on" => $now,
                "updated_on" => $now,
                "add_answered_questions" => count($answers),
                "verification_code" => $this->getVerificationCode(),
                "answer_group_id" => $this->getAnswerGroupId(),
            ]);
            $this->answeredQuestions = (int)$db->fetchOne("SELECT answered_questions FROM job_unit WHERE unit_id = ?", $this->getUnitId());
            return $judgementIds;
        });
    }

    public function setIsPaintingCompleted($judgementId) {
        DB::instance()->execute("
            UPDATE job_unit_judgement
            SET is_painting_completed = :completed
            WHERE id = :id
        ",
        [
            "completed" => 1,
            "id" => $judgementId,
        ]);
    }

    public function getPaintingCount() {
        return (int)DB::instance()->fetchOne("
            SELECT COUNT(*)
            FROM job_unit_judgement
            WHERE unit_id = ? AND is_painting_completed = 1
        ",
        [
            $this->getUnitId(),
        ]);
    }

    public static function eachJudgementData($jobId = null) {
        if (is_numeric($jobId)) {
            $judgements = DB::instance()->each("
                SELECT * FROM job_unit_judgement 
                WHERE
                    unit_id IN (SELECT unit_id FROM job_unit WHERE job_id = ?)
            ", $jobId);
        } else {
            $judgements = DB::instance()->each("
                SELECT * FROM job_unit_judgement
                WHERE
                    unit_id IN (
                        SELECT unit_id FROM job_unit WHERE job_id IS NOT NULL
                    )
            ");
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

    public static function loadsFromAnswerGroupId($answerGroupId) {
        $units = DB::instance()->each("SELECT * FROM job_unit WHERE answer_group_id = ?", $answerGroupId);
        foreach ($units as $unit) {
            yield new self($unit);
        }
    }

    public static function createNewSession($params = []) {
        $now = date("Y-m-d H:i:s");
        return new self(array_merge([
            "unit_id" => Crypto::CreateUniqueId(16),
            "created_on" => $now,
            "updated_on" => $now,
        ], $params));
    }

    private function getFinishedAnsweredIds(Question $question) {
        $modelIds = DB::instance()->fetchColumn("
            SELECT model_id FROM job_unit_judgement
            WHERE unit_id = ?
            GROUP BY model_id HAVING COUNT(model_id) >= ?", 
            [
                $this->getUnitId(),
                $question->lodVariationCount(),
            ]);
        return $modelIds;
    }
}