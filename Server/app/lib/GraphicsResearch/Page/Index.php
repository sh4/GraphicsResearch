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
use GraphicsResearch\AnswerContext;

class Index {
    private $question;
    private $questionPage;
    private $unit;
    private $job;
    private $number;
    private $formAction;
    private $lastAnswers;
    private $lastJudgementIds;
    private $modelFormId;
    private $isPaintMode;

    const WorkerId = "questionWorkerId";

    public function __construct() {
        $this->modelFormId = 0;
        $this->lastAnswers = [];
        $this->lastJudgementIds = [];
        $this->number = (int)Form::get("num", 1);
        $this->formAction = \Router::Path("/");
        if ($this->number !== null && $this->number > 0) {
            $this->formAction .= "?num=$this->number";
        }
        $this->question = Question::instance();
        $this->unit = $this->createOrUpdateUnit();
        $this->defaultQuestionPage = QuestionPage::DefaultPage();
        $this->job = null;
        $this->isPaintMode = null;
    }

    public function getFormAction() {
        return $this->formAction;
    }

    public function getNumber() {
        return $this->number;
    }

    public function getDefaultQuestionPage() {
        return $this->defaultQuestionPage;
    }

    public function getQuestionOrders() {
        $questions = $this->unit->getRandomQuestionOrder($this->question, $this->getAnswerContext());
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

    public function getJob() {
        if ($this->job !== null) {
            return $this->job;
        }
        if ($jobId = $this->unit->getJobId()) {
            return ($this->job = Job::loadFromId($jobId));
        }
        return null;
    }

    public static function loadUnit() {
        $unitId = Form::request("unitId", "");
        $quizUnit = null;
        if ($quizUnitId = Form::request("quizUnitId", "")) {
            $quizUnit = QuizUnit::loadFromId($quizUnitId);
        }

        $quizModeEnabled = Form::request("quizMode", 0) == 1;
        if ($quizModeEnabled) {
            // クイズモードの回答状況を復元
            $quizSessionId = Form::request("quizSid", "");
            if (empty($quizSessionId)) {
                return null;
            }
            $unit = $quizUnit;
            $unit->setQuizSessionId($quizSessionId);
        } else {
            $unit = JobUnit::loadFromId($unitId);
            // 指定された quizUnitId で QuizUnit からの読み込みが可能な場合は、
            // 新規に JobUnit を作成する (参照先の JobId は QuizUnit から得る)
            if (!$unit && $quizUnit) {
                // quiz データの回答は golden データと一致しないと正答率が下がるため、
                // 確認コードは quiz データのそれと同一にする
                $unit = JobUnit::createNewSession([
                    "unit_id" => $unitId,
                    "job_id" => $quizUnit->getJobId(),
                    "verification_code" => $quizUnit->getVerificationCode(),
                ]);
            }
            // JobUnit 同士を結びつける回答グループID が定義されていれば、それを反映
            if ($unit && ($answerGroupId = Form::request("gid"))) {
                $unit->setAnswerGroupId($answerGroupId);
            }
        }

        return $unit;
    }

    public function getQuestionInfo() {
        $isFetchLods = (int)Form::get("fetchLods", 0) == 1;

        // 新しいモデルデータの取得を求められているか
        $questions = [];
        if ($isFetchLods) {
            $isPaintMode = $this->isPaintMode();
            if ($isPaintMode) {
                $questions = $this->enumerateQuestionsWithPainting();                
            } else {
                $questions = $this->enumerateQuestions();
            }
        }

        return [
            "questions" => $questions,
            "progress" => $this->getAnswerProgress(),
            "answerContext" => $this->getAnswerContext()->toArray(),
            "lastJudgementIds" => $this->lastJudgementIds,
        ];
    }

    public function getAnswerContext() {
        $rawAnsweredLods = Form::post("answeredLods", []);
        if (!is_array($rawAnsweredLods)) {
            $rawAnsweredLods = [];
        }

        $lastAnsweredLods = array_merge(
            array_map(function ($e) {
                list ($modelId, $lod) = explode(",", $e);
                return [ "model_id" => $modelId, "lod" => $lod ];                
            }, $rawAnsweredLods),
            $this->lastAnswers
        );

        $lastModelId = -1; // 最後に回答した LOD
        $answerLods = []; // 回答済み LOD のリスト
        foreach ($lastAnsweredLods as $answer) {
            if ($lastModelId != $answer["model_id"]) { // 回答モデルが異なれば、リストを 1 から構築しなおす
                $answerLods = [$answer["lod"]];
                $lastModelId = $answer["model_id"];
            } else {
                $answerLods[] = $answer["lod"];
            }
        }

        if (empty($answerLods)) {
            return new AnswerContext(null, null, $this->isPaintMode());
        }

        $lastAnswer = $this->lastAnswers[count($this->lastAnswers) - 1];
        return new AnswerContext($lastAnswer, $answerLods, $this->isPaintMode());
    }

    public function isPaintMode() {
        if (is_a($this->unit, "GraphicsResearch\\QuizUnit")) {
            return false; // FIXME: クイズモードにおいてペイントは未サポートなので強制的に false とする
        }
        if ($job = $this->getJob()) {
            return $job->getTaskType() == Job::TaskType_Painting;
        }
        return false;
    }

    private function enumerateQuestions() {
        $questions = [];
        // 最後に回答したモデルのID
        $skipModelId = null;
        $answerContext = $this->getAnswerContext();
        if ($lastAnswer = $answerContext->getLastAnswer()) {
            $skipModelId = (int)$lastAnswer["model_id"];
        }
        // 最後に比較したモデルID
        $lastModelId = null;
        foreach ($this->getQuestionOrders() as $origModels) {
            list ($model) = $origModels;
            if ($model["id"] == $skipModelId) {
                continue;
            }
            if ($model["id"] !== $lastModelId) {
                // 異なるモデルIDが現れたら、その時点で列挙は終了
                $isInitialModelId = $lastModelId === null;
                $lastModelId = $model["id"];
                if (!$isInitialModelId) {
                    break;
                }
            }

            $modelOrder = array_keys($origModels);
            // リファレンスと比較モデルの並びをシャッフルする
            shuffle($modelOrder);

            $models = [];
            foreach ($modelOrder as $order) {
                $models[] = $origModels[$order];
            }
            $questions[] = $models;
        }
        return $questions;
    }

    private function enumerateQuestionsWithPainting() {
        $prefetchQuestions = 4; // 先読み質問数
        $questions = [];
        foreach ($this->getQuestionOrders() as $models) {
            $questions[] = $models;
            if (--$prefetchQuestions <= 0) {
                break;
            }
        }
        return $questions;
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
                $this->lastJudgementIds = $unit->writeJudgeData($answerData);
                $this->lastAnswers = $answerData;
            }
        }

        // ペイントデータが存在すればそれを DB に保存
        if ($paintRawData = Form::post("paint", [])) { 
            $appRoot = dirname(__FILE__)."/../../..";
            $rootDir = "$appRoot/../".PAINTING_TASK_IMAGES;
            foreach ($paintRawData as $i => $paint) {
                $judgementId = $this->lastJudgementIds[$i];
                $paintingFilePath = $rootDir."/".$unit->getPaintingFilePath($this->question, $judgementId);
                $paintingDir = dirname($paintingFilePath);
                if (!file_exists($paintingDir)) {
                    mkdir($paintingDir, 0777, true);
                }
                if (Form::saveFile($paint["name"], $paintingFilePath)) {
                    $unit->setIsPaintingCompleted($judgementId);
                }
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