<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace App\Controller;

use App\Constants\ResponseCode;
use App\Exception\BaseException;
use App\Exception\SystemErrorException;
use App\Util\MyLengthAwarePaginator;
use Closure;
use Hyperf\Contract\LengthAwarePaginatorInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;

abstract class AbstractController
{
    #[Inject]
    protected ContainerInterface $container;

    #[Inject]
    protected RequestInterface $request;

    #[Inject]
    protected ResponseInterface $response;

    /**
     * 获取接口输入信息
     * @param string|array|null $key
     * @param mixed|null $default
     * @return mixed
     */
    protected function input(string|array|null $key = null, mixed $default = null): mixed
    {
        return match (gettype($key)) {
            'NULL' => $this->request->all(),
            'array' => $this->request->inputs($key, $default),
            'string' => $this->request->input($key, $default)
        };
    }

    /**
     * 获取上传文件t输入信息
     * @param string|null $key
     * @param mixed|null $default
     * @return mixed
     */
    protected function file(?string $key = null, mixed $default = null): mixed
    {
        return $this->request->file($key, $default);
    }

    /**
     * 获取post输入信息
     * @param string|null $key
     * @param mixed|null $default
     * @return mixed
     */
    protected function post(?string $key = null, mixed $default = null): mixed
    {
        return $this->request->post($key, $default);
    }

    /**
     * 获取get输入信息
     * @param string|null $key
     * @param mixed|null $default
     * @return mixed
     */
    protected function get(?string $key = null, mixed $default = null): mixed
    {
        return $this->request->query($key, $default);
    }

    /**
     * 返回成功结果
     * @param mixed $data
     * @param string|null $msg
     * @return array
     */
    protected function successResponse(mixed $data = [], ?string $msg = null): array
    {
        return [
            'code' => ResponseCode::SUCCESS,
            'msg' => $msg ?? ResponseCode::getMessage(ResponseCode::SUCCESS),
            'data' => $data
        ];
    }

    /**
     * 返回错误Response
     * @param int|string $code
     * @param string|null $msg
     * @return array
     */
    protected function errorResponse(int|string $code = ResponseCode::BUSINESS_ERROR, ?string $msg = null): array
    {
        return [
            'code' => $code,
            'msg' => $msg ?? ResponseCode::getMessage($code)
        ];
    }

    /**
     * 返回错误Response
     * @param string|null $msg
     * @return array
     */
    protected function errorResponseMsg(?string $msg = null): array
    {
        return $this->errorResponse(ResponseCode::BUSINESS_ERROR, $msg);
    }

    /**
     * 返回抛出错误Response
     * @param BaseException $e
     * @return array
     */
    protected function exceptionResponse(BaseException $e): array
    {
        return $this->errorResponse($e->responseCode(), $e->getMessage());
    }

    /**
     * 返回未登录Response
     * @return array
     */
    protected function notLoginResponse(): array
    {
        return $this->errorResponse(ResponseCode::NOT_LOGIN, '请登录');
    }

    protected function queryPage($query, ?Closure $item_handle = null, array $columns = ['*'], bool $response = true, string $page_key = 'page', string $pagesize_key = 'size'): array|LengthAwarePaginatorInterface
    {
        $pagesize = intval($this->input($pagesize_key, 20));

        if (
            $query instanceof \Hyperf\Database\Model\Builder ||
            $query instanceof \Hyperf\Database\Query\Builder
        ) {
            $paginate = $query->paginate($pagesize, $columns, $page_key);
            return $response ? $this->pageResponse($paginate, $item_handle) : $paginate;
        } else {
            throw new SystemErrorException('分页错误');
        }
    }

    protected function pageResponse(MyLengthAwarePaginator $paginate, ?Closure $item_handle = null): array
    {
        return $this->successResponse([
            'returnData' => $paginate->each($item_handle)->items(),
            'currentPage' => $paginate->currentPage(),
            'size' => $paginate->perPage(),
            'count' => $paginate->total(),
            'totalPage' => $paginate->lastPage()
        ]);
    }

    protected function pageResponseByData(array $data, ?Closure $item_handle = null, string $page_key = 'page', string $pagesize_key = 'size'): array
    {
        $page = intval($this->input($page_key, 1));
        $pagesize = intval($this->input($pagesize_key, 20));

        $total = count($data);
        $data = array_slice($data, ($page - 1) * $pagesize, $pagesize);

        if (!empty($item_handle)) {
            foreach ($data as $key => $item) {
                $data[$key] = $item_handle($item, $key);
            }
        }

        return $this->successResponse([
            'returnData' => $data,
            'currentPage' => $page,
            'size' => $pagesize,
            'count' => $total,
            'totalPage' => max((int)ceil($total / $pagesize), 1)
        ]);
    }

}
