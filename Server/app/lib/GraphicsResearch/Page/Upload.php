<?php

namespace GraphicsResearch\Page;

use GraphicsResearch\Form;

class Upload {
    public function __construct() {
    }

    public function receiveFiles() {
        $result = [ "ok" => false ];
        if (!$this->isValidUploadKey()) {
            return $result;
        }
        if (Form::file("file")) {
            // upload file
            $result["ok"] = $this->uploadModelFile();
        }
        if (Form::post("ls")) {
            // list files
            $result["files"] = $this->listModelFile();
            $result["ok"] = true;
        }
        if (Form::post("rm")) {
            // remove files
            $removedFiles = $this->removeModelFile(Form::post("rm", []));
            if (!empty($removedFiles)) {
                $result["ok"] = true;
                $result["removedFiles"] = $removedFiles;
            }
        }
        return $result;
    }

    private function isValidUploadKey() {
        $uploadKey = Form::post("uploadKey", "");
        return $uploadKey != "" && $uploadKey === UPLOAD_KEY;
    }

    private function uploadModelFile() {
        $file = Form::file("file");
        if ($file === null) {
            return false;
        }
        $fileName = $file["name"];
        $filePath = JUDGEMENT_IMAGES."/$fileName";
        return Form::saveFile("file", $filePath);
    }

    private function listModelFile() {
        $files = [];
        foreach (scandir(JUDGEMENT_IMAGES) as $file) {
            if (!preg_match('#\.(?:gif|png|jpe?g)$#iu', $file)) {
                continue;
            }
            $files[] = $file;
        }
        return $files;
    }

    private function removeModelFile($targetFiles) {
        $removedFiles = [];
        foreach ($targetFiles as $removeFile) {
            if (!preg_match('#^[a-z0-9_\-]+\.(?:gif|png|jpe?g)$#iu', $removeFile)) {
                continue;
            }
            $targetFilePath = JUDGEMENT_IMAGES."/$removeFile";
            if (!file_exists($targetFilePath)) {
                continue;
            }
            if (unlink($targetFilePath)) {
                $removedFiles[] = $removeFile;
            }
        }
        return $removedFiles;
    }
}
