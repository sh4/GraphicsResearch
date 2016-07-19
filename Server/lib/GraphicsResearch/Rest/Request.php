<?php

namespace GraphicsResearch\Rest;

class Request {
    private $content;
    private $httpHeaders = [];
    private $event;

    public function __construct($content, $contentType) {
        $this->content = $content;
        $this->httpHeaders = [];
        $this->event = new Event();
        $this->setHeader("Content-Type", $contentType);
    }

    public function getEvent() {
        return $this->event;
    }

    public function getContent() {
        return $this->content;
    }

    public function setHeader($name, $value = null) {
        if (is_array($name)) {
            $this->httpHeaders = array_merge($name, $this->httpHeaders);
        } else  {
            $this->httpHeaders[$name] = $value;
        }
    }

    public function getHeaders($name = null) {
        if ($name !== null) {
            if (isset($this->httpHeaders[$name])) {
                return $this->httpHeaders[$name];
            } else {
                return null;
            }
        } else {
            return $this->httpHeaders;
        }
    }

    public static function createEmpty() {
        return new Request(null, null);
    }

    public static function form($params = []) {
        return new Request(http_build_query($params), "application/x-www-form-urlencoded");
    }

    public static function json($params = []) {
        return new Request(json_encode($params), "application/json");
    }
}
