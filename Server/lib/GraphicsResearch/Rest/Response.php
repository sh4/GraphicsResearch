<?php

namespace GraphicsResearch\Rest;

class Response {
    private $header;
    private $body;
    private $handshake;
    private $request;

    public function __construct(Request $request, $handshake, $header, $body) {
        $this->header = $header;
        $this->body = $body;
        $this->handshake = $handshake;
        $this->request = $request;
    }

    public function getHeader() {
        return $this->header;
    }

    public function getBody() {
        return $this->body;
    }

    public function getHandshake() {
        return $this->handshake;
    }

    public function getRequest() {
        return $this->request;
    }
}