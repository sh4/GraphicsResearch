<?php

namespace GraphicsResearch;

class Job {
    private $jobId;
    private $title;
    private $instructions;
    private $questions;
    private $maxAssignments;
    private $rewardAmountUSD;
    private $createdOn;

    private $crowdFlowerJobId;
    private $crowdFlower;

    // Quiz Mode を有効化する場合は 2 にする
    // 2 にすると RowPerPage == 2 になり、ジョブ実行前にクイズが挟まれるのと、1 問 Quiz 行が入る
    const crowdFlowerRowPerPage = 1;
    // CrowdFlower の Quiz Mode を有効化するために必要な問題数 
    const crowdFlowerMinimumQuizQuestions = 5;

    private function __construct($job) {
        if (!isset($job["title"],
            $job["instructions"],
            $job["questions"],
            $job["max_assignments"],
            $job["reward_amount_usd"]))
        {
            throw new \Exception("Job create parameter required");
        }
        $this->title = $job["title"];
        $this->instructions = $job["instructions"];
        $this->questions = (int)$job["questions"];
        $this->maxAssignments = (int)$job["max_assignments"];
        $this->rewardAmountUSD = (float)$job["reward_amount_usd"];
        $this->quizAccuracyRate = (float)$job["quiz_accuracy_rate"];
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

        // CreateNewJob 呼び出し時に使用する一時的な変数
        $this->quizQuestions = [];
        if (isset($job["quiz_questions"])) {
            $this->quizQuestions = $job["quiz_questions"];
        }
        $this->quizQuestionCount = 0;
        if (isset($job["quiz_question_count"])) {
            $this->quizQuestionCount = (int)$job["quiz_question_count"];
        }

        $this->crowdFlower = new CrowdFlowerClient();
        $this->crowdFlower->setAPIKey(CROWDFLOWER_API_KEY);
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

    public function getQuestions() {
        return $this->questions;
    }

    public function getMaxAssignments() {
        return (int)$this->maxAssignments;
    }

    public function getRewardAmountUSD() {
        return (float)$this->rewardAmountUSD;
    }

    public function getCrowdFlowerJobId() {
        return $this->crowdFlowerJobId;
    }

    // パーセンテージの数字を返す (0-100)
    public function getQuizAccuracyRate() {
        return $this->quizAccuracyRate;   
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

    public function getAnswerProgress() {
        $progress = 0.0;
        foreach ($this->getUnits() as $unit) {
            $progress += $unit->getProgress();
        }
        return $progress / $this->getMaxAssignments();
    }

    public function launchJob($channel) {
        return $this->crowdFlower->launchJob($this->getCrowdFlowerJobId(), $this->getMaxAssignments(), $channel);
    }

    public static function createNewJob($jobAssoc) {
        $job = new Job($jobAssoc);
        DB::instance()->transaction(function (DB $db) use ($job) {
            $job->createNewJobOnDB($db);
            $job->createNewJobOnCrowdFlower($db);
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

    public static function deleteFromId($jobId) {
        return DB::instance()->transaction(function (DB $db) use ($jobId) {
            return self::deleteJobOnDB($jobId, $db);
        });
    }

    public static function getQuestionsPerUnitFromId($jobId) {
        $questions = (int)DB::instance()->fetchOne("SELECT questions FROM job WHERE job_id = ?", $jobId);
        return (int)($questions / self::crowdFlowerRowPerPage);
    }

    public static function getJobs() {
        $jobRows = DB::instance()->each("SELECT * FROM job");
        foreach ($jobRows as $jobRow) {
            yield new self($jobRow);
        }
    }

    private function createNewJobOnCrowdFlower(DB $db) {
        $params = [
            "url" => \Router::Url(),
        ];
        // CrowdFlower 上で新規にジョブを作成
        $job = json_decode($this->crowdFlower->createJob([
            "title" => $this->getTitle(),
            "instructions" => $this->getInstructions(),
            "cml" => $this->getCrowdFlowerCML($params),
            "js" => $this->getCrowdFlowerJavaScript($params),
        ]));
        // 回答用データを CrowdFlower にアップロード
        $this->uploadJobUnitsToCrowdFlower($job->id, $db);
        // クイズ用データをアップロード
        $this->uploadQuizUnitsToCrowdFlower($job->id, $db);
        // 報酬の設定 (ドル => セント単位に変換)
        $this->crowdFlower->jobTaskPayment($job->id, $this->getRewardAmountUSD() * 100);
        // 1 ページ (報酬を支払う最低単位) あたりのテスト数
        $this->crowdFlower->rowsPerPage($job->id, self::crowdFlowerRowPerPage);
        // 1 Row (Unit) あたりの判定数 (回答可能な Contributor 数)
        $this->crowdFlower->judgementsPerUnit($job->id, 1);
        // 1クラウドワーカーあたりの最大回答数を 1 回に制限する
        // TODO: この制限が必要になった段階でコメントを外す
        //$this->crowdFlower->maxJudgmentsPerWorker($job->id, 1);

        // DB に CrowdFlower のジョブ情報を設定
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
        $this->jobId = $db->insert("job", [
            "title" => $this->getTitle(),
            "instructions" => $this->getInstructions(),
            "questions" => $this->getQuestions(),
            "max_assignments" => $this->getMaxAssignments(),
            "reward_amount_usd" => $this->getRewardAmountUSD(),
            "created_on" => $this->createdOn()->format("Y-m-d H:i:s"),
            "crowdflower_job_id" => $this->crowdFlowerJobId,
            "question_order_json" => "[]",
            "quiz_accuracy_rate" => $this->getQuizAccuracyRate(),
        ]);

        $this->insertJobUnits($db);
    
        // クイズ必要回答数も質問数も 1 以上ならクイズ用データを挿入
        if ($this->quizQuestionCount > 0
            && count($this->quizQuestions) > $this->quizQuestionCount)
        {
            $this->insertQuizJobUnits($db);
            $this->insertQuizGoldenData($db);
        }
    }

    private function insertJobUnits(DB $db) {
        $now = date("Y-m-d H:i:s");
        $rows = [];
        // 必要回答数の job_unit を保存
        // 1ページあたりの必要設問数 * 1人当たりの回答数 * 回答必要人数 分の行を生成
        for ($page = 0; $page < self::crowdFlowerRowPerPage; $page++) {
            for ($i = 0, $n = $this->getMaxAssignments(); $i < $n; $i++) {
                $rows[] = [
                    "unit_id" => Crypto::CreateUniqueId(16),
                    "job_id" => $this->getJobId(),
                    "verification_code" => Crypto::CreateUniqueNumber(10),
                    "created_on" => $now,
                    "answered_questions" => 0,
                ];
            }
        }
        $db->insertMulti("job_unit", $rows);
    }

    private function insertQuizJobUnits(DB $db) {
        $quizUnits = [];
        // クイズ用の行を挿入
        for ($i = 0; $i < self::crowdFlowerMinimumQuizQuestions; $i++) {
            $quizUnits[] = [
                "unit_id" => Crypto::CreateUniqueId(16),
                "job_id" => $this->getJobId(),
                "verification_code" => Crypto::CreateUniqueNumber(10),
                "question_count" => $this->quizQuestionCount,
            ];
        }
        $db->insertMulti("job_quiz_unit", $quizUnits);
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
        $db->delete("job_unit",
            "job_id = :job_id", $params);

        $db->delete("job",
            "job_id = :job_id", $params);

        return true;
    }
}