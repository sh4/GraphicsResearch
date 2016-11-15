<?php

namespace GraphicsResearch\Rest;

class Event {
    private $requestHandlers = [];
    private $responseHandlers = [];

    public function onRequest($handler) {
        if (is_callable($handler)) {
            $this->requestHandlers[] = $handler;
        }
    }

    public function onResponse($handler) {
        if (is_callable($handler)) {
            $this->responseHandlers[] = $handler;
        }
    }

    public function invokeOnRequest(Request $request) {
        foreach ($this->requestHandlers as $handler) {
            $handler($request);
        }
    }

    public function invokeOnResponse(Response $response) {
        foreach ($this->responseHandlers as $handler) {
            $handler($response);
        }
    }
}