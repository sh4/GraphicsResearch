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
            $question = Question::buildFromModelDirectory(JUDGEMENT_IMAGES);
            if ($question->answerProgress($unit)->completed) {
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
                $cfJobId = $job->getCrowdFlowerJobId();
                Router::Flash("warning", 
                    "Launch job failed, ".
                    '<a href="https://make.crowdflower.com/jobs/'.$cfJobId.'" target="_blank">Please retry launch the job from the CrowdFlower job page.</a>');
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
                    $cfJobId = $job->getCrowdFlowerJobId();
                    Router::Flash("success", 
                        "Successfully delete the job from this site: ".htmlspecialchars($job->getTitle()).
                        ', <a href="https://make.crowdflower.com/jobs/'.$cfJobId.'/">Please delete manually of the CrowdFlower job.</a>'
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