<?php

namespace GraphicsResearch;

use GraphicsResearch\Rest;

// https://success.crowdflower.com/hc/en-us/articles/202703425-CrowdFlower-API-Requests-Guide
class CrowdFlowerClient {
    private $restClient;
    private $apiKey;

    const Url = "https://api.crowdflower.com/v1";
    const JobsUrl = "https://make.crowdflower.com/jobs";

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
        return $this->restClient->post($url, $json);
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
        return json_decode($response);
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

    public function maxJudgmentsPerWorker($jobId, $maxJudgment) {
        $url = self::JobsUrl."/$jobId/settings/quality_control?key=$this->apiKey";
        list($form, $cookie) = $this->getHTMLPageForm($url);

        $form["builder_job[max_judgments_per_worker]"] = $maxJudgment;

        $request = Rest\Request::form($form);
        $request->setHeader("Cookie", implode("; ", $cookie));
        $this->restClient->post($url, $request);

        return $form;
    }

    public function judgementsPerUnit($jobId, $numJudgment) {
        $url = self::JobsUrl."/$jobId/settings?key=$this->apiKey";
        list($form, $cookie) = $this->getHTMLPageForm($url);

        $form["job[judgments_per_unit]"] = $numJudgment;
        $form["job[units_per_assignment]"] = $numJudgment;

        $request = Rest\Request::form($form);
        $request->setHeader("Cookie", implode("; ", $cookie));
        $this->restClient->post($url, $request);

        return $form;
    }

    private function unitUrl($jobId, $unitId) {
        return self::Url."/jobs/$jobId/units/$unitId.json?key=$this->apiKey";
    }

    private function getHTMLPageForm($url) {
        $cookie = [];
        $request = Rest\Request::createEmpty();
        $request->getEvent()->onResponse(function (Rest\Response $response) use (&$cookie) {
            $cookie = self::ParseSetCookie($response->getHeader());
        });

        $html = $this->restClient->invoke("GET", $url, $request);
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

	private static function ParseSetCookie($headers) {
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

