<?php

namespace GraphicsResearch;

class Crypto {
    public static function CreateUniqueId($byteLength) {
        $str = base64_encode(openssl_random_pseudo_bytes($byteLength));
        return rtrim(str_replace(['/', '+'], ['x', 'X'], $str), '=');
    }

    public static function CreateUniqueNumber($length) {
        $size = round($length / 2);
        $code = implode('', array_map('hexdec', str_split(bin2hex(openssl_random_pseudo_bytes($size)), 2)));
        return substr($code, 0, $length);
    }

    public static function isValidUniqueId($uniqueId) {
        return preg_match('#^[a-z0-9\-@]+$#ui', $uniqueId);
    }
}
