<?php

namespace Page;

class Upload {
    public function __construct() {
    }

    public function isValidUploadKey() {
        $uploadKey = \Form::post("uploadKey", "");
        return $uploadKey != "" && $uploadKey === UPLOAD_KEY;
    }

    public function uploadModelFile() {
        $file = \Form::file("file");
        if ($file === null) {
            return false;
        }
        $fileName = $file["name"];
        $filePath = TEST_IMAGE_DIRECTORY."/$fileName";
        return \Form::saveFile("file", $filePath);
    }

    public function listModelFile() {
        $files = [];
        foreach (scandir(TEST_IMAGE_DIRECTORY) as $file) {
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
            if (!preg_match('#^[a-z0-9_\-\.]+$#iu', $removeFile)) {
                continue;
            }
            $targetFilePath = TEST_IMAGE_DIRECTORY."/$removeFile";
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
