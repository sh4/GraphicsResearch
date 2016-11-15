<?php

namespace GraphicsResearch;

class Form {
    const CSRFTokenName = "FORM_CSRF_TOKEN";
    const CSRFTokenIndexName = "FORM_CSRF_TOKEN_IDX";

    private static $CSRFTokenIndex = 0;

    public static function e($str) {
        echo htmlspecialchars($str, ENT_QUOTES);
    }

    public static function get($name, $default = null) {
        return isset($_GET[$name]) ? $_GET[$name] : $default;
    }

    public static function post($name, $default = null) {
        return isset($_POST[$name]) ? $_POST[$name] : $default;
    }

    public static function request($name, $default = null) {
        return isset($_REQUEST[$name]) ? $_REQUEST[$name] : $default;
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
        list($token, $index) = self::publishCSRFToken();
        echo '<input type="hidden" name="', self::CSRFTokenName, '" value="', $token, '" />', "\r\n";
        echo '<input type="hidden" name="', self::CSRFTokenIndexName, '" value="', $index, '" />', "\r\n";
    }

    public static function publishCSRFToken() {
        $index = ++self::$CSRFTokenIndex;
        $token = Crypto::CreateUniqueId(32);
        if (!isset($_SESSION[self::CSRFTokenName]) || !is_array($_SESSION[self::CSRFTokenName])) {
            $_SESSION[self::CSRFTokenName] = [];
        }
        $_SESSION[self::CSRFTokenName][$index] = $token;
        return [$token, $index];
    }

    public static function ensureCSRFToken() {
        $token = self::post(self::CSRFTokenName, "");
        $index = (int)self::post(self::CSRFTokenIndexName, 0);
        $sessionTokens = self::session(self::CSRFTokenName, []);
        if (empty($sessionTokens) || !is_array($sessionTokens)) {
            throw new \Exception("Security token check failed: sessionTokens is empty");
        }
        if (!isset($sessionTokens[$index])) {
            throw new \Exception("Security token check failed: sessionsTokens[$index] is not set");
        }
        if ($token !== $sessionTokens[$index]) {
            throw new \Exception("Security token check failed: session/post token mismatched");
        }
    }
}
