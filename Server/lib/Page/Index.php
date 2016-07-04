<?php

namespace Page;

class Index {
    private $modelTestSuite;
    private $modelTestSession;

    public function __construct() {
        session_start();
        $this->modelTestSuite = new \Model\TestSuite(TEST_IMAGE_DIRECTORY);
        $this->modelTestSession = $this->createOrUpdateSession();
    }

    public function getRandomizeOrderTest() {
        $tests = $this->modelTestSuite->createRandomizeOrderTest($this->modelTestSession);
        foreach ($tests as $i => $model) {
            yield $i => $model;
        }
    }

    public function getSessionId() {
        return $this->modelTestSession->getSessionId();
    }

    public function getModelPath($modelId, $lod) {
        return $this->modelTestSuite->modelPath($modelId, $lod);
    }

    private function createOrUpdateSession() {
        if (!\Form::get("reset") && isset($_SESSION["testSessionId"])) {
            $sessionId = $_SESSION["testSessionId"];
            $modelTestSession = new \Model\TestSession(TEST_SESSION_DIRECTORY, $sessionId);
            // 回答データがポストされていればそれを保存
            if ($answerRawData = \Form::post("answer", [])) { // ["ModelID,LOD,Judge", ...]
                $answerData = self::ensureAnswerDataFormat($answerRawData);
                if (!empty($answerData)) {
                    $modelTestSession->writeSessionData($answerData);
                }
            }
        } else {
            $modelTestSession = \Model\TestSession::createNewSession(TEST_SESSION_DIRECTORY);
            $_SESSION["testSessionId"] = $modelTestSession->getSessionId();
        }
        return $modelTestSession;
    }

    private static function ensureAnswerDataFormat($answerRawData) {
        $answerData = [];
        foreach ($answerRawData as $answer) {
            list($modelId, $lod, $judge) = explode(",", $answer);
            if (is_numeric($modelId) 
                && is_numeric($lod)
                && in_array($judge, \Model\TestConstants::JudgeList))
            {
                $answerData[(int)$modelId] = [
                    "id" => $modelId,
                    "lod" => $lod,
                    "judge" => $judge,
                ];
            }
        }
        return $answerData;
    }
   
}