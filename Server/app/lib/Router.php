<?php

class Router {
    private $routingMap = [];
    private static $defaultRouter;

    public static function instance() {
        if (self::$defaultRouter === null) {
            self::$defaultRouter = new Router();
        }
        return self::$defaultRouter;
    }

    public static function redirect($location, $params = []) {
        $url = self::Path($location);
        if (!empty($params)) {
            $url .= "?";
            $url .= http_build_query($params);
        }
        header("HTTP/1.1 302 Found");
        header("Location: $url");
        self::instance()->Cleanup();
        exit(0);
    }

    public static function Url() {
        $scheme = "http";
        if (isset($_SERVER["HTTPS"]) && !empty($_SERVER["HTTPS"])) {
            $scheme = "https";
        }
        return "$scheme://".$_SERVER["HTTP_HOST"].$_SERVER["SCRIPT_NAME"];
    }

    public static function Flash($name, $value = null) {
        if (!isset($_SESSION["flash"])) {
            $_SESSION["flash"] = [];
        }
        if ($value === null) {
            if (isset($_SESSION["flash"][$name])) {
                return $_SESSION["flash"][$name];
            } else {
                return null;
            }
        } else {
            $_SESSION["flash"][$name] = $value;
            $_SESSION["flash.expire"] = false;
        }
        return $value;
    }

    public static function Path($action = "") {
        $pathInfo = "";
        if (isset($_SERVER["PATH_INFO"])) {
            $pathInfo = $_SERVER["PATH_INFO"];
        }
        $relativePathCount = substr_count($pathInfo, "/");
        $indexPath = str_repeat("../", $relativePathCount);
        if (!empty($action)) {
            $indexPath .= "index.php/$action";
        } else {
            $indexPath .= "app";
        }
        if (empty($indexPath)) {
            $indexPath = ".";
        }
        return rtrim($indexPath, "/");
    }

    public function Register($routingMap) {
        foreach ($routingMap as $path => $executeAction) {
            if (!is_callable($executeAction)) {
                throw new Exception("Not callable action: $path => $executeAction");
            }
            $path = rtrim($path, "/");
            if (isset($this->routingMap[$path])) {
                throw new Exception("Routing $path already assigned");
            }
            $this->routingMap[$path] = $executeAction;
        }
    }

    public function Execute($pathInfo = null) {
        if ($pathInfo === null && isset($_SERVER["PATH_INFO"])) {
            $pathInfo = $_SERVER["PATH_INFO"];
        }
        $path = rtrim($pathInfo, "/");
        if (isset($this->routingMap[$path])) {
            try {
                $executeAction = $this->routingMap[$path];
                return $executeAction();
            } catch (Exception $e) {
                @header_remove("Location");
                @header_remove("Content-Type");
                @header("HTTP/1.1 500 Server Internal Error");
                @header("Content-Type: text/plain; charset=utf-8");
                $error = $this->toErrorMessage($e);
                if (DEBUG) {
                    echo $error;
                } else {
                    // ユーザー向けのエラーメッセージ出す
                    echo "Internal Server Error: Please refresh this page.";
                }
                error_log($error, 3, __DIR__."/../../logs/error.log");
            } finally {
                $this->Cleanup();
            }
        } else {
            header("HTTP/1.1 404 Not Found");
        }
        return null;
    }

    private function toErrorMessage(Exception $e) {
        $error  = "";
        $error .= date("Y/m/d H:i:s");
        $error .= "\r\n";
        $error .= $e->getMessage();
        $error .= "\r\n";
        $error .= str_repeat("-", 70);
        $error .= "\r\n";
        $error .= $e->getTraceAsString();
        return $error;
    }

    private function Cleanup() {
        if (isset($_SESSION["flash.expire"])) {
            if ($_SESSION["flash.expire"]) {
                // フラッシュをクリーンアップ
                unset($_SESSION["flash"]);
                unset($_SESSION["flash.expire"]);
            } else {
                // 次回のリクエストで Flash をクリーンアップする
                $_SESSION["flash.expire"] = true;
            }
        }
    }
}
