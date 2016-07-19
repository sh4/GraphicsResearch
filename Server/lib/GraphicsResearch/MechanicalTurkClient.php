<?php

namespace GraphicsResearch;

use GraphicsResearch\Rest;

class ExternalQuestion {
    private $externalUrl;
    private $frameHeight;

    public function __construct($externalUrl, $frameHeight) {
        $this->externalUrl = $externalUrl;
        $this->frameHeight = $frameHeight;
    }

    public function toXML() {
        return <<<EOM
<ExternalQuestion xmlns="http://mechanicalturk.amazonaws.com/AWSMechanicalTurkDataSchemas/2006-07-14/ExternalQuestion.xsd">
  <ExternalURL>{$this->externalUrl}</ExternalURL>
  <FrameHeight>{$this->frameHeight}</FrameHeight>
</ExternalQuestion>
EOM;
    }
}

class TaskTimeLimit {
    private $durationInSeconds;
    private $lifetimeInSeconds;

    public function __construct($durationInSeconds, $lifetimeInSeconds) {
        $this->durationInSeconds = $durationInSeconds;
        $this->lifetimeInSeconds = $lifetimeInSeconds;
    }
}

class Reward {
    // 報酬1件あたりの手数料(20%)
    const ChargeRatio = 0.20;
    // HIT1つあたりのコストが10万超えそうなら例外をはいて死ぬ
    const CloudCostHardLimitUSD = 1000;

    private $rewardAmountUSD;
    private $maxAssignments;

    public function __construct($rewardAmountUSD, $maxAssignments) {
        $this->rewardAmountUSD = $rewardAmountUSD;
        $this->maxAssignments = $maxAssignments;

        if ($this->estimateMaxAmount() > self::CloudCostHardLimitUSD) {
            throw new \Exception("Cloud cost hard limit execeed: "
                . $this->estimateMaxAmount() . "USD"
                . " > " 
                . self::CloudCostHardLimitUSD . "USD");
        }
    }

    public function maxAssignments() {
        return $this->maxAssignments;
    }

    public function rewardAmountUSD() {
        return $this->rewardAmountUSD;
    }

    public function estimateMaxAmount() {
        return $this->maxAssignments * ($this->rewardAmountUSD + $this->rewardAmountUSD * self::ChargeRatio);
    }
}

class MechanicalTurkClient {
    private $secretKey;
    private $accessKey;

    private $restClient;

    // 本番 URL
    //const Url     = "https://mechanicalturk.amazonaws.com/";
    // サンドボックス URL
    const Url     = "https://mechanicalturk.sandbox.amazonaws.com/";
    // サービス名(MTurkでは固定)
    const Service = "AWSMechanicalTurkRequester";

    public function __construct() {
        $this->restClient = new Rest\Client();
        $this->restClient->getEvent()->onRequest(function (Rest\Response $response)  {
        });
    }

    public function setSecretKey($secretKey) {
        $this->secretKey = $secretKey;
    }

    public function setAccessKey($accessKey) {
        $this->accessKey = $accessKey;
    }

    public function createHIT() {
        $maxAssignments = 1;
        $rewardAmountPerAssignmentUSD = 0.50;
        $question = new ExternalQuestion("https://init.sakura.ne.jp/test/mturk/mturk-test.php", 800);
        $requestToken = sha1(uniqid("", true));

        $xml = $this->invoke("CreateHIT", [
            "Title" => "Test HIT",
            "Description" => "Test Description",
            "Question" => $question->toXML(),
            "Reward.1.Amount" => $rewardAmountPerAssignmentUSD,
            "Reward.1.CurrencyCode" => "USD",
            "AssignmentDurationInSeconds" => 60, // Worker が HIT に着手可能な時間（秒）
            "LifetimeInSeconds" => 600, // HIT が利用可能な時間（秒）
            "Keywords" => implode(",", ["test1", "test2", "test3"]),
            // 何名の Worker が HIT を Accept したら利用できなくなるか
            "MaxAssignments" => $maxAssignments,
            // HIT が送信されてから自動的に Approve になるまでの時間（秒）
            // 0 は submit した瞬間に直後に自動 Approve 
            //"AutoApprovalDelayInSeconds" => 2592000, // 30 days
            // リクエストの識別子
            // リクエストがタイムアウトしてから再送したとき、子の識別子が重複していたらキャンセルになってくれる
            "UniqueRequestToken" => $requestToken,
        ]);
        $this->ensureValidRequest($xml->HIT->Request->IsValid);
        return $xml;
    }

    public function forceExpireHIT($hitId) {
        $xml = $this->invoke("ForceExpireHIT", [
            "HITId" => $hitId,
        ]);
        $this->ensureValidRequest($xml->ForceExpireHITResult->Request->IsValid);
        return $xml;        
    }

    public function removeHIT($hitId) {
        $xml = $this->invoke("DisableHIT", [
            "HITId" => $hitId,
        ]);
        $this->ensureValidRequest($xml->DisableHITResult->Request->IsValid);
        return $xml;
    }

    public function getAssignmentsForHIT($hitId) {
        $xml = $this->invoke("GetAssignmentsForHIT", [
            "HITId" => $hitId,
            "AssignmentStatus" => implode(",", ["Submitted","Approved","Rejected"]),
            //"SortProperty" => "SubmitTime", // AcceptTime | AssignmentStatus
            //"SortDirection" => "Ascending", // Descending
            //"PageSize" => 10, // between 1 and 100
            //"PageNumber" => 1, // positive integer
        ]);
        $this->ensureValidRequest($xml->GetAssignmentsForHITResult->Request->IsValid);
        return $xml;
    }

    public function getAccountBalance() {
        $xml = $this->invoke("GetAccountBalance");
        $this->ensureValidRequest($xml->GetAccountBalanceResult->Request->IsValid);
        return $xml;
    }

    public function approveAssignment($assignmentId) {
        $xml = $this->invoke("ApproveAssignment", [
            "AssignmentId" => $assignmentId,
            //"RequesterFeedback" => "", // can be up to 1024 characters, including multi-byte characters.
        ]);
        $this->ensureValidRequest($xml->ApproveAssignmentResult->Request->IsValid);
        return $xml;        
    }

    public function rejectAssignment($assignmentId) {
        $xml = $this->invoke("RejectAssignment", [
            "AssignmentId" => $assignmentId,
            //"RequesterFeedback" => "", // can be up to 1024 characters, including multi-byte characters.
        ]);
        $this->ensureValidRequest($xml->RejectAssignmentResult->Request->IsValid);
        return $xml;
    }

    private function invoke($operation, $params = []) {
        $this->ensureKeys();

        $requestParam = array_merge(
            $this->commonParameters($operation), 
            $params);

        $responseBody = $this->restClient->post(self::Url, Rest\Request::form($requestParam));

        // ルート要素の子要素についてアロー演算子による要素の参照が可能
        $xml = new \SimpleXMLElement($responseBody);
        return $xml;
    }

    private function commonParameters($operation) {
        $timestamp = (new \DateTime())->format(\DateTime::W3C);
        return [
            "AWSAccessKeyId" => $this->accessKey,
            "Service" => self::Service,
            // if not specified, the latest version of the API is used.
            //"Version" => "2014-08-15",
            "Operation" => $operation,
            "Timestamp" => $timestamp,
            "Signature" => $this->calculateHMAC(self::Service, $operation, $timestamp),
        ];
    }

    private function calculateHMAC($service, $operation, $timestamp) {
        return base64_encode(mhash(MHASH_SHA1, "$service$operation$timestamp", $this->secretKey));
    }
    
    private function ensureKeys() {
        if (empty($this->accessKey)) {
            throw new \Exception("AWS AccessKey must be assign.");
        }
        if (empty($this->secretKey)) {
            throw new \Exception("AWS SecretKey must be assign.");
        }
    }

    private function ensureValidRequest($valid) {
        if (strtolower($valid) !== "true") {
            throw new \Exception("API Request is invalid");
        }
    }
}