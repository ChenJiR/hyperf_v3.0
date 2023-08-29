<?php declare(strict_types=1);

namespace App\Routes;

use App\Exception\BusinessException;
use App\Util\Version;
use Closure;
use Exception;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\HttpMessage\Exception\NotFoundHttpException;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Response;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\HttpServer\Router\Router;
use Hyperf\Context\ApplicationContext;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionParameter;

abstract class RouteConfig
{

    const PREFIX = 'api';

    public static function routes(): void
    {
    }

    protected static function middlewares(): array
    {
        return [];
    }

    public static function loadRoutes()
    {
        static::addGroup(static::PREFIX, [static::class, 'routes'], ['middleware' => static::middlewares()]);
    }

    public static function addRoute(string|array $httpMethod, string $route, $handler, array $options = []): void
    {
        $stdout_log = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);

        $route = '/' . trim($route, '/');
        if (is_string($handler) || is_callable($handler)) {
            $is_version_route = false;
        } else if (is_array($handler)) {
            if (count($handler) == 2) {
                $keys = array_keys($handler);
                $is_version_route = !($keys == array_keys($keys));
            } else {
                $is_version_route = true;
            }
        } else {
            $stdout_log->error('路由配置错误:' . $route . ' 已跳过');
            return;
        }

        if ($is_version_route) {
            try {
                self::versionHandlerInit($handler);
                self::versionHandlerSort($handler);
            } catch (Exception) {
                $stdout_log->error('路由配置错误:' . $route . ' 已跳过');
                return;
            }
            //版本动态路由
            Router::addRoute(
                $httpMethod,
                $route,
                function (RequestInterface $request) use ($route, $handler, $options) {
                    // todo getVersion
                    $appVersion = '1.1.1';
                    //根据版本获取响应的handler
                    $version_handler = $handler['default'];
                    foreach ($handler as $version => $each_handler) {
                        if ($version == 'default') continue;

                        if (Version::compare($appVersion, $version) >= 0) {
                            $version_handler = $each_handler;
                            break;
                        }
                    }
                    try {
                        if (is_null($version_handler)) throw new NotFoundHttpException();
                        if (
                            isset($version_handler['method']) &&
                            !in_array($request->getMethod(), is_string($version_handler['method']) ? [$version_handler['method']] : $version_handler['method'])
                        ) {
                            throw new NotFoundHttpException();
                        }

                        $controller = $version_handler['controller'];
                        $function = $version_handler['function'];
                        $request_route_params = self::getHandlerParamsValue($request, $version_handler['params'] ?? []);
                    } catch (NotFoundHttpException) {
                        return ApplicationContext::getContainer()->get(Response::class)
                            ->withBody(new SwooleStream('Not Found'))
                            ->withStatus(404);
                    }

                    return is_null($controller)
                        ? $function(...$request_route_params)
                        : $controller->{$function}(...$request_route_params);
                },
                $options
            );
        } else {
            //正常路由
            Router::addRoute($httpMethod, $route, $handler, $options);
        }
    }

    /**
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    protected static function parseRouteHandlerContent(string|array|Closure $handler): array
    {
        $stdout_log = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
        $method = null;
        if (is_array($handler)) {
            if (isset($handler['handler'])) {
                $method = $handler['method'] ?? null;
                $handler = $handler['handler'];
            } else {
                if (count($handler) == 2) {
                    $keys = array_keys($handler);
                    if ($keys != array_keys($keys)) {
                        $stdout_log->error('路由配置错误:' . json_encode($handler));
                        throw new Exception();
                    }
                } else {
                    $stdout_log->error('路由配置错误:' . json_encode($handler));
                    throw new Exception();
                }
            }
        }
        if (is_string($handler)) {
            if (str_contains($handler, '::')) {
                $handler = explode('::', $handler);
            } elseif (str_contains($handler, '@')) {
                $handler = explode('@', $handler);
            } else {
                $stdout_log->error('路由配置错误:' . $handler);
                throw new Exception();
            }
        }
        return $handler instanceof Closure ? [null, $handler, $method] : array_merge($handler, [$method]);
    }

    /**
     * @param RequestInterface $request
     * @param ReflectionParameter[] $handler_params
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected static function getHandlerParamsValue(RequestInterface $request, array $handler_params = []): array
    {
        if (empty($handler_params)) return [];

        $stdout_log = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);

        $router = $request->getAttribute(Dispatched::class);
        $router_params = $router?->params ?? [];

        $request_route_params = [];
        foreach ($handler_params as $param) {
            $params_name = $param->getName();
            $params_type = $param->getType();

            if ($params_type?->isBuiltin() === false) {
                //指定参数类型 且 指定参数类型为指定类型 则去容器中找
                try {
                    $request_route_params[$params_name] = ApplicationContext::getContainer()->get($params_type->getName());
                } catch (Exception $e) {
                    $stdout_log->error('请求未找到方法指定的参数类型:' . $params_type);
                    throw new BusinessException(null, $e);
                }
            } else {
                try {
                    $params_value = $router_params[$params_name] ?? $param->getDefaultValue();
                } catch (Exception) {
                    $params_value = null;
                }
                if (is_null($params_value)) {
                    $stdout_log->error('请求缺失必须的参数:' . $params_name);
                    throw new BusinessException();
                }
                if (isset($params_type)) {
                    switch ($params_type->getName()) {
                        case 'string' :
                            $request_route_params[$params_name] = strval($params_value);
                            break;
                        case 'int' :
                            $request_route_params[$params_name] = intval($params_value);
                            break;
                        case 'bool' :
                            $request_route_params[$params_name] = boolval($params_value);
                            break;
                        default :
                            $stdout_log->error('参数无法转换为方法指定的参数类型:' . $params_type->getName());
                            throw new BusinessException();
                    }
                } else {
                    $request_route_params[$params_name] = strval($params_value);
                }
            }
        }
        return $request_route_params;
    }

    /**
     * @throws Exception
     */
    protected static function versionHandlerSort(array &$version_handler = [])
    {
        uksort(
            $version_handler,
            function ($k1, $k2) {
                if ($k1 == 'default') return 1;
                if ($k2 == 'default') return -1;

                return -Version::compare($k1, $k2);
            }
        );
    }

    /**
     * @throws Exception
     */
    protected static function versionHandlerInit(array &$version_handler = [])
    {
        foreach ($version_handler as &$item) {
            list($controller, $function, $method) = self::parseRouteHandlerContent($item);
            if (is_null($controller)) {
                $params = (new ReflectionFunction($function))->getParameters();
            } else {
                $controller = ApplicationContext::getContainer()->get($controller);
                $reflect = new ReflectionClass($controller::class);
                $params = $reflect->getMethod($function)?->getParameters() ?? [];
            }
            $item = [
                'controller' => $controller,
                'function' => $function,
                'method' => $method,
                'params' => $params
            ];
        }
    }

    public static function get(string $route, $handler, array $options = [])
    {
        static::addRoute('GET', $route, $handler, $options);
    }

    public static function post(string $route, $handler, array $options = [])
    {
        static::addRoute('POST', $route, $handler, $options);
    }

    public static function put(string $route, $handler, array $options = [])
    {
        static::addRoute('PUT', $route, $handler, $options);
    }

    public static function delete(string $route, $handler, array $options = [])
    {
        static::addRoute('DELETE', $route, $handler, $options);
    }

    public static function patch(string $route, $handler, array $options = [])
    {
        static::addRoute('PATCH', $route, $handler, $options);
    }

    public static function head(string $route, $handler, array $options = [])
    {
        static::addRoute('HEAD', $route, $handler, $options);
    }

    public static function addGroup($prefix, $callback, array $options = [])
    {
        $prefix = empty($prefix) ? $prefix : '/' . trim($prefix, '/');
        Router::addGroup($prefix, $callback, $options);
    }
}