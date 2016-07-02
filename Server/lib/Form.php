<?php

class Form {
    public static function e($str) {
        echo htmlspecialchars($str, ENT_QUOTES);
    }

    public static function get($name, $default = null) {
        return isset($_GET[$name]) ? $_GET[$name] : $default;
    }

    public static function post($name, $default = null) {
        return isset($_POST[$name]) ? $_POST[$name] : $default;
    }

    public static function file($name) {
        return isset($_FILES[$name]) ? $_FILES[$name] : null;
    }

    public static function saveFile($name, $uploadPath) {
        if (isset($_FILES[$name], $_FILES[$name]["tmp_name"])
            && move_uploaded_file($_FILES[$name]["tmp_name"], $uploadPath))
        {
            return true;
        }
        else
        {
            return false;
        }
    }
}
