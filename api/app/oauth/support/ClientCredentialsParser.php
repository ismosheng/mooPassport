<?php

declare(strict_types=1);

namespace app\oauth\support;

use app\common\enum\TokenEndpointAuthMethod;
use app\common\exception\OAuthProtocolException;
use app\oauth\dto\ClientCredentials;
use Webman\Http\Request;

/** 统一解析 Token 类端点使用的 Basic、Post 或公开客户端凭据。 */
final class ClientCredentialsParser
{
    /** @param array<string, mixed> $parameters */
    public function parse(Request $request, array $parameters): ClientCredentials
    {
        $authorization = $request->header('Authorization');
        if (is_string($authorization) && preg_match('/^Basic\s+/i', $authorization) === 1) {
            // 同时出现 Header 和表单凭据会产生解析歧义，必须直接拒绝。
            if (isset($parameters['client_id']) || isset($parameters['client_secret'])) {
                throw new OAuthProtocolException('invalid_request', '客户端认证方式不能混用。');
            }

            $encodedCredentials = preg_replace('/^Basic\s+/i', '', $authorization, 1);
            $decoded = is_string($encodedCredentials) ? base64_decode($encodedCredentials, true) : false;
            if ($decoded === false || !str_contains($decoded, ':')) {
                throw new OAuthProtocolException('invalid_client', '客户端认证失败。', 401);
            }
            [$clientId, $clientSecret] = explode(':', $decoded, 2);

            return new ClientCredentials(
                urldecode($clientId),
                urldecode($clientSecret),
                TokenEndpointAuthMethod::ClientSecretBasic,
            );
        }
        if (is_string($authorization) && $authorization !== '') {
            throw new OAuthProtocolException('invalid_client', '客户端认证失败。', 401);
        }

        $clientId = $parameters['client_id'] ?? null;
        if (!is_string($clientId) || $clientId === '' || strlen($clientId) > 512) {
            throw new OAuthProtocolException('invalid_request', '缺少或无效的 client_id 参数。');
        }

        $clientSecret = $parameters['client_secret'] ?? null;
        if ($clientSecret !== null && !is_string($clientSecret)) {
            throw new OAuthProtocolException('invalid_request', 'client_secret 参数格式无效。');
        }

        return new ClientCredentials(
            $clientId,
            $clientSecret,
            $clientSecret === null
                ? TokenEndpointAuthMethod::None
                : TokenEndpointAuthMethod::ClientSecretPost,
        );
    }
}
