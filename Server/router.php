<?php

require_once "config.php";

use GraphicsResearch\Form;
use GraphicsResearch\Unit;
use GraphicsResearch\Job;
use GraphicsResearch\DB;
use GraphicsResearch\Question;
use GraphicsResearch\Page\Upload;

class Router {
    private $routingMap = [];
    private static $defaultRouter;

    public static function instance() {
        if (self::$defaultRouter === null) {
            self::$defaultRouter = new Router();
        }
        return self::$defaultRouter;
    }

    public static function redirect($location) {
        header("HTTP/1.1 302 Found");
        header("Location: ".self::Path($location));
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
                error_log($error, 3);
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
        if ($unit = Unit::loadFromId($unitId)) {
            if ($job = Job::loadFromId($unit->getJobId())) {
                $remainQuestions = $job->getQuestions() - count($unit->getJudgementData());
                if ($remainQuestions <= 0) {
                    $ok = $unit->getVerificationCode() == $verificationCode;
                }
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

    "/admin/jobs" => function () {
        // ジョブ詳細ページ
        session_start();
        if (!Form::session("admin_login", false)) {
            Router::redirect("admin");
        }
        if (Form::isPOST()) {
            $rawJob = Form::post("job", []);
            try {
                $rawJob["question_order_json"] = null;
                if ($customQuestionOrder = Form::getFile("job_question_order")) {
                    $rawJob["question_order_json"] = Question::ParseQuestionOrderFromCSV($customQuestionOrder);
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
                Router::Flash("warning", "Launch job failed, Please retry launch the job.");
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
        // 回答済みデータダウンロード
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=judgement.csv");

        $jobId = Form::get("jobId", "");
        if ($jobId) {
            // ジョブIDごと
            $rows = DB::instance()->each("SELECT judgement_data_json FROM job_unit WHERE job_id = ?", $jobId);
        } else {
            // すべての判定データ
            $rows = DB::instance()->each("SELECT judgement_data_json FROM job_unit");
        }
        echo implode(",", [
            "ModelID",
            "RotationID",
            "LOD",
            "ContainDifferences",
        ]);
        echo "\r\n";
        foreach ($rows as $row) {
            $judgementData = json_decode($row["judgement_data_json"]);
            foreach ($judgementData as $data) {
                echo implode(",", [
                    $data->id,
                    $data->rotation,
                    $data->lod,
                    $data->judge,
                ]);
                echo "\r\n";
            }
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
    }

]);