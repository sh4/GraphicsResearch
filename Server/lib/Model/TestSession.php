<?php

namespace Model;

class TestSession {
    private $testSessionPath;
    private $sessionId;

    public function __construct($sessionDirectory, $sessionId) {
        if (!self::isValidSessionId($sessionId)) {
            throw new Exception("Invalid SessionID: $sessionId");
        }
        $this->sessionId = $sessionId;
        $this->testSessionPath = "$sessionDirectory/$sessionId.csv"; 
    }

    public function getSessionId() {
        return $this->sessionId;
    }

    public function getAnsweredModelIds() {
        $modelIds = [];
        foreach ($this->readSessionData() as $answer) {
            list ($modelId,) = $answer; 
            $modelIds[] = (int)$modelId;
        }
        return $modelIds;
    }

    // $answers = [ ModelID => ["id" => ModelID, "lod" => LOD, "judge" => Judge], ...]
    public function writeSessionData($answers) {
        foreach ($this->readSessionData() as $answer) {
            list ($modelId) = $answer;
            if (isset($answers[$modelId])) {
                unset($answers[$modelId]);
            }
        }
        $data = "";
        foreach ($answers as $modelId => $answerData) {
            $data .= implode(",", [$modelId, $answerData["lod"], $answerData["judge"]]);
            $data .= "\r\n";
        }
        file_put_contents($this->testSessionPath, $data, FILE_APPEND | LOCK_EX);
    }

    private function readSessionData() {
        if (!file_exists($this->testSessionPath)) {
            return [];
        }
        return array_map("str_getcsv", file($this->testSessionPath));
    }

    public static function createNewSession($sessionDirectory) {
        $sessionId = sha1(sha1(uniqid("", true)).uniqid("", true));
        return new self($sessionDirectory, $sessionId);
    }

    private static function isValidSessionId($sessionId) {
        return preg_match('#^[a-z0-9]+$#ui', $sessionId);
    }
}

