<?php

namespace GraphicsResearch\Page;

use GraphicsResearch\Form;
use GraphicsResearch\Question;
use GraphicsResearch\QuestionPage;
use GraphicsResearch\Job;
use GraphicsResearch\JobUnit;
use GraphicsResearch\QuizUnit;
use GraphicsResearch\Constants;
use GraphicsResearch\Crypto;

class Index {
    private $question;
    private $questionPage;
    private $unit;
    private $number;
    private $formAction;

    const WorkerId = "questionWorkerId";

    public function __construct() {
        $this->number = (int)Form::get("num", 1);
        $this->formAction = \Router::Path("/");
        if ($this->number !== null && $this->number > 0) {
            $this->formAction .= "?num=$this->number";
        }
        $this->question = Question::buildFromModelDirectory(JUDGEMENT_IMAGES);
        $this->unit = $this->createOrUpdateUnit();
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

    public function getUnitId() {
        return $this->unit->getUnitId();
    }

    public function getModelPath($modelId, $rotation, $lod) {
        return $this->question->modelPath($modelId, $rotation, $lod);
    }
    
    public function getAnswers($modelId, $rotation, $lod) {
        $choices = ["Yes", "No"];
        foreach ($choices as $i => $ans) {
            $input = new \stdClass();
            $input->value = implode(",", [$modelId, $rotation, $lod, $ans]);
            $input->id = "answer-form-$modelId-$i";
            $input->answer = $ans;
            yield $input;
        }
    }

    public static function loadUnit() {
        $unitId = Form::request("unitId", "");

        $quizModeEnabled = Form::request("quizMode", 0) == 1;
        if ($quizModeEnabled) {
            $quizSessionId = Form::request("quizSid", "");
            $unit = QuizUnit::loadFromId($unitId);
            $unit->setQuizSessionId($quizSessionId);
        } else {
            $unit = JobUnit::loadFromId($unitId);
        }

        return $unit;
    }

    private function createOrUpdateUnit() {
        $unit = null;
        if (!Form::get("reset")) {
            $unit = self::loadUnit();
        }
        if (!$unit) {
            $unit = JobUnit::createNewSession();
        }

        // WorkerId を取得し、設定
        $workerId = self::getUniqueIdFromSession(self::WorkerId);
        $unit->setWorkerId($workerId);

        // 回答データがポストされていればそれを保存
        if ($answerRawData = Form::post("answer", [])) { // ["ModelID,Rotation,LOD,Judge", ...]
            $answerData = self::ensureAnswerDataFormat($unit, $answerRawData);
            if (!empty($answerData)) {
                $unit->writeJudgeData($answerData);
            }
        }
        // Unit に設定されているワーカー ID をセッションに格納
        // (同一ユーザーかどうかを判定するためのもの。クライアント側でセッションが破棄された場合は不正確)
        $_SESSION[self::WorkerId] = $unit->getWorkerId();
        return $unit;
    }

    private static function getUniqueIdFromSession($key) {
        if (isset($_SESSION[$key]) && !empty($_SESSION[$key])) {
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
                && is_numeric($rotation))
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