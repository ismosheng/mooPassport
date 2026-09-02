<?php

declare(strict_types=1);

namespace app\common\exception;

use app\common\support\ApiResponse;
use support\exception\Handler as WebmanHandler;
use support\validation\ValidationException;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * 将账号及业务异常转换为稳定的 API 错误响应结构。
 *
 * OAuth/OIDC 协议错误由对应协议控制器生成，不使用普通业务响应结构。
 */
final class Handler extends WebmanHandler
{
    /** @var list<class-string<Throwable>> */
    public $dontReport = [
        BusinessException::class,
        ValidationException::class,
    ];

    public function render(Request $request, Throwable $exception): Response
    {
        if ($exception instanceof BusinessException) {
            return ApiResponse::error(
                $request,
                $exception->errorCode,
                $exception->getMessage(),
                $exception->httpStatus,
            );
        }

        if ($exception instanceof ValidationException) {
            return ApiResponse::error(
                $request,
                'validation_failed',
                $exception->getMessage(),
                422,
            );
        }

        if ($this->isBusinessApi($request)) {
            return ApiResponse::error(
                $request,
                'internal_error',
                config('app.debug') ? $exception->getMessage() : '服务器内部错误。',
                500,
            );
        }

        return parent::render($request, $exception);
    }

    private function isBusinessApi(Request $request): bool
    {
        $path = '/' . ltrim($request->path(), '/');

        return str_starts_with($path, '/passport/')
            || str_starts_with($path, '/api/')
            || str_starts_with($path, '/admin/');
    }
}
