<?php
declare(strict_types=1);

namespace App\Component\HttpClient;

use App\Exception\HttpClientException;
use App\Listener\RunEnvListener;
use App\Logger\Log;
use Closure;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\Context\ApplicationContext;
use Hyperf\Codec\Json;
use Hyperf\Codec\Exception\InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function Hyperf\Support\env;

abstract class BaseHttpClient
{

    const CACHE_TTL = 60 * 15;

    const API_KEY = null;

    abstract protected function getHost($key = 'HOST'): string;

    public function getRawUrl(string $api, ?string $host_key = null,): string
    {
        $host = $host_key ? static::getHost($host_key) : static::getHost();
        return rtrim($host, '/') . '/' . ltrim($api, '/');
    }

    /**
     * @param string $api
     * @param array $data
     * @param Closure|null $result_call_back
     * @param string|null $host_key
     * @param null|string $cache_key
     * @param int|null $cache_ttl
     * @return mixed
     * @throws GuzzleException
     */
    public function get(
        string   $api,
        array    $data = [],
        ?Closure $result_call_back = null,
        ?string  $host_key = null,
        ?string  $cache_key = null,
        ?int     $cache_ttl = null
    ): mixed
    {
        $url = $this->getRawUrl($api, $host_key);

//        echo PHP_EOL;
//        echo $url . '?' . http_build_query($data);
//        echo PHP_EOL;
//        echo PHP_EOL;

        $cache_key && $cache_ttl === null && $cache_ttl = static::CACHE_TTL;
        return (new HttpClientComponent($url, $data))
            ->setCacheConfig($cache_key, $cache_ttl)
            ->setApiMethod(HttpClientComponent::API_METHOD_GET)
            ->getResult($result_call_back);
    }

    /**
     * @param string $api
     * @param array $data
     * @param Closure|null $result_call_back
     * @param string|null $host_key
     * @param string|null $cache_key
     * @param int|null $cache_ttl
     * @return mixed
     * @throws GuzzleException
     */
    public function formPost(
        string   $api,
        array    $data = [],
        ?Closure $result_call_back = null,
        ?string  $host_key = null,
        ?string  $cache_key = null,
        ?int     $cache_ttl = null
    ): mixed
    {
        $url = $this->getRawUrl($api, $host_key);

        $cache_key && $cache_ttl === null && $cache_ttl = static::CACHE_TTL;
        return (new HttpClientComponent($url, $data))
            ->setCacheConfig($cache_key, $cache_ttl)
            ->setApiMethod(HttpClientComponent::API_METHOD_POST)
            ->getResult($result_call_back);
    }

    /**
     * @param string $api
     * @param array $data
     * @param Closure|null $result_call_back
     * @param string|null $host_key
     * @param string|null $cache_key
     * @param int|null $cache_ttl
     * @return mixed
     * @throws GuzzleException
     */
    public function jsonPost(
        string   $api,
        array    $data = [],
        ?Closure $result_call_back = null,
        ?string  $host_key = null,
        ?string  $cache_key = null,
        ?int     $cache_ttl = null
    ): mixed
    {
        $url = $this->getRawUrl($api, $host_key);

        $cache_key && $cache_ttl === null && $cache_ttl = static::CACHE_TTL;
        return (new HttpClientComponent($url, $data))
            ->setCacheConfig($cache_key, $cache_ttl)
            ->setApiMethod(HttpClientComponent::API_METHOD_JSON_POST)
            ->getResult($result_call_back);
    }

    /**
     * 发送文件
     * @param string $api
     * @param string $file_path
     * @param string|null $key
     * @param string|null $host_key
     * @param string|null $file_type
     * @param array $post_data
     * @return mixed
     * @throws GuzzleException
     */
    public function uploadFile(
        string  $api,
        string  $file_path,
        ?string $key = null,
        ?string $host_key = null,
        ?string $file_type = null,
        array   $post_data = []
    ): mixed
    {
        $url = $this->getRawUrl($api, $host_key);

        return (new HttpClientComponent($url))
            ->setApiMethod(HttpClientComponent::API_METHOD_FILE_UPLOAD)
            ->setSendFile([$key => ['path' => $file_path, 'type' => $file_type]])
            ->setSendData($post_data)
            ->getResult();
    }

    /**
     * 多文件发送
     * @param string $api
     * @param array $send_file
     * @param string|null $host_key
     * @param array $post_data
     * @return mixed
     * @throws GuzzleException
     */
    public function uploadFileMultiple(
        string  $api,
        array   $send_file,
        ?string $host_key = null,
        array   $post_data = []
    ): mixed
    {
        $url = $this->getRawUrl($api, $host_key);

        return (new HttpClientComponent($url))
            ->setApiMethod(HttpClientComponent::API_METHOD_FILE_UPLOAD)
            ->setSendFile($send_file)->setSendData($post_data)
            ->getResult();
    }


    protected function buildSign($data): array
    {
        // todo generate sign
        $data['sign'] = 'xxx';
        return $data;
    }

    protected function handleResult($url, $request, $response)
    {
        try {
            $result = Json::decode($response);
        } catch (InvalidArgumentException $e) {
            Log::error('接口返回数据格式错误', ['api' => $url, 'request' => $request, 'response' => $response]);
            throw $e;
        }
        if ($result['code']) {
            Log::error('接口返回错误', ['api' => $url, 'request' => $request, 'response' => $result]);
            throw new HttpClientException('接口返回错误:' . ($result['msg'] ?? $result['message']));
        }
        return $result['data'];
    }

    /**
     * @param string $method
     * @param string $api
     * @param array $data
     * @param array $header
     * @param bool $to_obj
     * @return array|object|string|null
     * @throws GuzzleException
     */
    public function getResponse(string $method, string $api, array $data = [], array $header = [], bool $to_obj = true): array|object|string|null
    {
        $host = static::getHost();
        $url = rtrim($host, '/') . '/' . ltrim($api, '/');
        $method = match ($method) {
            default => HttpClientComponent::API_METHOD_GET,
            'JSON_POST' => HttpClientComponent::API_METHOD_JSON_POST,
            'POST' => HttpClientComponent::API_METHOD_POST,
            'PUT' => HttpClientComponent::API_METHOD_PUT
        };
        $token = '';
        if (!empty($token)) {
            $data['token'] = $token;
            $header['authorization'] = $token;
        }
        $data = $this->buildSign($data);
        $header['sign'] = $data['sign'];
        $header['app_id'] = $data['app_id'];
        $header['timestamp'] = $data['timestamp'];
        $result = (new HttpClientComponent($url, $data))
            ->setHeader($header)
            ->setHeader(['from' => env('APP_NAME')])
            ->setApiMethod($method)
            ->getResult();
        return $to_obj ? $this->handleResult($url, $data, $result) : $result;
    }
}
