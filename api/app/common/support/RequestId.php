<?php

declare(strict_types=1);

namespace app\common\support;

use Symfony\Component\Uid\Ulid;
use Webman\Http\Request;

/** 为单次 HTTP 请求提供经过校验且可贯穿日志、审计和响应的追踪标识。 */
final class RequestId
{
    public const CONTEXT_KEY = 'request_id';

    public static function initialize(Request $request): string
    {
        $existing = $request->context[self::CONTEXT_KEY] ?? null;
        if (is_string($existing) && self::isValid($existing)) {
            return $existing;
        }

        $incoming = $request->header('X-Request-ID');
        $requestId = is_string($incoming) && self::isValid($incoming)
            ? $incoming
            : (string) new Ulid();
        $request->context[self::CONTEXT_KEY] = $requestId;

        return $requestId;
    }

    public static function get(Request $request): string
    {
        return self::initialize($request);
    }

    private static function isValid(string $requestId): bool
    {
        return preg_match('/^[A-Za-z0-9._-]{8,100}$/D', $requestId) === 1;
    }
}
