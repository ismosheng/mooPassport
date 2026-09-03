<?php

declare(strict_types=1);

namespace app\common\middleware;

use app\common\support\RequestId;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/** 为动态请求建立稳定的请求 ID，并在响应头中返回给调用方。 */
final class RequestTracing implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $requestId = RequestId::initialize($request);
        $response = $handler($request);
        $response->header('X-Request-ID', $requestId);

        return $response;
    }
}
