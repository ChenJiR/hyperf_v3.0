<?php

namespace App\Component\HttpClient\HuaweiCloud;


use DateTimeZone;

class Signer
{
    private string $Key = 'ed455fd4ab894bb1bdcae3af23f99e58';
    private string $Secret = '9fa606c2bd3f488cbeaddf5a8b4419dc';

    /**
     * @param string $Key
     */
    public function setKey(string $Key)
    {
        $this->Key = $Key;
    }

    /**
     * @param string $Secret
     */
    public function setSecret(string $Secret)
    {
        $this->Secret = $Secret;
    }

    function escape($string): array|string
    {
        return str_replace(['+', "%7E"], ['%20', "~"], urlencode($string));
    }

    function findHeader(HWSDKRequest $r, $header): ?string
    {
        foreach ($r->headers as $key => $value) {
            if (!strcasecmp($key, $header)) {
                return $value;
            }
        }
        return NULL;
    }

// Build a CanonicalRequest from a regular request string
//
// CanonicalRequest =
//  HTTPRequestMethod + '\n' +
//  CanonicalURI + '\n' +
//  CanonicalQueryString + '\n' +
//  CanonicalHeaders + '\n' +
//  SignedHeaders + '\n' +
//  HexEncode(Hash(RequestPayload))
    function CanonicalRequest(HWSDKRequest $r, $signedHeaders): string
    {
        $CanonicalURI = $this->CanonicalURI($r);
        $CanonicalQueryString = $this->CanonicalQueryString($r);
        $canonicalHeaders = $this->CanonicalHeaders($r, $signedHeaders);
        $signedHeadersString = join(";", $signedHeaders);
        $hash = $this->findHeader($r, "X-Sdk-Content-Sha256");
        if (!$hash) {
            $hash = hash("sha256", $r->body);
        }
        return "$r->method\n$CanonicalURI\n$CanonicalQueryString\n$canonicalHeaders\n$signedHeadersString\n$hash";
    }

// CanonicalURI returns request uri
    function CanonicalURI(HWSDKRequest $r): string
    {
        $pattens = explode("/", $r->uri);
        $uri = array();
        foreach ($pattens as $v) {
            array_push($uri, $this->escape($v));
        }
        $urlpath = join("/", $uri);
        if (!str_ends_with($urlpath, "/")) {
            $urlpath = $urlpath . "/";
        }
        return $urlpath;
    }

// CanonicalQueryString
    function CanonicalQueryString(HWSDKRequest $r): string
    {
        $keys = array();
        foreach ($r->query as $key => $value) {
            array_push($keys, $key);
        }
        sort($keys);
        $a = array();
        foreach ($keys as $key) {
            $k = $this->escape($key);
            $value = $r->query[$key];
            if (is_array($value)) {
                sort($value);
                foreach ($value as $v) {
                    $kv = "$k=" . $this->escape($v);
                    array_push($a, $kv);
                }
            } else {
                $kv = "$k=" . $this->escape($value);
                array_push($a, $kv);
            }
        }
        return join("&", $a);
    }

// CanonicalHeaders
    function CanonicalHeaders(HWSDKRequest $r, $signedHeaders): string
    {
        $headers = array();
        foreach ($r->headers as $key => $value) {
            $headers[strtolower($key)] = trim($value);
        }
        $a = array();
        foreach ($signedHeaders as $key) {
            array_push($a, $key . ':' . $headers[$key]);
        }
        return join("\n", $a) . "\n";
    }

    function curlHeaders(HWSDKRequest $r): array
    {
        $header = [];
        foreach ($r->headers as $key => $value) {
            $header[strtolower($key)] = trim($value);
        }
        return $header;
    }

// SignedHeaders
    function SignedHeaders(HWSDKRequest $r): array
    {
        $a = [];
        foreach ($r->headers as $key => $value) {
            $a[] = strtolower($key);
        }
        sort($a);
        return $a;
    }

// Create a "String to Sign".
    function StringToSign($canonicalRequest, $t): string
    {
        $date = gmdate("Ymd\THis\Z", $t);
        $hash = hash("sha256", $canonicalRequest);
        return "SDK-HMAC-SHA256\n$date\n$hash";
    }

// Create the HWS Signature.
    function SignStringToSign($stringToSign, $signingKey): bool|string
    {
        return hash_hmac("sha256", $stringToSign, $signingKey);
    }

// Get the finalized value for the "Authorization" header. The signature parameter is the output from SignStringToSign
    function AuthHeaderValue($signature, $accessKey, $signedHeaders): string
    {
        $signedHeadersString = join(";", $signedHeaders);
        return "SDK-HMAC-SHA256 Access=$accessKey, SignedHeaders=$signedHeadersString, Signature=$signature";
    }

    public function Sign(HWSDKRequest $r): array
    {
        $date = $this->findHeader($r, "X-Sdk-Date");
        $t = isset($date) ? date_timestamp_get(date_create_from_format("Ymd\THis\Z", $date, new DateTimeZone('UTC'))) : null;
        if (!@$t) {
            $t = time();
            $r->headers["X-Sdk-Date"] = gmdate("Ymd\THis\Z", $t);
        }
        $queryString = $this->CanonicalQueryString($r);
        if ($queryString != "") {
            $queryString = "?" . $queryString;
        }
        $signedHeaders = $this->SignedHeaders($r);
        $canonicalRequest = $this->CanonicalRequest($r, $signedHeaders);
        $stringToSign = $this->StringToSign($canonicalRequest, $t);
        $signature = $this->SignStringToSign($stringToSign, $this->Secret);
        $authValue = $this->AuthHeaderValue($signature, $this->Key, $signedHeaders);
        $r->headers['Authorization'] = $authValue;

        $uri = str_replace(["%2F"], ["/"], rawurlencode($r->uri));
        $url = $r->scheme . '://' . $r->host . $uri . $queryString;
        $headers = $this->curlHeaders($r);

        return [$url, $headers, $r->method, $r->body];
    }
}