<?php

namespace GraphicsResearch\Rest;

class Client {
    private $userAgent;
    private $event;

    public function __construct() {
        $this->userAgent = "PHP RestClient/v0.1.0";
        $this->event = new Event();
    }

    public function get($url, $param = []) {
        return $this->invoke("GET", self::appendQueryString($url, $param), Request::createEmpty());
    }

    public function post($url, Request $request) {
        return $this->invoke("POST", $url, $request);
    }

    public function put($url, Request $request) {
        return $this->invoke("PUT", $url, $request);
    }

    public function delete($url, $param = []) {
        return $this->invoke("DELETE", self::appendQueryString($url, $param), Request::createEmpty());
    }

    public function invoke($method, $url, Request $request) {
        $this->getEvent()->invokeOnRequest($request);
        $request->getEvent()->invokeOnRequest($request);

        list($c, $handshakeVerboseStream) = $this->createCurlRequest($method, $url, $request);

        $responseHeader = [];
        curl_setopt($c, CURLOPT_HEADERFUNCTION, function ($c, $header) use (&$responseHeader) {
            if ($pos = strpos($header, ":")) {
                $name = substr($header, 0, $pos);
                $responseHeader[] = [$name, trim(substr($header, $pos + 1))];
            }
            return strlen($header);
        });
        $response = curl_exec($c);
        $responseHeaderSize = curl_getinfo($c, CURLINFO_HEADER_SIZE);
        $responseBody = substr($response, $responseHeaderSize);
        curl_close($c);

        rewind($handshakeVerboseStream);
        $handshake = stream_get_contents($handshakeVerboseStream);
        fclose($handshakeVerboseStream);

        $response = new Response($request, $handshake, $responseHeader, $responseBody);

        $request->getEvent()->invokeOnResponse($response);
        $this->getEvent()->invokeOnResponse($response);

        return $responseBody;
    }

    public function getEvent() {
        return $this->event;
    }

    private function createCurlRequest($method, $url, Request $request) {
        $verbose = fopen('php://temp', 'w+');
        $c = curl_init();
        curl_setopt_array($c, [
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_VERBOSE => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_HEADER => true,
            CURLOPT_URL => $url,
            CURLOPT_STDERR => $verbose,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ]);

        $header = [
            "Expect:", // Expect: 100-Continue の送出を抑制
        ];

        if ($request !== null) {
            $content = $request->getContent();
            if (!empty($content)) {
                curl_setopt($c, CURLOPT_POSTFIELDS, $content);
            }
            foreach ($request->getHeaders() as $name => $value) {
                $header[] = "$name: $value";
            }
        }

        curl_setopt($c, CURLOPT_HTTPHEADER, $header);

        return [$c, $verbose];
    }

    public static function appendQueryString($url, $param = []) {
        if (!empty($param)) {
            if (strpos($url, "?") !== false) {
                if (substr($url, -1, 1) !== "?") {
                    $url .= "&";
                } else {
                    // no action
                }
            } else {
                $url .= "?";
            }
            $url .= http_build_query($param);
        }
        return $url;
    }
}