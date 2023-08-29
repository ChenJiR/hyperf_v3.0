<?php

namespace App\Event;

use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;

class OnHttpRequest
{
    protected ServerRequestInterface $request;

    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }
}