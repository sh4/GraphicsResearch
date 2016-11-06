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

    // 本当は 1 ページ == 1 問にしてすべてこのシステムで回答をハンドリングしたいが、
    // 足切りのための Quiz Mode を有効化するために RowPerPage >= 2 の前提を満たす必要がある
    const crowdFlowerRowPerPage = 1;

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
        foreach ($this->getUnits() as $unit) {
            $progress += $unit->getAnsweredQuestionCount() / $numQuestions;
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
        // 回答用データを CrowdFlower にアップロード
        $rows = $db->each("SELECT unit_id FROM job_unit WHERE job_id = ?", $this->getJobId());
        $job = json_decode($this->crowdFlower->createJob([
            "title" => $this->getTitle(),
            "instructions" => $this->getInstructions(),
            "cml" => $this->getCrowdFlowerCML($params),
            "js" => $this->getCrowdFlowerJavaScript($params),
        ]));
        $this->crowdFlower->uploadRows($job->id, $rows);
        // 成功報酬の設定 (ドル => セント単位に変換)
        $this->crowdFlower->jobTaskPayment($job->id, $this->getRewardAmountUSD() * 100);
        // 1 ページあたりのテスト数
        $this->crowdFlower->rowsPerPage($job->id, self::crowdFlowerRowPerPage);
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

    private static function deleteJobOnDB($jobId, DB $db) {
        $db->delete("job_unit", "job_id = :job_id", [ "job_id" => $jobId ]);
        $db->delete("job", "job_id = :job_id", [ "job_id" => $jobId ]);
        return true;
    }
}