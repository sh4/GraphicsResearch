<?php

namespace GraphicsResearch;

class QuestionPage {

    public static function DefaultPage() {
        return DB::instance()->fetchRow("SELECT * FROM question_page WHERE page_key = ?", [ "default" ]);
    }

    public static function Update($key, $questionPage) {
        return DB::instance()->update("question_page", "page_key = 'default'", [
            "instructions" => $questionPage["instructions"],
        ]);
    }

}
