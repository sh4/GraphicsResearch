<?php

namespace GraphicsResearch\Page;

use GraphicsResearch\Form;

class Upload {
    public function __construct() {
    }

    public function isValidUploadKey() {
        $uploadKey = Form::post("uploadKey", "");
        return $uploadKey != "" && $uploadKey === UPLOAD_KEY;
    }

    public function uploadModelFile() {
        $file = Form::file("file");
        if ($file === null) {
            return false;
        }
        $fileName = $file["name"];
        $filePath = JUDGEMENT_IMAGES."/$fileName";
        return Form::saveFile("file", $filePath);
    }

    public function listModelFile() {
        $files = [];
        foreach (scandir(JUDGEMENT_IMAGES) as $file) {
            if (!preg_match('#\.(?:gif|png|jpe?g)$#iu', $file)) {
                continue;
            }
            $files[] = $file;
        }
        return $files;
    }

    public function removeModelFile($targetFiles) {
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
