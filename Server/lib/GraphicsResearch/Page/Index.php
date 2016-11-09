<?php

namespace GraphicsResearch\Page;

use GraphicsResearch\Form;
use GraphicsResearch\Question;
use GraphicsResearch\QuestionPage;
use GraphicsResearch\Job;
use GraphicsResearch\JobUnit;
use GraphicsResearch\QuizUnit;
use GraphicsResearch\Constants;

class Index {
    private $question;
    private $questionPage;
    private $unit;
    private $number;
    private $formAction;

    const QuestionUnitId = "questionUnitId";
    const WorkerId = "questionWorkerId";
    const QuizSessionId = "quizSessionId";

    public function __construct() {
        $this->number = (int)Form::get("num", 1);
        $this->formAction = \Router::Path("/");
        if ($this->number !== null && $this->number > 0) {
            $this->formAction .= "?num=$this->number";
        }
        $this->question = Question::buildFromModelDirectory(JUDGEMENT_IMAGES);
        $this->unit = self::createOrUpdateUnit();
        $this->questionPage = QuestionPage::DefaultPage();
    }

    public function getFormAction() {
        return $this->formAction;
    }

    public function getNumber() {
        return $this->number;
    }

    public function getQuestionPage() {
        return $this->questionPage;
    }

    public function getQuestionOrders() {
        $questions = $this->question->createQuestionOrder($this->unit);
        foreach ($questions as $i => $model) {
            yield $i => $model;
        }
    }

    public function getAnswerProgress() {
        $progress = $this->question->answerProgress($this->unit);
        if ($this->unit->getJobId()) {
            $progress->total = $this->unit->getTotalQuestionCount();
        }
        $progress->remain = max(0, min($progress->remain, $progress->total - $progress->answered));
        return $progress;
    }

    public function getSessionId() {
        return $this->unit->getUnitId();
    }

    public function getModelPath($modelId, $rotation, $lod) {
        return $this->question->modelPath($modelId, $rotation, $lod);
    }
    
    public function getAnswers($modelId, $rotation, $lod) {
        $choices = Constants::JudgeList;
        foreach ($choices as $i => $ans) {
            $input = new \stdClass();
            $input->value = implode(",", [$modelId, $rotation, $lod, $ans]);
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

        $quizModeEnabled = Form::request("quizMode", 0) == 1;
        if ($quizModeEnabled) {
            $unit = QuizUnit::loadFromId($unitId);
            $quizSessionId = self::getUniqueIdFromSession(self::QuizSessionId);
            $unit->setQuizSessionId($quizSessionId);
        } else {
            // 本番の質問時にクイズ回答履歴があれば、それをクリア
            if (isset($_SESSION[self::QuizSessionId])) {
                unset($_SESSION[self::QuizSessionId]);
            }
            $unit = JobUnit::loadFromId($unitId);
        }

        $workerId = self::getUniqueIdFromSession(self::WorkerId);
        $unit->setWorkerId($workerId);

        return $unit;
    }

    public static function createOrUpdateUnit() {
        $unit = null;
        if (!Form::get("reset")) {
            $unit = self::loadUnit();
        }
        if (!$unit) {
            $unit = JobUnit::createNewSession();
        }
        // 回答データがポストされていればそれを保存
        if ($answerRawData = Form::post("answer", [])) { // ["ModelID,Rotation,LOD,Judge", ...]
            $answerData = self::ensureAnswerDataFormat($unit, $answerRawData);
            if (!empty($answerData)) {
                $unit->writeJudgeData($answerData);
            }
        }
        $_SESSION[self::QuestionUnitId] = $unit->getUnitId();
        $_SESSION[self::WorkerId] = $unit->getWorkerId();
        return $unit;
    }

    private static function getUniqueIdFromSession($key) {
        if (isset($_SESSION[$key])) {
            return $_SESSION[$key];
        } else {
            return Crypto::CreateUniqueId(16);
        }
    }

    private static function ensureAnswerDataFormat($unit, $answerRawData) {
        $answerData = [];
        foreach ($answerRawData as $answer) {
            list($modelId, $rotation, $lod, $judge) = explode(",", $answer);
            if (is_numeric($modelId) 
                && is_numeric($lod)
                && is_numeric($rotation)
                && in_array($judge, Constants::JudgeList))
            {
                $answerData[] = [
                    "unit_id" => $unit->getUnitId(),
                    "model_id" => $modelId,
                    "rotation_id" => $rotation,
                    "lod" => $lod,
                    "is_same" => $judge == "Yes" ? 0 : 1,
                    "worker_id" => $unit->getWorkerId(),
                ];
            }
        }
        return $answerData;
    }
   
}