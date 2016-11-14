<?php

require_once "config.php";

use GraphicsResearch\Form;
use GraphicsResearch\AbstractUnit;
use GraphicsResearch\Job;
use GraphicsResearch\JobUnit;
use GraphicsResearch\DB;
use GraphicsResearch\Question;
use GraphicsResearch\QuestionPage;
use GraphicsResearch\Page\Upload;
use GraphicsResearch\Page\Index;

class Router {
    private $routingMap = [];
    private static $defaultRouter;

    public static function instance() {
        if (self::$defaultRouter === null) {
            self::$defaultRouter = new Router();
        }
        return self::$defaultRouter;
    }

    public static function redirect($location, $params = []) {
        $url = self::Path($location);
        if (!empty($params)) {
            $url .= "?";
            $url .= http_build_query($params);
        }
        header("HTTP/1.1 302 Found");
        header("Location: $url");
        self::instance()->Cleanup();
        exit(0);
    }

    public static function Url() {
        $scheme = "http";
        if (isset($_SERVER["HTTPS"]) && !empty($_SERVER["HTTPS"])) {
            $scheme = "https";
        }
        return "$scheme://".$_SERVER["HTTP_HOST"].$_SERVER["SCRIPT_NAME"];
    }

    public static function Flash($name, $value = null) {
        if (!isset($_SESSION["flash"])) {
            $_SESSION["flash"] = [];
        }
        if ($value === null) {
            if (isset($_SESSION["flash"][$name])) {
                return $_SESSION["flash"][$name];
            } else {
                return null;
            }
        } else {
            $_SESSION["flash"][$name] = $value;
            $_SESSION["flash.expire"] = false;
        }
        return $value;
    }

    public static function Path($action = "") {
        $pathInfo = "";
        if (isset($_SERVER["PATH_INFO"])) {
            $pathInfo = $_SERVER["PATH_INFO"];
        }
        $relativePathCount = substr_count($pathInfo, "/");
        $indexPath = str_repeat("../", $relativePathCount);
        if (!empty($action)) {
            $indexPath .= "index.php/$action";
        }
        if (empty($indexPath)) {
            $indexPath = ".";
        }
        return rtrim($indexPath, "/");
    }

    public function Register($routingMap) {
        foreach ($routingMap as $path => $executeAction) {
            if (!is_callable($executeAction)) {
                throw new Exception("Not callable action: $path => $executeAction");
            }
            $path = rtrim($path, "/");
            if (isset($this->routingMap[$path])) {
                throw new Exception("Routing $path already assigned");
            }
            $this->routingMap[$path] = $executeAction;
        }
    }

    public function Execute($pathInfo = null) {
        if ($pathInfo === null && isset($_SERVER["PATH_INFO"])) {
            $pathInfo = $_SERVER["PATH_INFO"];
        }
        $path = rtrim($pathInfo, "/");
        if (isset($this->routingMap[$path])) {
            try {
                $executeAction = $this->routingMap[$path];
                return $executeAction();
            } catch (Exception $e) {
                header_remove("Location");
                header_remove("Content-Type");
                header("HTTP/1.1 500 Server Internal Error");
                header("Content-Type: text/plain; charset=utf-8");
                $error = $this->toErrorMessage($e);
                if (DEBUG) {
                    echo $error;
                } else {
                    // ユーザー向けのエラーメッセージ出す
                    echo "Internal Server Error: Please refresh this page.";
                }
                error_log($error, 3, __DIR__."/logs/error.log");
            } finally {
                $this->Cleanup();
            }
        } else {
            header("HTTP/1.1 404 Not Found");
        }
        return null;
    }

    private function toErrorMessage(Exception $e) {
        $error  = "";
        $error .= $e->getMessage();
        $error .= "\r\n";
        $error .= str_repeat("-", 70);
        $error .= "\r\n";
        $error .= $e->getTraceAsString();
        return $error;
    }

    private function Cleanup() {
        if (isset($_SESSION["flash.expire"])) {
            if ($_SESSION["flash.expire"]) {
                // フラッシュをクリーンアップ
                unset($_SESSION["flash"]);
                unset($_SESSION["flash.expire"]);
            } else {
                // 次回のリクエストで Flash をクリーンアップする
                $_SESSION["flash.expire"] = true;
            }
        }
    }
}

\Router::instance()->Register([

    "/" => function () {
        // 回答ページ
        session_start();
        include "view/question.php";
    },

    "/done" => function () {
        // 回答完了
        session_start();
        include "view/question_complete.php";
    },

    "/verify" => function () {
        // 回答データの確認
        header("Content-Type: application/json; charset=utf-8");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: *");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET");

        $ok = false;
        $unitId = Form::get("unitId", "");
        $verificationCode = Form::get("verificationCode", "");
        if ($unit = JobUnit::loadFromId($unitId)) {
            $remainQuestions = $unit->getTotalQuestionCount() - $unit->getAnsweredQuestionCount();
            if ($remainQuestions <= 0) {
                $ok = $unit->getVerificationCode() == $verificationCode;
            }
        }
        echo json_encode([ "ok" => $ok ]);
    },

    "/admin" => function () {
        // 管理画面
        session_start();
        // POST で処理する前の下準備
        $password = Form::post("password", "");
        if (!empty($password)) {
            if ($password === ADMIN_PASSWORD) {
                Form::ensureCSRFToken();
                // ログインが完了したので管理者画面に改めて遷移
                session_regenerate_id(true);
                $_SESSION["admin_login"] = true;
                Router::redirect("admin");
            } else {
                Router::Flash("warning", "Your password does not match.");
                Router::redirect("admin");
            }
        }
        if (Form::session("admin_login", false)) {
            // 管理画面アクセス時にDBのマイグレーションを行う
            DB::instance()->migrateSchema();
            // 管理者画面 (ジョブ一覧、新規作成)
            include "view/admin.php";
        } else {
            include "view/admin_login.php";
        }
    },

    "/admin/logout" => function () {
        // 管理画面ログアウト
        session_start();
        if (Form::session("admin_login")) {
            Router::Flash("success", "You have successfully logged out.");
            session_destroy();
        }
        Router::redirect("admin");
    },

    "/admin/question" => function () {
        // 質問ページ編集
        session_start();
        if (!Form::session("admin_login", false)) {
            Router::redirect("admin");
        }
        if (Form::isPOST()) {
            Form::ensureCSRFToken();
            $rawQuestionPage = Form::post("question", []);
            QuestionPage::Update("default", $rawQuestionPage);
            Router::Flash("success", "Question page successfully updated.");
        }
        Router::redirect("admin");
    },

    "/admin/question/remove" => function () {
        // 質問ページの画像削除
        session_start();
        if (!Form::session("admin_login", false)) {
            Router::redirect("admin");
        }
        if (Form::isPOST()) {
            Form::ensureCSRFToken();
            $targetFiles = [];
            if ($removeFilePattern = Form::post("remove_file_pattern", "")) {
                $targetFiles = Question::getModelFileWithPattern(JUDGEMENT_IMAGES, $removeFilePattern);
            }
            else if (Form::post("cleanup_invalid_dataset")) {
                $question = Question::buildFromModelDirectory(JUDGEMENT_IMAGES);
                $targetFiles = $question->invalidModelFiles();
            }
            $removedFiles = Question::removeModelFiles(JUDGEMENT_IMAGES, $targetFiles);
            if ($removedFiles > 0) {
                Router::Flash("success", "Image file deleted: $removedFiles files.");
            } else {
                Router::Flash("warning", "Not found images that match file pattern.");
            }
        }
        Router::redirect("admin");
    },

    "/admin/jobs" => function () {
        // ジョブ詳細ページ
        session_start();
        if (!Form::session("admin_login", false)) {
            Router::redirect("admin");
        }
        if (Form::isPOST()) {
            Form::ensureCSRFToken();
            $rawJob = Form::post("job", []);
            try {
                if ($quizQuestions = Form::getFile("quiz_questions")) {
                    $rawJob["quiz_questions"] = Question::parseQuizGoldenDataFromCSV($quizQuestions);
                }
                $job = Job::createNewJob($rawJob);
                unset($_SESSION["job"]);
                Router::Flash("success", "You have successfully created the job: ".htmlspecialchars($job->getTitle()));
            } catch (Exception $e) {
                $_SESSION["job"] = $rawJob;
                Router::Flash("warning", "Create job failed, Please check job form input.");
            }
            Router::redirect("admin");
        }
        $job = Job::loadFromId(Form::get("jobId", ""));
        if ($job === null) {
            Router::redirect("admin");
        }
        include "view/admin_job.php";
    },

    "/admin/jobs/launch" => function () {
        // ジョブの公開
        session_start();
        if (!Form::session("admin_login", false)) {
            Router::redirect("admin");
        }
        $job = Job::loadFromId(Form::get("jobId", ""));
        $channel = Form::get("channel", []);
        if ($job && !empty($channel)) {
            if ($job->launchJob($channel)) {
                Router::Flash("success", "Successfully launched the job: ".htmlspecialchars($job->getTitle()));
            } else {
                $jobId = $job->getJobId();
                Router::Flash("warning", 
                    "Launch job failed, ".
                    '<a href="https://make.crowdflower.com/jobs/'.$jobId.'" target="_blank">Please retry launch the job from the CrowdFlower job page.</a>');
            }
        }
        Router::redirect("admin");
    },

    "/admin/jobs/delete" => function () {
        // ジョブの削除
        session_start();
        if (!Form::session("admin_login", false)) {
            Router::redirect("admin");
        }
        if (Form::isPOST()) {
            Form::ensureCSRFToken();
            $job = Job::loadFromId(Form::post("jobId", ""));
            if ($job) {
                if (Job::deleteFromId($job->getJobId())) {
                    // ジョブの削除を行っても、外部サイトの情報は消えない点に注意
                    $jobId = $job->getJobId();
                    Router::Flash("success", 
                        "Successfully delete the job from this site: ".htmlspecialchars($job->getTitle()).
                        ', <a href="https://make.crowdflower.com/jobs/$jobId/">Please delete manually of the CrowdFlower job.</a>'
                    );
                } else {
                    Router::Flash("warning", "Delete job failed.");
                }
            }
        }
        Router::redirect("admin");
    },

    "/admin/jobs/unit" => function() {
        // ユニット詳細ページ
        $job = Job::loadFromId(Form::get("jobId", ""));
        if ($job === null) {
            Router::redirect("admin");
        }
        include "view/admin_job_unit.php";
    },

    "/download" => function () {
        session_start();
        if (!Form::session("admin_login", false)) {
            Router::redirect("admin");
        }
        
        // 回答済みデータダウンロード
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=judgement.csv");

        $question = Question::buildFromModelDirectory(JUDGEMENT_IMAGES);
        $jobId = Form::get("jobId", "");
        // ヘッダ行を書き出し
        echo implode(",", [
            "WorkerID",
            "ModelID",
            "RotationID",
            "LOD",
            "IsBetterThanReferenceModel",
            "Filename",
        ]);
        echo "\r\n";
        // 回答データを書き出し
        foreach (JobUnit::eachJudgementData($jobId) as $judgement) {
            // 連想配列をローカル変数に展開
            extract($judgement);
            // ワーカーIDが未設定なら、正確ではないが UnitId を設定
            // （必ずしも単一の人物が回答したとは限らないため）
            if (empty($worker_id)) {
                $worker_id = $unit_id;
            }
            $modelPath = $question->modelPath($model_id, $rotation_id, $lod);
            echo implode(",", [
                $worker_id,
                $model_id,
                $rotation_id,
                $lod,
                $is_better_than_ref,
                basename($modelPath),
            ]);
            echo "\r\n";
        }
    },

    "/upload" => function () {
        // 回答用データアップロード
        header("Content-Type: application/json; charset=utf-8");

        $page = new Upload();
        $result = [ "ok" => false ];
        if (!$page->isValidUploadKey()) {
            echo json_encode($result);
            exit;
        }
        if (Form::file("file")) {
            // upload file
            $result["ok"] = $page->uploadModelFile();
        }
        if (Form::post("ls")) {
            // list files
            $result["files"] = $page->listModelFile();
            $result["ok"] = true;
        }
        if (Form::post("rm")) {
            // remove files
            $removedFiles = $page->removeModelFile(Form::post("rm", []));
            if (!empty($removedFiles)) {
                $result["ok"] = true;
                $result["removedFiles"] = $removedFiles;
            }
        }
        echo json_encode($result);
    },

    "/api/question" => function () {
        session_start();
        header("Content-Type: application/json; charset=utf-8");
        $page = new Index();
        $page->renderQuestionJson();
    },
]);