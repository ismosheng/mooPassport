<?php

declare(strict_types=1);

namespace app\common\support;

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
            'request_id' => RequestId::get($request),
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
            'request_id' => RequestId::get($request),
        ])->withStatus($status);
    }
}
