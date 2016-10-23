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
    // 回答データの表示順を保持する
    // [ [ id => X, rotation => Y, lod => Z ], ... ]
    private $questionOrder;

    private $crowdFlowerJobId;
    private $crowdFlower;

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

        // ユーザー定義の質問データ順序が与えられているか
        if (isset($job["question_order_json"])) {
            $questionOrder = $job["question_order_json"];
            if (is_string($questionOrder)) {
                $this->questionOrder = json_decode($questionOrder, true);
            } else {
                $this->questionOrder = $questionOrder;
            }
        } else {
            $this->questionOrder = [];
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
        // 回答データの順序が与えられているときは、そちらが必要回答数になる
        $orderCount = count($this->questionOrder);
        if ($orderCount > 0) {
            return $orderCount;
        } else {
            return (int)$this->questions;
        }
    }

    public function getUserDefinedQuestionOrder() {
        return $this->questionOrder;
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

    public function estimateTotalAmountUSD() {
        return $this->getMaxAssignments() * $this->getRewardAmountUSD();
    }

    public function getUnits() {
        $units = DB::instance()->each("SELECT * FROM job_unit WHERE job_id = ?", $this->getJobId());
        foreach ($units as $unit) {
            yield new Unit($unit);
        }
    }

    public function getAnswerProgress() {
        $progress = 0.0;
        $numQuestions = $this->getQuestions();
        foreach ($this->getUnits() as $session) {
            $progress += count($session->getJudgementData()) / $numQuestions;
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
        // 回答用データをアップロード
        $rows = $db->each("SELECT unit_id FROM job_unit WHERE job_id = ?", $this->getJobId());
        $job = json_decode($this->crowdFlower->createJob([
            "title" => $this->getTitle(),
            "instructions" => $this->getInstructions(),
            "cml" => $this->getCrowdFlowerCML($params),
            "js" => $this->getCrowdFlowerJavaScript($params),
        ]));
        $this->crowdFlower->uploadRows($job->id, $rows);
        // 報酬に必要な回答数は1回
        $this->crowdFlower->jobTaskPayment($job->id, $this->getRewardAmountUSD() * 100); // dollar to cents
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
            "question_order_json" => json_encode($this->questionOrder),
        ]);
        $now = date("Y-m-d H:i:s");
        $rows = [];
        // 最大回答データ受付数分の JobUnit を生成
        // 回答データは合計で questions (1 assignment あたりの必要回答データ数. お給料をもらうのに必要な回答数) * max_assigments 件集まる
        for ($i = 0, $n = $this->getMaxAssignments(); $i < $n; $i++) {
            $rows[] = [
                "unit_id" => Crypto::CreateUniqueId(12),
                "job_id" => $this->getJobId(),
                "verification_code" => Crypto::CreateUniqueNumber(16),
                "created_on" => $now,
                "answered_questions" => 0,
                "judgement_data_json" => "[]",
            ];
        }
        $db->insertMulti("job_unit", $rows);
    }

    private static function deleteJobOnDB($jobId, DB $db) {
        $db->delete("job_unit", "job_id = :job_id", [ "job_id" => $jobId ]);
        $db->delete("job", "job_id = :job_id", [ "job_id" => $jobId ]);
        return true;
    }
}