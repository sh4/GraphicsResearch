<?php

namespace GraphicsResearch;

class AnswerContext {
    private $lastAnswer;
    private $answeredLods;
    private $isPaintMode;

    public function __construct($lastAnswer, $answeredLods, $isPaintMode) {
        $this->lastAnswer = $lastAnswer;
        $this->answeredLods = $answeredLods;
        if (!$this->answeredLods) {
            $this->answeredLods = [];
        }
        $this->isPaintMode = $isPaintMode;
    }

    public function getLastAnswer() {
        return $this->lastAnswer;
    }

    public function getAnsweredLods() {
        return $this->answeredLods;
    }

    public function isPaintMode() {
        return $this->isPaintMode;
    }

    public function toArray() {
        return [
            "lastAnswer" => $this->lastAnswer,
            "answeredLods" => $this->answeredLods,
        ];
    }
}