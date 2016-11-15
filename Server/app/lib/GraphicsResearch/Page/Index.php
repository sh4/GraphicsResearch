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
    private $lastAnswers;
    private $modelFormId;

    const WorkerId = "questionWorkerId";

    public function __construct() {
        $this->modelFormId = 0;
        $this->lastAnswers = [];
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
        $answerContext = $this->getAnswerContext();
        $questions = $this->unit->getRandomQuestionOrder($this->question, $answerContext);
        // $model = [
        //   "id" => ModelID,
        //   "rotation" => RotationId,
        //   "lod" => LOD,
        // ]
        foreach ($questions as $model) {
            yield $this->yieldModel($model);
        }
    }

    public function getAnswerProgress() {
        return $this->question->answerProgress($this->unit);
    }

    public function getUnitId() {
        return $this->unit->getUnitId();
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

    public function renderQuestionJson() {
        $progress = $this->getAnswerProgress();
        $isFetchLods = (int)Form::get("fetchLods", 0) == 1;
        $questions = [];
        if ($isFetchLods) {
            $lastModelId = null;
            $skipModelId = null;
            if ($answerContext = $this->getAnswerContext()) {
                $skipModelId = (int)$answerContext["lastAnswer"]["model_id"];
            }
            foreach ($this->getQuestionOrders() as $origModels) {
                list ($model) = $origModels;
                if ($model["id"] == $skipModelId) {
                    continue;
                } 
                if ($model["id"] !== $lastModelId) {
                    $isInitialModelId = $lastModelId === null;
                    $lastModelId = $model["id"];
                    if (!$isInitialModelId) {
                        break;
                    }
                }
                $modelOrder = array_keys($origModels);
                shuffle($modelOrder);
                $models = [];
                foreach ($modelOrder as $order) {
                    $models[] = $origModels[$order];
                }
                $questions[] = $models;
            }
        }
        echo json_encode([
            "questions" => $questions,
            "progress" => $progress,
            "answerContext" => $this->getAnswerContext(),
        ]);
    }

    public function getAnswerContext() {
        $rawAnsweredLods = Form::post("answeredLods", []);
        if (!is_array($rawAnsweredLods)) {
            $rawAnsweredLods = [];
        }
        $lastModelId = -1;
        $answerLods = [];
        foreach ($rawAnsweredLods as $rawAnswerLod) {
            list ($modelId, $lod) = explode(",", $rawAnswerLod);
            if ($lastModelId != $modelId) {
                $answerLods = [$lod];
                $lastModelId = $modelId;
            } else {
                $answerLods[] = $lod;
            }
        }
        foreach ($this->lastAnswers as $answer) {
            if ($lastModelId != $answer["model_id"]) {
                $answerLods = [$answer["lod"]];
                $lastModelId = $answer["model_id"];
            } else {
                $answerLods[] = $answer["lod"];
            }
        }
        if (empty($answerLods)) {
            return null;
        }
        $lastAnswer = $this->lastAnswers[count($this->lastAnswers) - 1];
        return [
            "lastAnswer" => $lastAnswer,
            "answeredLods" => $answerLods,
        ];
    }

    private function yieldModel($model) {
        $refModel = $this->prepareModelParams($model, 0);
        $model    = $this->prepareModelParams($model, $model["lod"]);
        return [$refModel, $model];
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
        if ($answerRawData = Form::post("answer", [])) { // ["ModelID,Rotation,LOD,IsBetterThanRef", ...]
            $answerData = self::ensureAnswerDataFormat($unit, $answerRawData);
            if (!empty($answerData)) {
                $unit->writeJudgeData($answerData);
                $this->lastAnswers = $answerData;
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
            list($modelId, $rotation, $lod, $isBetterThanRef) = explode(",", $answer);
            if (is_numeric($modelId) 
                && is_numeric($lod)
                && is_numeric($rotation))
            {
                $answerData[] = [
                    "unit_id" => $unit->getUnitId(),
                    "model_id" => $modelId,
                    "rotation_id" => $rotation,
                    "lod" => $lod,
                    "is_same" => 0,
                    // リファレンスモデル (LOD=0) よりもよく見えたかどうか
                    "is_better_than_ref" => $isBetterThanRef == 1 ? 1 : 0,
                    "worker_id" => $unit->getWorkerId(),
                ];
            }
        }
        return $answerData;
    }

    private function prepareModelParams($model, $lod) {
        $model["path"] = $this->question->modelPath($model["id"], $model["rotation"], $lod);
        $model["formId"] = "answer-form-".$this->modelFormId;
        $this->modelFormId++;
        $model["formValue"] = implode(",", [
            $model["id"],
            $model["rotation"],
            $model["lod"],
            // リファレンスモデル (LOD=0) よりよく見えるなら 1
            $lod != 0 ? 1 : 0,
        ]);
        return $model;
    }
}