<?php

namespace GraphicsResearch;

use GraphicsResearch\Crowdsourcing\FigureEight;

class Job {
    private $question;

    private $jobId;
    private $title;
    private $instructions;
    private $questionInstructions;
    private $questions;
    private $maxAssignments;
    private $rewardAmountUSD;
    private $createdOn;
    private $quizAccuracyRate;
    private $loopCount;

    private $crowdFlowerJobId;
    private $crowdFlower;
    private $crowdFlowerRowPerPage;

    private $taskType;

    private static $jobRepository = [];

    //// タスク種別

    // 選択(Reference と Comparision モデルの比較)
    const TaskType_Choice   = "choice";
    // ペイント(Reference と Comparision のモデルの差分を塗る) 
    const TaskType_Painting = "painting";
    // 選択＆ペイント(閾値となるモデルが存在すれば、その差異をペイントさせる)
    const TaskType_ThresholdJudgement = "threshold_judgement";
    
    ////

    // FigureEight の Quiz Mode を有効化するために必要な問題数
    // クラウドワーカーはこのクイズ数分だけジョブをアサインされる
    // 同じクイズ行が単一クラウドワーカーに 2 回以上現れることはない
    const crowdFlowerMinimumQuizQuestions = 5;

    private function __construct($job) {
        if (!isset($job["title"],
            $job["instructions"],
            $job["loop_count"]))
        {
            throw new \Exception("Job create parameter required");
        }
        $this->question = Question::instance();

        $this->title = $job["title"];
        $this->instructions = $job["instructions"];
        $this->questions = (int)$job["questions"];
        $this->maxAssignments = (int)$job["max_assignments"];
        $this->rewardAmountUSD = (float)$job["reward_amount_usd"];
        $this->quizAccuracyRate = (float)$job["quiz_accuracy_rate"];
        $this->loopCount = $job["loop_count"];

        if (!isset($job["max_assignments"])) {
            $this->maxAssignments = 0;
        }

        if (isset($job["created_on"])) {
            $this->createdOn = new \DateTime($job["created_on"]);
        } else {
            $this->createdOn = new \DateTime();
        }
        if (isset($job["crowdflower_job_id"])) {
            $this->crowdFlowerJobId = (int)$job["crowdflower_job_id"];
        } else {
            $this->crowdFlowerJobId = 0;
        }
        if (isset($job["job_id"])) {
            $this->jobId = (int)$job["job_id"];
        }
        $this->quizQuestionCount = 0;
        if (isset($job["quiz_question_count"])) {
            $this->quizQuestionCount = (int)$job["quiz_question_count"];
        }

        $this->bonusAmountUSD = 0.0;
        if (isset($job["bonus_amount_usd"])) {
            $this->bonusAmountUSD = (float)$job["bonus_amount_usd"];
        }

        $this->taskType = self::TaskType_Choice;
        if (isset($job["task_type"])) {
            $this->taskType = strtolower($job["task_type"]);
        }

        $this->questionInstructions = "";
        if (isset($job["question_instructions"])) {
            $this->questionInstructions = $job["question_instructions"];
        }

        //// CreateNewJob 呼び出し時に使用する一時的な変数
        $this->quizQuestions = [];
        if (isset($job["quiz_questions"])) {
            $this->quizQuestions = $job["quiz_questions"];
        }
        $this->questionsOrder = [];
        if (isset($job["questions_order"])) {
            $this->questionsOrder = $job["questions_order"];
        }
        $this->isCreateCrowdFlowerJob = false;
        if (isset($job["create_crowdflower_job"])) {
            $this->isCreateCrowdFlowerJob = $job["create_crowdflower_job"] == 1;
        }
        /////

        $this->crowdFlower = new Crowdsourcing\FigureEight();
        $this->crowdFlower->setAPIKey(CROWDFLOWER_API_KEY);
        $this->crowdFlowerRowPerPage = $this->quizQuestionCount == 0 ? 1 : 2;
        // ThresholdJudgement の場合は CF 上でやらない前提として、質問数の分割はナシ
        if ($this->getTaskType() === Job::TaskType_ThresholdJudgement) {
            $this->crowdFlowerRowPerPage = 1;
        }
    }

    public function createdOn() {
        return $this->createdOn;
    }

    public function getJobId() {
        return $this->jobId;
    }

    public function getTitle() {
        return $this->title;
    }

    public function getInstructions() {
        return $this->instructions;
    }

    public function getQuestionInstructions() {
        return $this->questionInstructions;
    }

    public function getQuestions() {
        return $this->questions;
    }

    public function getMaxAssignments() {
        return (int)$this->maxAssignments;
    }

    public function getRewardAmountUSD() {
        return (float)$this->rewardAmountUSD;
    }

    public function getBonusAmountUSD() {
        return (float)$this->bonusAmountUSD;
    }

    public function getTaskType() {
        return $this->taskType;
    }

    public function getCrowdFlowerJobId() {
        return $this->crowdFlowerJobId;
    }

    // パーセンテージの数字を返す (0-100)
    public function getQuizAccuracyRate() {
        return $this->quizAccuracyRate;   
    }

    public function getQuizQuestionCount() {
        return $this->quizQuestionCount;
    }

    public function getQuestionLoopCount() {
        return $this->loopCount;
    }

    public function estimateTotalAmountUSD() {
        return $this->getMaxAssignments() * $this->getRewardAmountUSD();
    }

    public function getUnits() {
        $units = DB::instance()->each("SELECT * FROM job_unit WHERE job_id = ?", $this->getJobId());
        foreach ($units as $unit) {
            yield new JobUnit($unit);
        }
    }

    public function getUnitsByAnswerGroup() {
        $units = DB::instance()->each("
        SELECT
            unit_id,
            job_id,
            verification_code,
            MAX(created_on) AS created_on,
            MAX(updated_on) AS updated_on,
            SUM(job_unit.answered_questions) AS answered_questions,
            answer_group_id
        FROM job_unit
        WHERE job_id = :job_id AND answer_group_id IS NOT NULL
        GROUP BY answer_group_id
        UNION
        SELECT * FROM job_unit
        WHERE job_id = :job_id AND answer_group_id IS NULL
        ",
        [
            "job_id" => $this->getJobId(),
        ]);
        foreach ($units as $unit) {
            yield new JobUnit($unit);
        }
    }

    public function getTotalQuestion() {
        switch ($this->getTaskType()) {
        case self::TaskType_Choice:
        case self::TaskType_ThresholdJudgement:
            // 選択式の場合、CSV として与えられた質問数 * ループ数
            return $this->getQuestions() * $this->getQuestionLoopCount();
        case self::TaskType_Painting:
            // ペイントの場合は LOD0vsLODx の単一比較 (シーンごとにいずれか 1 つの LOD と比較)
            return $this->getQuestions();
        default:
            return 0;
        }
    }

    public function getPerModelQuestionCount() {
        $question = Question::instance();
        switch ($this->getTaskType()) {
        case self::TaskType_Choice:
        case self::TaskType_ThresholdJudgement:
            // 選択式の場合、LOD0vsLODx の全比較
            return $question->lodVariationCount() - 1;
        case self::TaskType_Painting:
            // ペイントの場合は LOD0vsLODx の単一比較 (シーンごとにいずれか 1 つの LOD と比較)
            return 1;
        default:
            return 0;
        }
    }

    public function getProgress() {
        $totalQuestions = $this->getTotalQuestion() * $this->getMaxAssignments();
        $answeredQuestions =  0;
        foreach ($this->getUnits() as $unit) {
            $answeredQuestions += $unit->getAnswerProgress()->answered;
        }
        if ($totalQuestions == 0) {
            return 0;
        } else {
            return $answeredQuestions / $totalQuestions;
        }
    }

    public function launchJob($channel) {
        if ($this->getCrowdFlowerJobId() > 0) {
            return $this->crowdFlower->launchJob($this->getCrowdFlowerJobId(), $this->getMaxAssignments(), $channel);
        } else {
            return false;
        }
    }

    public function getQuizPassRate() {
        $passRate = new \StdClass();
        $row = DB::instance()->fetchRow("
            SELECT
                COUNT(correct_count) AS total_correct_count,
                SUM(CASE WHEN correct_count >= ? THEN 1
                    ELSE 0
                    END
                ) AS pass_count
            FROM (
                SELECT
                    SUM(judgement.is_correct) AS correct_count
                FROM 
                    job_quiz_unit_judgement AS judgement
                    INNER JOIN job_quiz_unit_golden AS golden ON judgement.golden_id = golden.id
                WHERE job_id = ?
                GROUP BY quiz_sid
            ) AS correct_tbl
        ", [
            (int)($this->getQuizQuestionCount() * ($this->getQuizAccuracyRate() / 100.0)),
            $this->getJobId(),
        ]);
        $passRate->total = (int)$row["total_correct_count"];
        $passRate->pass = (int)$row["pass_count"];
        if ($passRate->total > 0) {
            $passRate->ratio = $passRate->pass / $passRate->total;
        } else {
            $passRate->ratio = 0.0;
        }
        return $passRate;
    }

    public function updateSummary($title, $instructions, $questionInstructions) {
        DB::instance()->transaction(function (DB $db) use ($title, $instructions, $questionInstructions) {
            $this->title = $title;
            $this->instructions = $instructions;
            $this->questionInstructions = $questionInstructions;
            $db->update("job", "job_id = ".(int)$this->getJobId(), [
                "title" => $this->getTitle(),
                "instructions" => $this->getInstructions(),
                "question_instructions" => $this->getQuestionInstructions(),
            ]);
            if ($this->getCrowdFlowerJobId() > 0) {
                $this->crowdFlower->updateJobParameters($this->getCrowdFlowerJobId(), [
                    FigureEight::Param_Title => $this->getTitle(),
                    FigureEight::Param_Instructions => $this->getInstructions(),
                ]);
            }
        });
    }

    public static function createNewJob($jobAssoc) {
        $job = new Job($jobAssoc);
        DB::instance()->transaction(function (DB $db) use ($job) {
            $job->createNewJobOnDB($db);
            if ($job->isCreateCrowdFlowerJob) {
                $job->createNewJobOnCrowdFlower($db);
            }
        });
        return $job;
    }

    public static function loadFromId($jobId) {
        $jobRow = DB::instance()->fetchRow("SELECT * FROM job WHERE job_id = ?", (int)$jobId);
        if ($jobRow) {
            return new self($jobRow);
        } else {
            return null;
        }
    }

    public static function loadFromIdWithCache($jobId) {
        if (isset(self::$jobRepository[$jobId])) {
            return self::$jobRepository[$jobId];
        }
        return (self::$jobRepository[$jobId] = self::loadFromId($jobId));
    }

    public static function deleteFromId($jobId) {
        return DB::instance()->transaction(function (DB $db) use ($jobId) {
            return self::deleteJobOnDB($jobId, $db);
        });
    }

    public static function getJobs() {
        $jobRows = DB::instance()->each("SELECT * FROM job");
        foreach ($jobRows as $jobRow) {
            yield new self($jobRow);
        }
    }

    public function addNewJobUnit() {
        $job = $this;
        return DB::instance()->transaction(function (DB $db) use ($job) {
            $jobUnit = $job->createJobUnit();
            $db->insert("job_unit", $jobUnit);
            $job->insertQuizJobUnits($jobUnit["unit_id"], $db);
            return $jobUnit;
        });
    }

    private function createNewJobOnCrowdFlower(DB $db) {
        $params = [
            "url" => \Router::Url(),
        ];
        // FigureEight 上で新規にジョブを作成
        $job = json_decode($this->crowdFlower->createJob([
            "title" => $this->getTitle(),
            "instructions" => $this->getInstructions(),
            "cml" => $this->getCrowdFlowerCML($params),
            "js" => $this->getCrowdFlowerJavaScript($params),
        ]));
        // 回答用データを FigureEight にアップロード
        $this->uploadJobUnitsToCrowdFlower($job->id, $db);
        // クイズ用データが存在すれば、それを行としてアップロード
        $this->uploadQuizUnitsToCrowdFlower($job->id, $db);
        // ジョブパラメータを設定
        $this->crowdFlower->updateJobParameters($job->id, [
            // 報酬の設定 (ドル => セント単位に変換)
            FigureEight::Param_PaymentCents => round($this->getRewardAmountUSD() * 100),
            // 1 Row (Unit) あたりの判定数 (回答可能な Contributor 数)
            FigureEight::Param_JudgementsPerUnit => 1,
            // 1 ページ (報酬を支払う最低単位) あたりのテスト数
            FigureEight::Param_UnitPerAssignment => $this->crowdFlowerRowPerPage,
        ]);
        // 1クラウドワーカーあたりの最大回答数を 1 回に制限する
        // (この値を指定しなくても、最大回答数は Job あたりの QuizUnit 数にキャップされる)
        //$this->crowdFlower->maxJudgmentsPerWorker($job->id, 1);
        // WebHook を設定
        //$this->crowdFlower->enableWebHook($job->id, \Router::Url() . "/webhook");

        // DB に FigureEight のジョブ情報を設定
        $this->crowdFlowerJobId = $job->id;
        $db->update("job", "job_id=".$this->getJobId(), [ 
            "crowdflower_job_id" => $this->crowdFlowerJobId,
        ]);
    }
    
    private function uploadJobUnitsToCrowdFlower($jobId, DB $db) {
        $jobUnitIds = $db->each("SELECT unit_id FROM job_unit WHERE job_id = ?", $this->getJobId());
        $this->crowdFlower->uploadRows($jobId, $jobUnitIds);
    }

    private function uploadQuizUnitsToCrowdFlower($jobId, DB $db) {
        $jobQuizUnits = $db->each("SELECT unit_id, verification_code FROM job_quiz_unit WHERE job_id = ?", $this->getJobId());
        foreach ($jobQuizUnits as $unit) {
            $this->crowdFlower->createNewRow($jobId, [
                "unit_id" => $unit["unit_id"],
                "survey_code_gold" => $unit["verification_code"],
                "survey_code_gold_reason" => "The number of correct answers is missing.",
            ], [
                "state" => "golden",
            ]);
        }
    }

    private function getCrowdFlowerCML($params) {
        return $this->renderPage("view/_crowdflower_cml.php", $params);
    }

    private function getCrowdFlowerJavaScript($params) {
        return $this->renderPage("view/_crowdflower_js.php", $params);
    }

    private function renderPage($file, $params = []) {
        extract($params);
        ob_start();
        include(__DIR__."/../../$file");
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }

    private function createNewJobOnDB(DB $db) {
        $enabledQuestionOrder = $this->getTaskType() == self::TaskType_Painting 
                                && count($this->questionsOrder) > 0;

        // 回答順序の指定が有効な場合、必要回答数の最大値を回答順序数にキャップさせる
        if ($enabledQuestionOrder) {
            $this->questions = min($this->questions, count($this->questionsOrder));
        }

        $this->jobId = $db->insert("job", [
            "title" => $this->getTitle(),
            "instructions" => $this->getInstructions(),
            "questions" => $this->getQuestions(),
            "max_assignments" => $this->getMaxAssignments(),
            "reward_amount_usd" => $this->getRewardAmountUSD(),
            "bonus_amount_usd" => $this->getBonusAmountUSD(),
            "created_on" => $this->createdOn()->format("Y-m-d H:i:s"),
            "crowdflower_job_id" => $this->crowdFlowerJobId,
            "question_order_json" => "[]",
            "quiz_accuracy_rate" => $this->getQuizAccuracyRate(),
            "quiz_question_count" => $this->getQuizQuestionCount(),
            "task_type" => $this->getTaskType(),
            "question_instructions" => $this->getQuestionInstructions(),
            "loop_count" => $this->getQuestionLoopCount(),
        ]);

        $this->insertQuestionsOrder($db);
        $this->insertQuizGoldenData($db);
    }

    private function createJobUnit() {
        $now = date("Y-m-d H:i:s");
        return [
            "unit_id" => Crypto::CreateUniqueId(16),
            "job_id" => $this->getJobId(),
            "verification_code" => Crypto::CreateUniqueNumber(10),
            "created_on" => $now,
            "answered_questions" => 0,
        ];
    }

    private function insertQuizJobUnits($jobUnitId, DB $db) {
        // クイズ用の行を挿入
        $db->insert("job_quiz_unit", [
            "unit_id" => Crypto::CreateUniqueId(16),
            "job_unit_id" => $jobUnitId,
            "job_id" => $this->getJobId(),
            "verification_code" => Crypto::CreateUniqueNumber(10),
            "question_count" => $this->getQuizQuestionCount(),
        ]);
    }

    // $this->quizQuestions = [
    //   [
    //     model_id => 0,
    //     rotation_id => 1,
    //     lod => 2,
    //     is_same => 0, // 0 or 1
    //   ],
    //   ...
    // ]
    private function insertQuizGoldenData(DB $db) {
        $rows = [];
        foreach ($this->quizQuestions as $row) {
            $row["job_id"] = $this->getJobId();
            $rows[] = $row;
        }
        $db->insertMulti("job_quiz_unit_golden", $rows);
    }

    private function insertQuestionsOrder(DB $db) {
        $rows = [];
        foreach ($this->questionsOrder as $i => $order) {
            $modelId = $order["model_id"];
            $rows[] = [
                "job_id" => $this->getJobId(),
                "no" => $i,
                "model_id" => $modelId,
                "rotation_id" => $order["rotation_id"],
                "lod" => $order["lod"],
            ];
        }
        $db->insertMulti("job_unit_question_order", $rows);
    }

    private static function deleteJobOnDB($jobId, DB $db) {
        $params = [ "job_id" => $jobId ];

        $db->delete("job_quiz_unit_judgement",
            "unit_id IN (SELECT unit_id FROM job_quiz_unit WHERE job_id = :job_id)", $params);
        $db->delete("job_quiz_unit", 
            "job_id = :job_id", $params);
        $db->delete("job_quiz_unit_golden",
            "job_id = :job_id", $params);

        $db->delete("job_unit_judgement", 
            "unit_id IN (SELECT unit_id FROM job_unit WHERE job_id = :job_id)", $params);
        $db->delete("job_unit_question_order",
            "job_id = :job_id", $params);
        $db->delete("job_unit",
            "job_id = :job_id", $params);

        $db->delete("job",
            "job_id = :job_id", $params);

        return true;
    }
}