<?php

namespace GraphicsResearch\Crowdsourcing;

use GraphicsResearch\Rest;
use GraphicsResearch\DB;

// CrowdFlower リリースノート:
// https://make.crowdflower.com/product-release-notes
//
// API リファレンス:
// https://success.crowdflower.com/hc/en-us/articles/202703425-CrowdFlower-API-Requests-Guide
// * Undocumented だが、job オブジェクトの要素は大体 PUT リクエストで書き換えが可能
class CrowdFlower {
    private $restClient;
    private $apiKey;

    const Url = "https://api.crowdflower.com/v1";
    const JobsUrl = "https://make.crowdflower.com/jobs";

    const Param_UnitPerAssignment  = "units_per_assignment";
    const Param_JudgementsPerUnit  = "judgments_per_unit";
    const Param_PaymentCents       = "payment_cents";

    public function __construct() {
        $this->restClient = new Rest\Client();
        $this->restClient->getEvent()->onResponse(function (Rest\Response $response)  {
            $body = $response->getBody();
            foreach ($response->getHeader() as $header) {
                list ($name, $value) = $header;
                if ($name === "Content-Type" && strpos($value, "text/html") !== false) {
                    $body = "(HTML Trimmed)";
                    break;
                }
            }
            DB::instance()->insert("http_audit_log", [
                "created_on" => date("Y-m-d H:i:s"),
                "handshake" => $response->getHandshake(),
                "request_header" => var_export($response->getRequest()->getHeaders(), true),
                "request_body" => $response->getRequest()->getContent(),
                "response_header" => var_export($response->getHeader(), true),
                "response_body" => $body,
            ]);
        });
    }

    public function setAPIKey($apiKey) {
        $this->apiKey = $apiKey;
    }

    // $channels = ["on_demand", "cf_internal"]
    public function launchJob($jobId, $debitUnitCounts, $channels = ["cf_internal"]) {
        $params = [
            // 何ユニット分のデータを収集するか（報酬を支払うか）
            "debit[units_count]" => $debitUnitCounts,
        ];
        if (empty($channels)) {
            throw new \Exception("channels cannot be empty");
        }
        foreach ($channels as $i => $channel) {
            $params["channels[$i]"] = $channel;
        }
        $url = self::Url."/jobs/$jobId/orders.json?key=$this->apiKey";
        $form = Rest\Request::form($params);
        return $this->restClient->post($url, $form);
    }

    public function getJob($jobId) {
        $url = self::Url."/jobs/$jobId.json";
        return $this->restClient->get($url);
    }

    public function pauseJob($jobId) {
        $url = self::Url."/jobs/$jobId/pause.json?key=$this->apiKey";
        return $this->restClient->get($url);
    }

    public function resumeJob($jobId) {
        $url = self::Url."/jobs/$jobId/resume.json?key=$this->apiKey";
        return $this->restClient->get($url);
    }

    // job[title] = title
    // job[instructions] = instructions html
    // job[cml] = cml content
    public function createJob($params) {
        $encodedParams = [];
        foreach ($params as $key => $value) {
            $encodedParams["job[$key]"] = $value;
        }
        $url = self::Url."/jobs.json?key=$this->apiKey";
        $form = Rest\Request::form($encodedParams);
        return $this->restClient->post($url, $form);
    }

    public function uploadRows($jobId, $rows) {
        $jsonRows = [];
        foreach ($rows as $row) {
            $jsonRows[] = json_encode($row);
        }
        $url = self::Url."/jobs/$jobId/upload.json?key=$this->apiKey";
        $json = new Rest\Request(implode("\n", $jsonRows), "application/json");
        $response = $this->restClient->post($url, $json);
        return json_decode($response, true);
    }

    public function createNewRow($jobId, $params, $unitParams = []) {
        $encodedParams = [];
        foreach ($params as $column => $value) {
            $encodedParams["unit[data][$column]"] = $value;
        }
        foreach ($unitParams as $key => $value) {
            $encodedParams["unit[$key]"] = $value;
        }
        $url = self::Url."/jobs/$jobId/units.json?key=$this->apiKey";
        $response = $this->restClient->post($url, Rest\Request::form($encodedParams));
        return json_decode($response, true);
    }

    // https://success.crowdflower.com/hc/en-us/articles/202703305-Glossary-of-Terms
    //
    // Each data row has a state that describes its status. The states available to a row are:
    // * new         – A row that has not yet been ordered and will not collect judgments.
    // * judgeable   – A row that has been ordered and can collect judgments.
    // * judging     -  
    // * judged      - 
    // * ordering    - 
    // * finalized   – A row that has received enough trusted judgments to be considered complete and will no longer collect judgments.
    // * canceled    - 
    // * golden      – A Test Question.
    // * hidden      – A disabled Test Question.
    // * hidden_gold - 
    public function changeRowState($jobId, $unitId, $state) {
        $url = $this->unitUrl($jobId, $unitId);
        $form = Rest\Request::form([ "unit[state]" => $state ]);
        return $this->restClient->put($url, $form);
    }

    public function rowState($jobId, $unitId) {
        $url = $this->unitUrl($jobId, $unitId);
        return $this->restClient->get($url);
    }

    public function jobTaskPayment($jobId, $paymentUSDCentsPerTask) {
        $url = self::Url."/jobs/$jobId.json?key=$this->apiKey";
        return $this->restClient->put($url, Rest\Request::form([
            "job[payment_cents]" => round($paymentUSDCentsPerTask),
        ]));
    }

    public function timePerAssignment($jobId, $timeToLiveToSeconds) {
        $url = self::Url."/jobs/$jobId.json?key=$this->apiKey";
        return $this->restClient->put($url, Rest\Request::form([
            "job[options][req_ttl_in_seconds]" => $timeToLiveToSeconds,
        ]));
    }

    // 1 Unit (Row) あたりに割り当て可能な  Contributor 数 (判定数)
    public function judgementsPerUnit($jobId, $judgements) {
        $url = self::Url."/jobs/$jobId.json?key=$this->apiKey";
        return $this->restClient->put($url, Rest\Request::form([
            "job[judgments_per_unit]" => $judgements,
        ]));
    }

    public function maxJudgmentsPerWorker($jobId, $maxJudgment) {
        $url = self::JobsUrl."/$jobId/settings/quality_control?key=$this->apiKey";
        list($form, $cookie) = $this->getHTMLPageForm($url);

        $form["builder_job[max_judgments_per_worker]"] = $maxJudgment;

        $request = Rest\Request::form($form);
        $request->setHeader("Cookie", implode("; ", $cookie));
        $this->restClient->post($url, $request);

        return $form;
    }

    public function rowsPerPage($jobId, $numJudgment) {
        $url = self::Url."/jobs/$jobId.json?key=$this->apiKey";
        $form = Rest\Request::form([
            "job[units_per_assignment]" => $numJudgment,
        ]);
        return $this->restClient->put($url, $form);
    }

    // Webhook による通知を有効化
    // https://success.crowdflower.com/hc/en-us/articles/201856249-CrowdFlower-Webhook-Basics
    public function enableWebhook($jobId, $url) {
        $requestUrl = self::Url."/jobs/$jobId.json?key=$this->apiKey";
        return $this->restClient->put($requestUrl, Rest\Request::form([
            "job[webhook_uri]" => $url,
            "job[send_judgments_webhook]" => "true",
        ]));
    }

    // 指定したジョブのタスクを行った特定ワーカーに対してボーナスを支払う
    public function payContributorBonus($jobId, $workerId, $amountInUSDCents) {
        $params = [
            // ワーカーに支払うボーナス (USD Cents)
            "amount" => $amountInUSDCents,
        ];
        $bonusUrl = self::Url."/jobs/$jobId/workers/$workerId/bonus.json?key=$this->apiKey";
        $form = Rest\Request::form($params);
        return $this->restClient->post($bonusUrl, $form);
    }

    public function updateJobParameters($jobId, $params) {
        $url = self::Url."/jobs/$jobId.json?key=$this->apiKey";
        $jobParams = [];
        foreach ($params as $key => $value) {
            $jobParams["job[".$key."]"] = $value;
        }
        $form = Rest\Request::form($jobParams);
        return $this->restClient->put($url, $form);
    }

    private function unitUrl($jobId, $unitId) {
        return self::Url."/jobs/$jobId/units/$unitId.json?key=$this->apiKey";
    }

    private function getHTML($url) {
        $cookie = [];
        $request = Rest\Request::createEmpty();
        $request->getEvent()->onResponse(function (Rest\Response $response) use (&$cookie) {
            $cookie = self::parseSetCookie($response->getHeader());
        });
        $html = $this->restClient->invoke("GET", $url, $request);
        return [$html, $cookie];
    }

    private function getHTMLPageForm($url) {
        list($html, $cookie) = $this->getHTML($url); 
        $form = self::getFormInputTags($html);
        if (empty($form)) {
            return null;
        }
        return [$form, $cookie];
    }

    private static function getFormInputTags($html) {
        if (!preg_match_all('#<input[^>]*?>#iu', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $form = [];
        foreach ($matches as $subMatches) {
            list($input) = $subMatches;
            if (!preg_match('#name="([^"]*?)"#iu', $input, $internMatches)) {
                continue;
            }
            list (, $name) = $internMatches;
            if (!preg_match('#value="([^"]*?)"#iu', $input, $internMatches)) {
                continue;
            }
            list (, $value) = $internMatches;
            $form[$name] = html_entity_decode($value);
        }
        return $form;
    }

	private static function parseSetCookie($headers) {
        $cookie = [];
        foreach ($headers as $header) {
            list ($name, $setCookie) = $header;
            if ($name !== "Set-Cookie") {
                continue;
            }
            foreach (explode(";", $setCookie) as $item) {
                $itemPair = explode("=", trim($item), 2);
                if (count($itemPair) !== 2) {
                    continue;
                }
                list ($name, $value) = $itemPair;
                if ($name === "domain" || $name === "path") {
                    continue;
                }
                $cookie[] = "$name=$value";
            }
        }
        return $cookie;
    } 
}

