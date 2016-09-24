<?php

namespace GraphicsResearch;

class Form {
    const CSRFTokenName = "FORM_CSRF_TOKEN";

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
        } else {
            return false;
        }
    }

    // アップロードされたファイルを文字列として返す。
    // オンメモリの処理なので巨大なファイルを読み込まないようにすること。
    public static function getFile($name) {
        if (isset($_FILES[$name], $_FILES[$name]["tmp_name"])
            && is_uploaded_file($_FILES[$name]["tmp_name"]))
        {
            return file_get_contents($_FILES[$name]["tmp_name"]);
        } else {
            return null;
        }
    }

    public static function session($name, $default = null) {
        return isset($_SESSION[$name]) ? $_SESSION[$name] : $default;
    }
    
    public static function isPOST() {
        return $_SERVER["REQUEST_METHOD"] === "POST";
    }

    public static function isGET() {
        return $_SERVER["REQUEST_METHOD"] === "GET";
    }

    public static function enableCSRF() {
        $token = self::publishCSRFToken();
        echo '<input type="hidden" name="', self::CSRFTokenName, '" value="', $token, '" />', "\r\n";
    }

    public static function publishCSRFToken() {
        $token = uniqid(uniqid("", true), true);
        $_SESSION[self::CSRFTokenName] = $token;
        return $token;
    }

    public static function ensureCSRFToken() {
        $token = self::post(self::CSRFTokenName, "");
        $sessionToken = self::session(self::CSRFTokenName, "");
        if (empty($sessionToken)) {
            throw new \Exception("Security token check failed: sessionToken is empty");
        }
        if ($token !== $sessionToken) {
            throw new \Exception("Security token check failed: session/post token mismatched");
        }
    }
}
