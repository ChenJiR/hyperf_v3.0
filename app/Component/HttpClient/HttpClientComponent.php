<?php
declare(strict_types=1);

namespace App\Component\HttpClient;

use App\Component\Cache\RedisCache;
use App\Exception\HttpClientException;
use App\Exception\SystemErrorException;
use App\Logger\Log;
use Closure;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Guzzle\ClientFactory;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class HttpClientComponent
{
    const API_METHOD_POST = 'POST';
    const API_METHOD_GET = 'GET';
    const API_METHOD_PUT = 'PUT';
    const API_METHOD_JSON_POST = 'JSON_POST';
    const API_METHOD_FILE_UPLOAD = 'FILE_UPLOAD';

    /**
     * @var null|Client
     */
    private ?Client $httpClient = null;


    #[Inject]
    private RedisCache $redis;


    #[Inject]
    protected ClientFactory $clientFactory;

    private string $url;

    /**
     * @var array
     */
    private array $send_data = [];

    /**
     * @var array
     * demo: [
     *    'file1' => '/var/demo.png',
     *     or
     *    'file2' => [ 'path'=>'/var/demo.png','type'=>'application/octet-stream' ]
     * ]
     */
    private array $send_file = [];

    /**
     * @var string | null
     */
    private ?string $cache_key = null;
    private ?int $cache_time = 60 * 15; //默认缓存15分钟
    private string $api_method = self::API_METHOD_JSON_POST;
    private array $header = [];

    private mixed $result;

    public function __construct($url, $send_data = [], $cache_key = null)
    {
        $this->url = $url;
        $this->cache_key = $cache_key;
        $this->send_data = $send_data;
    }

    public function newClient(array $option = []): Client
    {
        return $this->clientFactory->create($option);
    }

    /**
     * @param string $api_method
     * @return self
     */
    public function setApiMethod(string $api_method): self
    {
        $this->api_method = strtoupper($api_method);
        return $this;
    }

    /**
     * @param $send_data
     * @return self
     */
    public function setSendData($send_data): self
    {
        $this->send_data = $send_data;
        return $this;
    }

    /**
     * @param array $send_file
     * @return $this
     * @throws Exception
     */
    public function setSendFile(array $send_file): self
    {
        foreach ($send_file as $k => $file) {
            if (
                empty($file) ||
                (is_array($file) && empty($file['path']))
            ) {
                throw new Exception('上传文件错误，非法数据格式');
            }
        }
        $this->send_file = $send_file;
        return $this;
    }

    /**
     * @param string|null $cache_key
     * @param int|string|null $cache_time
     * @return self
     */
    public function setCacheConfig(string $cache_key = null, int|string|null $cache_time = null): self
    {
        $cache_key && $this->cache_key = $cache_key;
        $cache_time && $this->cache_time = intval($cache_time);
        return $this;
    }

    /**
     * @param $header
     * @return $this
     */
    public function setHeader($header): self
    {
        foreach ($header as $k => &$value) {
            is_array($value) && $value = implode(',', $value);
        }
        $this->header = array_merge($this->header, $header);
        return $this;
    }

    /**
     * @param array $option
     * @return string
     * @throws GuzzleException
     */
    protected function API_Send(array $option = [])
    {
        $this->httpClient = $this->newClient($option);

        $request_option = empty($this->header) ? [] : ['headers' => $this->header];
        if (
            (isset($this->header['Content-Type']) && in_array($this->header['Content-Type'], ['application/json;charset=UTF-8', 'application/json'])) ||
            (isset($this->header['content-type']) && in_array($this->header['content-type'], ['application/json;charset=UTF-8', 'application/json']))
        ) {
            $this->api_method = self::API_METHOD_JSON_POST;
        }
        try {
            switch ($this->api_method) {
                case self::API_METHOD_GET:
                    if (!empty($this->send_data)) {
                        $url_info = parse_url($this->url);
                        $mark = (isset($url_info['query']) && !empty($url_info['query'])) ? '&' : '?';
                        $this->url = $this->url . $mark . http_build_query($this->send_data);
                    }
                    $response = $this->httpClient->get($this->url, $request_option);
                    break;
                case self::API_METHOD_POST:
                    $request_option['form_params'] = $this->send_data;
                    $response = $this->httpClient->post($this->url, $request_option);
                    break;
                case self::API_METHOD_FILE_UPLOAD:
                    $response = $this->sendFile();
                    break;
                case self::API_METHOD_JSON_POST:
                default:
                    $this->header['charset'] = 'utf-8';
                    !empty($this->send_data) && $request_option['json'] = $this->send_data;
                    $response = $this->httpClient->post($this->url, $request_option);
                    break;
                case self::API_METHOD_PUT:
                    $url_info = parse_url($this->url);
                    $mark = (isset($url_info['query']) && !empty($url_info['query'])) ? '&' : '?';
                    $this->url = $this->url . $mark . http_build_query($this->send_data);
                    $request_option['form_params'] = $this->send_data;
                    $response = $this->httpClient->put($this->url, $request_option);
                    break;
            }
        } catch (RequestException $e) {
            $response = $e->getResponse();
        }
        if ($response->getStatusCode() == 200) {
            return $response->getBody()->getContents();
        } else {
            throw new HttpClientException(
                $this->url . ' http返回错误',
                [
                    'data' => $this->send_data,
                    'header' => $this->header,
                    'response' => $response->getBody(),
                    'code' => $response->getStatusCode(),
                    'raw' => $response
                ]
            );
        }
    }

    /**
     * 发送文件
     * @return ResponseInterface
     * @throws SystemErrorException
     * @throws GuzzleException
     */
    private function sendFile(): ResponseInterface
    {
        if (empty($this->send_file)) {
            throw new SystemErrorException();
        }
        $url_info = parse_url($this->url);
        $mark = (isset($url_info['query']) && !empty($url_info['query'])) ? '&' : '?';
        $this->url = $this->url . $mark . http_build_query($this->send_data);

        $file_content = [];
        foreach ($this->send_file as $k => $each_file) {
            if (empty($each_file)) {
                continue;
            }
            $file_path = is_array($each_file) ? $each_file['path'] : $each_file;
            $file_name = is_array($each_file) ? ($each_file['name'] ?? 'file_' . uniqid()) : 'file_' . uniqid();
            $file_content[] = [
                'name' => $file_name,
                'contents' => fopen($file_path, 'r')
            ];
        }
        return $this->httpClient->request('POST', $this->url, ['multipart' => $file_content]);
    }

    private function getCache(): bool
    {
        return $this->cache_key ? $this->redis->get(strval($this->cache_key)) : false;
    }

    private function setCache(): bool
    {
        return !$this->cache_key || $this->redis->set(strval($this->cache_key), $this->result, intval($this->cache_time));
    }

    /**
     * @param Closure|null $result_call_back
     * @return mixed
     * @throws GuzzleException
     */
    public function getResult(?Closure $result_call_back = null): mixed
    {
        $this->result = $this->cache_key ? $this->getCache() : [];
        if (!is_array($this->result) || empty($this->result)) {
            $this->result = $this->API_Send();
            if ($result_call_back instanceof Closure) {
                $this->result = $result_call_back($this->result);
            }
            if ($this->result != false) $this->setCache();
        }
        return $this->result;
    }

    public function setCacheTTL(int $cache_time = 0): bool
    {
        return !$this->cache_key || $this->redis->expire(strval($this->cache_key), intval($cache_time ?: $this->cache_time));
    }
}