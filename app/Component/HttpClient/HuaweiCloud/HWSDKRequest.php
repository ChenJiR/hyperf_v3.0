<?php

namespace App\Component\HttpClient\HuaweiCloud;

use Exception;

class HWSDKRequest
{
    public $method = 'GET';
    public $scheme = 'http';
    public $host = '';
    public $uri = '';
    public $query = [];
    public $headers = [];
    public $body = '';

    function __construct(?string $method = 'GET', ?string $url = null, ?array $headers = null, $body = '')
    {
        $method && $this->method = $method;

        if ($url != NULL) {
            $spl = explode("://", $url, 2);
            $scheme = 'http';
            if (count($spl) > 1) {
                $scheme = $spl[0];
                $url = $spl[1];
            }
            $spl = explode("?", $url, 2);
            $url = $spl[0];
            $query = array();
            if (count($spl) > 1) {
                foreach (explode("&", $spl[1]) as $kv) {
                    $spl = explode("=", $kv, 2);
                    $key = $spl[0];
                    if (count($spl) == 1) {
                        $value = "";
                    } else {
                        $value = $spl[1];
                    }
                    if ($key != "") {
                        $key = urldecode($key);
                        $value = urldecode($value);
                        if (array_key_exists($key, $query)) {
                            array_push($query[$key], $value);
                        } else {
                            $query[$key] = array($value);
                        }
                    }
                }
            }
            $spl = explode("/", $url, 2);
            $host = $spl[0];
            if (count($spl) == 1) {
                $url = "/";
            } else {
                $url = "/" . $spl[1];
            }
            $this->scheme = $scheme;
            $this->host = $host;
            $this->uri = urldecode($url);
            $this->query = $query;
        }
        if ($headers != NULL) {
            $this->headers = $headers;
        }
        if ($body != NULL) {
            $this->body = $body;
        }
    }

}
