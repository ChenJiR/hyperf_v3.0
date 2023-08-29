<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Event\OnHttpRequest;
use App\Kernel\Log\AppendRequestIdProcessor;
use Closure;
use Exception;
use Hyperf\Codec\Json;
use Hyperf\Config\Annotation\Value;
use Hyperf\Context\ApplicationContext;
use Psr\EventDispatcher\EventDispatcherInterface;
use Hyperf\Context\Context;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Inject;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function round, microtime, strtoupper, substr, md5, uniqid, gethostname;
use function Hyperf\Coroutine\co;
use function Hyperf\Coroutine\defer;

/**
 * RequestMiddleware
 * @package App\Middleware
 */
class RequestMiddleware implements MiddlewareInterface
{

    #[Inject]
    protected StdoutLoggerInterface $logger;

    #[Inject]
    protected ContainerInterface $container;

    #[Inject]
    protected ServerRequestInterface $request;

    #[Inject]
    protected EventDispatcherInterface $eventDispatcher;

    #[Value("requestLifecycle")]
    private array $request_lifecycle;


    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->requestFilter()) {
            return Context::get(ResponseInterface::class)->withStatus(404);
        }
        $this->eventDispatcher->dispatch(new OnHttpRequest($request));

        $this->logger->info("Request [{$request->getMethod()}] {$request->fullUrl()}");
        $request_start_time = microtime(true);
        Context::set('request_time', $request_start_time);

        $method = $this->request->getMethod();
        $inputdata = $this->request->all();
        $url = $this->request->path();
        co(
            function () use ($method, $inputdata, $url) {
                $this->beforeRequest($url, $method, $inputdata);
            }
        );

        $request_id = Context::get(AppendRequestIdProcessor::REQUEST_ID);
        $response = $handler->handle($request)->withHeader('qid', $request_id);

        defer(
            function () use ($method, $inputdata, $url, $response) {
                $this->afterRequest($url, $method, $inputdata, $response);
            }
        );

        $executionTime = round(microtime(true) - $request_start_time, 4);
        $this->logger->info("Response [{$request->getMethod()}] {$request->fullUrl()} status:{$response->getStatusCode()} time:{$executionTime}");

        return $response;
    }

    /**
     * request 过滤器
     * @return bool
     */
    private function requestFilter(): bool
    {
        $server_params = $this->request->getServerParams();
        if ($server_params['path_info'] === '/favicon.ico') {
            return false;
        }
        return true;
    }

    private function beforeRequest(string $url, string $method, array $inputdata = []): void
    {
        if (isset($this->request_lifecycle[$url])) {
            $before = $this->request_lifecycle[$url]['before'] ?? null;
            if ($before instanceof Closure || is_callable($before)) {
                $before($method, $inputdata, ApplicationContext::getContainer());
            }
        }
    }

    private function afterRequest(string $url, string $method, array $inputdata, ResponseInterface $response): void
    {
        if (isset($this->request_lifecycle[$url])) {
            $after = $this->request_lifecycle[$url]['after'] ?? null;
            if ($after instanceof Closure || is_callable($after)) {
                $data = $response->getBody()->getContents();
                $content_type = $response->getHeaderLine('content-type') ?: $response->getHeaderLine('Content-Type');
                try {
                    $response_data = match ($content_type) {
                        default => Json::decode($data),
                        'text/plain', 'application/octet-stream' => $data
                    };
                } catch (Exception) {
                    $response_data = $data;
                }
                $after($method, $inputdata, $response_data, ApplicationContext::getContainer());
            }
        }
    }

}