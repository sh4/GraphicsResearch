<?php

namespace Page;

class Answer {
    public function __construct() {

    }

    public function echoAllSessionCsv() {
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=answer.csv");

        $sessionFiles = scandir(TEST_SESSION_DIRECTORY);
        foreach ($sessionFiles as $file) {
            if (!preg_match('#\.csv$#iu', $file)) {
                continue;
            }
            readfile(TEST_SESSION_DIRECTORY."/$file");
        }
    }
}