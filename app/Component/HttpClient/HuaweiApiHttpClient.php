<?php

namespace App\Component\HttpClient;


use App\Component\HttpClient\HuaweiCloud\HWSDKRequest;
use App\Component\HttpClient\HuaweiCloud\Signer;
use App\Exception\HttpClientException;
use App\Logger\Log;
use GuzzleHttp\Exception\GuzzleException;

class HuaweiApiHttpClient
{

    /**
     * 逆地理编码解析服务
     * 经纬度 => 地址
     * @param string|null $lng
     * @param string|null $lat
     * @return array|null
     * @throws HttpClientException
     */
    public function geoToLocationObj(?string $lng = null, ?string $lat = null): ?array
    {
        if (!$lat || !$lng) return null;
        $url = "jmregeoquery.apistore.huaweicloud.com/geocode/regeo/query?location=$lng,$lat";

        try {
            $response = $this->getResponse(HttpClientComponent::API_METHOD_POST, $url);
        } catch (GuzzleException) {
            Log::warning('hwapi 逆地理编码解析服务 http请求错误！', ['request_data' => ['lng' => $lng, 'lat' => $lat], 'url' => $url]);
            throw new HttpClientException('hwapi 逆地理编码解析服务 http请求错误！');
        }
        $request = json_decode($response, true);
        if (isset($request['code']) && $request['code'] == 200 && !empty($request['data']['regeocodes'] ?? null)) {
            return $request['data']['regeocodes'][0];
        } else {
            Log::warning('逆地理编码解析错误', ['request_data' => ['lng' => $lng, 'lat' => $lat], 'url' => $url, 'response' => $request], 'hwapiClient');
            return null;
        }
    }


    /**
     * 短链接生成
     * @param string $target
     * @return array
     */
    public function shortLink(string $target): array
    {
        $url = "shortlinkcreate.apistore.huaweicloud.com/shortlink/create";
        $data = ['target' => $target];
        $url = $url . '?' . http_build_query($data);

        try {
            $response = $this->getResponse(HttpClientComponent::API_METHOD_POST, $url);
        } catch (GuzzleException) {
            Log::warning('hwapi 短链接生成服务 http请求错误！', ['request_data' => ['target' => $target], 'url' => $url]);
            throw new HttpClientException('hwapi 短链接生成服务 http请求错误！');
        }
        $response = json_decode($response, true);
        if (isset($response['code']) && $response['code'] == 200 && !empty($response['data']['link'] ?? null)) {
            return $response;
        } else {
            Log::warning('hwapi 短链接生成服务 http请求错误！', ['request_data' => ['target' => $target], 'url' => $url, 'response' => $response], 'hwapiClient');
            throw new HttpClientException('hwapi 短链接生成服务 http请求错误！');
        }
    }

    /**
     * @param string $method
     * @param string $api
     * @param array $data
     * @param array $header
     * @return object|string
     * @throws GuzzleException
     */
    public function getResponse(string $method, string $api, array $data = [], array $header = []): object|string
    {
        $key = 'ed455fd4ab894bb1bdcae3af23f99e58';
        $secret = '9fa606c2bd3f488cbeaddf5a8b4419dc';

        $signer = new Signer();
        $signer->setKey($key);
        $signer->setSecret($secret);

        switch ($method) {
            case HttpClientComponent::API_METHOD_POST:
            case HttpClientComponent::API_METHOD_JSON_POST:
                $method = HttpClientComponent::API_METHOD_POST;
                $header['content-type'] = 'application/json';
                break;
            default:
            case HttpClientComponent::API_METHOD_GET:
                if (!empty($data)) {
                    $url_info = parse_url($api);
                    $mark = (isset($url_info['query']) && !empty($url_info['query'])) ? '&' : '?';
                    $api = $api . $mark . http_build_query($data);
                    $data = [];
                }
                break;
        }

        $req = new HWSDKRequest($method, $api, $header, empty($data) ? null : json_encode($data));
        [$url, $headers, $method, $body] = $signer->Sign($req);

        return (new HttpClientComponent($url))
            ->setHeader($headers)->setApiMethod($method)
            ->setSendData($data ?? [])
            ->getResult();
    }
}