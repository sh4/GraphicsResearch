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
use GraphicsResearch\DB;

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
        $questions = $this->unit->getRandomQuestionOrder($this->getAnswerContext());
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
        return $this->unit->getAnswerProgress();
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
            // クイズモード
            $quizUnit->setQuizSessionId("");
            return $quizUnit;
        }

        // 本番モード
        return JobUnit::loadFromId($unitId);
    }

    public function getQuestionInfo() {
        $isFetchLods = (int)Form::get("fetchLods", 0) == 1;
        $isPaintContinue = (int)Form::get("paintContinue", 0) == 1;
        $skipLods = (int)Form::get("skipLods", 0);
        $answerCtx = $this->getAnswerContext();

        // 新しいモデルデータの取得を求められているか
        $questions = [];
        if ($isFetchLods) {
            $isPaintMode = $this->isPaintMode();
            if ($isPaintMode) {
                $questions = $this->enumerateQuestionsWithPainting($skipLods);                
            } else {
                $questions = $this->enumerateQuestions();
                if ($interruptQuestion = $this->checkLodThresholdAndGetInterruptQuestion($answerCtx)) {
                    array_unshift($questions, $interruptQuestion);
                }
            }
        }
        if ($isPaintContinue) {
            $questions[] = $this->fetchNextLodForContinuePainting($answerCtx);
        }

        return [
            "questions" => $questions,
            "progress" => $this->getAnswerProgress(),
            "answerContext" => $answerCtx->toArray(),
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
            return $job->getTaskType() === Job::TaskType_Painting;
        }
        return false;
    }

    private function enumerateQuestions() {
        $questions = [];
        // 最後に回答したモデルのID
        $skipModelId = null;
        $answerContext = $this->getAnswerContext();
        if ($lastAnswer = $answerContext->getLastAnswer()) {
            $skipModelId = $lastAnswer["model_id"];
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
            $questions[] = $this->shuffleLods($origModels);
        }
        return $questions;
    }

    private function enumerateQuestionsWithPainting($skipLods) {
        $prefetchQuestions = 4; // 先読み質問数
        $questions = [];
        foreach ($this->getQuestionOrders() as $models) {
            if ($skipLods > 0) {
                $skipLods--;
                continue;
            }
            $questions[] = $this->shuffleLods($models);
            if (--$prefetchQuestions <= 0) {
                break;
            }
        }
        return $questions;
    }

    // リファレンスと比較モデルの並びをシャッフルする
    private function shuffleLods($origModels) {
        $modelOrder = array_keys($origModels);
        shuffle($modelOrder);

        $models = [];
        foreach ($modelOrder as $order) {
            $models[] = $origModels[$order];
        }
        return $models;
    }

    private function yieldModel($model) {
        $refModel = $this->prepareModelParams($model, Question::ReferenceLod);
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
        if ($answerRawData = Form::post("answer", [])) { // ["ModelID,Rotation,LOD,IsBetterThanRef,IsDifferent", ...]
            $answerData = self::ensureAnswerDataFormat($unit, $answerRawData);
            if (!empty($answerData)) {
                $this->lastJudgementIds = $unit->writeJudgeData($answerData);
                $this->lastAnswers = $answerData;
            }
        }

        // ペイントデータが存在すればそれを DB に保存
        if ($paintRawData = Form::post("paint", [])) { 
            $appRoot = dirname(__FILE__)."/../../..";
            $rootDir = "$appRoot/../";
            foreach ($paintRawData as $i => $paint) {
                $judgementId = $this->lastJudgementIds[$i];
                $paintingFilePath = $rootDir."/".$unit->getPaintingFilePath($judgementId);
                $paintingDir = dirname($paintingFilePath);
                if (!file_exists($paintingDir)) {
                    mkdir($paintingDir, 0777, true);
                }
                if (Form::saveFile($paint["name"], $paintingFilePath)) {
                    // 回答データについて、ペイントを行った旨を記録
                    $unit->setIsPaintingCompleted($judgementId);
                }
            }
        }

        // Unit に設定されているワーカー ID をセッションに格納
        // (同一ユーザーかどうかを判定するためのもの。クライアント側でセッションが破棄された場合は不正確)
        $_SESSION[self::WorkerId] = $unit->getWorkerId();

        return $unit;
    }

    private function fetchNextLodForContinuePainting($answerCtx) {
        if (!($job = $this->getJob())) {
            return null;
        }
        if ($job->getTaskType() !== Job::TaskType_ThresholdJudgement) {
            return null;
        }
        if (!($lastAnswer = $answerCtx->getLastAnswer())) {
            return null;
        }
        $lod = $lastAnswer["lod"];
        $lod += 1;
        $model = [
            "id" => $lastAnswer["model_id"],
            "rotation" => $lastAnswer["rotation_id"],
            "lod" => $lod,
        ];
        return [
            // ペイントをアクティブにする
            $this->prepareModelParams($model, 0, true),
            $this->prepareModelParams($model, $lod, true),
        ];
    }

    private function checkLodThresholdAndGetInterruptQuestion($answerCtx) {
        if (!($job = $this->getJob())) {
            return null;
        }
        if ($job->getTaskType() !== Job::TaskType_ThresholdJudgement) {
            return null;
        }
        if (!($lastAnswer = $answerCtx->getLastAnswer())) {
            return null;
        }

        $modelId = $lastAnswer["model_id"];
        $rotationId = $lastAnswer["rotation_id"];
        $judgements = DB::instance()->fetchAll("
            SELECT
                lod,
                is_better_than_ref,
                is_different
            FROM job_unit_judgement
            WHERE unit_id = ? AND model_id = ? AND rotation_id = ?
            ORDER BY lod ASC
        ", [
            $this->unit->getUnitId(),
            $modelId,
            $rotationId,
        ]);

        // すでにペイントが回答済みならスキップ
        // (あるモデルの回答数が LOD 数よりも多くなっている)
        if (count($judgements) > $this->question->lodVariationCount()) {
            return null;
        }

        $thresholdLod = null;
        $answerLogs = [];
        foreach ($judgements as $i => $judgement) {
            $isDifferent = (int)$judgement["is_different"];
            $isBetterThanRef = (int)$judgement["is_better_than_ref"];
            $lod = $judgement["lod"];
            // is_different == 0 (差異が認められず) の後、
            // 最初に is_differnt == 1 (差異が認められたもの) なものを探す
            if ($isDifferent === 0) {
                if ($thresholdLod === null) {
                    $answerLogs[] = "[$i] SKIP: isDifferent = $isDifferent, isBetterThanRef = $isBetterThanRef, lod = $lod";
                    continue;
                } else {
                    // 差異が認められない判定が、キワが出てた後に現れたので NG
                    return null;
                }
            }
            if ($isBetterThanRef === 1) {
                // リファレンスよりいいと判断した解答（不正解）あったので NG
                return null;
            }
            if ($thresholdLod === null && $isDifferent === 1) {
                // 最初に差異があると認識したLOD (キワのLOD)
                $answerLogs[] = "[$i] SET: isDifferent = $isDifferent, isBetterThanRef = $isBetterThanRef, lod = $lod";
                $thresholdLod = $lod;
            } else {
                $answerLogs[] = "[$i] CONTINUE: isDifferent = $isDifferent, isBetterThanRef = $isBetterThanRef, lod = $lod";
            }
        }
        // 全問正解かつ差異があると認識したものがない場合は LOD 1 をキワとする
        if ($thresholdLod === null) {
            $thresholdLod = 1;
        }
        // キワの LOD をペイントタスクとして返す。左がリファレンス固定
        $model = [
            "id" => $modelId,
            "rotation" => $rotationId,
            "lod" => $thresholdLod,
        ];
        return [
            // ペイントをアクティブにする
            array_merge($this->prepareModelParams($model, 0, true), [
                "debug" => [
                    "judgements" => $judgements,
                    "thresholdLod" => $thresholdLod,
                    "answerLogs" => $answerLogs,
                ],
            ]),
            $this->prepareModelParams($model, $thresholdLod, true),
        ];
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
            list($modelId, $rotation, $lod, $isBetterThanRef, $isDifferent) = explode(",", $answer);
            $answerData[] = [
                "unit_id" => $unit->getUnitId(),
                "worker_id" => $unit->getWorkerId(),
                "model_id" => $modelId,
                "rotation_id" => $rotation,
                "lod" => $lod,
                "is_same" => 0,
                // リファレンスモデル (LOD=0) よりも良く見えたかどうか
                "is_better_than_ref" => $isBetterThanRef == 1 ? 1 : 0,
                // 差異が見つかったかどうか
                "is_different" => $isDifferent == 1 ? 1 : 0,
            ];
        }
        return $answerData;
    }

    private function prepareModelParams($model, $lod, $isPainting = false) {
        $model["path"] = $this->question->modelPath($model["id"], $model["rotation"], $lod);
        // LOD0 以外で、かつペイントモードが有効ならマスクデータを含ませる
        if ($lod != 0 && $this->isPaintMode()) {
            $model["mask"] = $this->question->maskPath($model["id"], $model["rotation"], $lod);
        }
        $model["formId"] = "answer-form-".$this->modelFormId;
        $this->modelFormId++;
        $model["formValue"] = implode(",", [
            $model["id"],
            $model["rotation"],
            (int)$model["lod"],
            // リファレンスモデル (LOD=0) よりよく見えるなら 1
            $lod != Question::ReferenceLod ? 1 : 0,
            // 差異が見つかれば 1, 差異がなければ 0
            1,
        ]);
        $model["paint"] = $isPainting;
        $model["lod"] = (int)$lod;
        return $model;
    }
}