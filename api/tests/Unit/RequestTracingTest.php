<?php

declare(strict_types=1);

namespace tests\Unit;

use app\common\middleware\RequestTracing;
use app\common\support\RequestId;
use PHPUnit\Framework\TestCase;
use Webman\Http\Request;

/** 验证请求 ID 在请求上下文、业务响应和响应头之间保持一致。 */
final class RequestTracingTest extends TestCase
{
    public function testGeneratesRequestIdWhenHeaderIsMissing(): void
    {
        $request = $this->request();
        $response = (new RequestTracing())->process($request, static fn () => response('ok'));

        $requestId = $request->context[RequestId::CONTEXT_KEY] ?? null;
        self::assertIsString($requestId);
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $requestId);
        self::assertSame($requestId, $response->getHeader('X-Request-ID'));
    }

    public function testPreservesValidIncomingRequestId(): void
    {
        $request = $this->request('demo-request-1234');
        $response = (new RequestTracing())->process($request, static fn () => response('ok'));

        self::assertSame('demo-request-1234', $request->context[RequestId::CONTEXT_KEY]);
        self::assertSame('demo-request-1234', $response->getHeader('X-Request-ID'));
    }

    public function testRejectsMalformedIncomingRequestId(): void
    {
        $request = $this->request("invalid\r\nheader");
        (new RequestTracing())->process($request, static fn () => response('ok'));

        self::assertNotSame("invalid\r\nheader", $request->context[RequestId::CONTEXT_KEY]);
    }

    private function request(?string $requestId = null): Request
    {
        $header = $requestId === null ? '' : "X-Request-ID: {$requestId}\r\n";

        return new Request("GET /health HTTP/1.1\r\nHost: localhost\r\n{$header}\r\n");
    }
}
