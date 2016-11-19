<?php

namespace GraphicsResearch\Page;

use GraphicsResearch\Form;
use GraphicsResearch\Job;
use GraphicsResearch\JobUnit;
use GraphicsResearch\Crowdsourcing\CrowdFlower;

class Webhook {
    public function __construct() {
    }

    public function handle() {
        // 回答が完了したフック以外は処理しない
        if (Form::request("signal", "") != "unit_complete") {
            return;
        }
        // リクエストの検証
        $jsonPayload = Form::request("payload", "");
        $signature = Form::request("signature");
        if ($signature != sha1($jsonPayload.CROWDFLOWER_API_KEY)) {
            header("HTTP/1.1 400 Bad Request");
            return;
        }
        // ペイロードからCrowdFlower のワーカーID と GraphicsResearch 側の UnitId を得る
        $payload = json_decode($jsonPayload, true);
        if (!($info = $this->getJudgmentInfo($payload))) {
            return;
        }

        // UnitId から JobUnit と Job を得る
        if (!($unit = JobUnit::loadFromId($info["unit_id"]))) {
            return;
        }
        if (!($job = Job::loadFromId($unit->getJobId()))) {
            return;
        }

        // クラウドワーカーに支払うボーナス金額を計算
        $bonusAmountUSDCents = (int)($job->getBonusAmountUSD() * 100);
        if ($bonusAmountUSDCents == 0) {
            return;
        }
        $paymentBonusUSDCents = $this->getBonusPaintingCount($unit) * $bonusAmountUSDCents;
        if ($paymentBonusUSDCents > 0)
        {
            // ボーナスの支給を実行
            /*
            $cf = new CrowdFlower();
            $cf->setAPIKey(CROWDFLOWER_API_KEY);
            $cf->payContributorBonus(
                $job->getCrowdFlowerJobId(), 
                $info["crowdFlower"]["workerId"],
                $paymentBonusUSDCents);
            */
        }
    }

    // ワーカーへのボーナスを計算
    private function getBonusPaintingCount($unit) {
        $totalPaintingCount = 0;
        $units = JobUnit::loadsFromAnswerGroupId($unit->getAnswerGroupId());
        foreach ($units as $unit) {
            $totalPaintingCount += $unit->getPaintingCount();
        }
        return $totalPaintingCount;
    }

    private function getJudgmentInfo($payload) {
        if (!isset(
            $payload["results"], 
            $payload["results"]["judgments"],
            $payload["results"]["judgments"][0],
            $payload["results"]["judgments"][0]["worker_id"]))
        {
            return null;
        }
        $judgement = $payload["results"]["judgments"][0];

        if (!isset(
            $judgement["unit_data"],
            $judgement["unit_data"]["unit_id"]
        ))
        {
            return null;
        }

        $cfWorkerId = $judgement["worker_id"];
        $unitId = $judgement["unit_data"]["unit_id"];

        return [
            "crowdFlower" => [
                "workerId" => $cfWorkerId,  
            ],
            "unitId" => $unitId,
        ];
    }
}
