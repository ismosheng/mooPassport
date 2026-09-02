<?php

declare(strict_types=1);

namespace app\common\support;

use Symfony\Component\Uid\Ulid;
use Webman\Http\Request;
use Webman\Http\Response;

/** 构建非协议类业务接口使用的稳定 JSON 响应结构。 */
final class ApiResponse
{
    public static function success(Request $request, mixed $data = null, int $status = 200): Response
    {
        return json([
            'code' => 0,
            'message' => 'success',
            'data' => $data,
            'request_id' => self::requestId($request),
        ])->withStatus($status);
    }

    public static function error(
        Request $request,
        string $code,
        string $message,
        int $status,
        mixed $data = null,
    ): Response {
        return json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'request_id' => self::requestId($request),
        ])->withStatus($status);
    }

    private static function requestId(Request $request): string
    {
        $requestId = $request->header('X-Request-ID');
        if (is_string($requestId) && preg_match('/^[A-Za-z0-9._-]{8,100}$/', $requestId) === 1) {
            return $requestId;
        }

        return (string) new Ulid();
    }
}
