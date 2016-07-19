<?php

namespace GraphicsResearch\Page;

use GraphicsResearch\Form;
use GraphicsResearch\Question;
use GraphicsResearch\Job;
use GraphicsResearch\Unit;
use GraphicsResearch\Constants;

class Index {
    private $question;
    private $unit;
    private $number;
    private $formAction;
    private $job;

    const QuestionUnitId = "questionUnitId";

    public function __construct() {
        $this->number = (int)Form::get("num", 1);
        $this->formAction = \Router::Path("/");
        if ($this->number !== null && $this->number > 0) {
            $this->formAction .= "?num=$this->number";
        }
        $this->question = new Question(JUDGEMENT_IMAGES);
        $this->unit = self::createOrUpdateUnit();
    }

    public function getFormAction() {
        return $this->formAction;
    }

    public function getNumber() {
        return $this->number;
    }

    public function getRandomizeOrderQuestions() {
        $questions = $this->question->createRandomizeOrderQuestions($this->unit);
        foreach ($questions as $i => $model) {
            yield $i => $model;
        }
    }

    public function getAnswerProgress() {
        $progress = $this->question->answerProgress($this->unit);
        if (!$this->job && $this->unit->getJobId()) {
            $this->job = Job::loadFromId($this->unit->getJobId());
        }
        if ($this->job) {
            $progress->total = $this->job->getQuestions();
        }
        $progress->remain = max(0, min($progress->remain, $progress->total - $progress->answered));
        return $progress;
    }

    public function getSessionId() {
        return $this->unit->getUnitId();
    }

    public function getModelPath($modelId, $lod) {
        return $this->question->modelPath($modelId, $lod);
    }
    
    public function getAnswers($modelId, $lod) {
        $choices = Constants::JudgeList;
        foreach ($choices as $i => $ans) {
            $input = new \stdClass();
            $input->value = implode(",", [$modelId, $lod, $ans]);
            $input->id = "answer-form-$modelId-$i";
            $input->answer = $ans;
            yield $input;
        }
    }

    public static function loadUnit() {
        $unitId = Form::get("unitId", "");
        if (empty($unitId) && isset($_SESSION[self::QuestionUnitId])) {
            $unitId = $_SESSION[self::QuestionUnitId];
        }
        return Unit::loadFromId($unitId);
    }

    public static function createOrUpdateUnit() {
        $unit = null;
        if (!Form::get("reset")) {
            $unit = self::loadUnit();
        }
        if (!$unit) {
            $unit = Unit::createNewSession();
        }
        // 回答データがポストされていればそれを保存
        if ($answerRawData = Form::post("answer", [])) { // ["ModelID,LOD,Judge", ...]
            $answerData = self::ensureAnswerDataFormat($answerRawData);
            if (!empty($answerData)) {
                $unit->writeJudgeData($answerData);
            }
        }
        $_SESSION[self::QuestionUnitId] = $unit->getUnitId();
        return $unit;
    }

    private static function ensureAnswerDataFormat($answerRawData) {
        $answerData = [];
        foreach ($answerRawData as $answer) {
            list($modelId, $lod, $judge) = explode(",", $answer);
            if (is_numeric($modelId) 
                && is_numeric($lod)
                && in_array($judge, Constants::JudgeList))
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