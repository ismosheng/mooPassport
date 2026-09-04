<?php

declare(strict_types=1);

namespace tests\Unit;

use app\common\infrastructure\crypto\SensitiveDataCipher;
use app\common\model\OAuthAccessToken;
use app\common\model\OAuthClient;
use app\common\model\User;
use app\common\service\UserSensitiveDataService;
use app\oauth\controller\UserInfoController;
use app\oauth\dto\AccessTokenIdentity;
use app\oauth\middleware\AuthenticateAccessToken;
use PHPUnit\Framework\TestCase;
use Webman\Http\Request;

/** 验证 UserInfo 仅按用户明确授权的 Scope 返回实名 Claims。 */
final class UserInfoControllerTest extends TestCase
{
    private UserSensitiveDataService $sensitiveData;
    private User $user;

    protected function setUp(): void
    {
        $this->sensitiveData = new UserSensitiveDataService(
            new SensitiveDataCipher(base64_encode(str_repeat('u', 32))),
        );
        $this->user = new User();
        $this->user->public_id = '01JTESTUSER000000000000004';
        $this->user->username = 'userinfo-test';
        $this->user->display_name = 'UserInfo Test';
        $this->user->email = 'userinfo@example.invalid';
        $this->user->identity_document_type = 'id_card';
        $this->user->realname_status = 'verified';
        $this->user->real_name_encrypted = $this->sensitiveData->encryptRealName('张三', $this->user);
        $this->user->identity_document_number_encrypted = $this->sensitiveData->encryptDocumentNumber(
            '11010120000102123X',
            $this->user,
        );
    }

    public function testOmitsRealnameClaimsWithoutRealnameScope(): void
    {
        $claims = $this->claims(['openid', 'profile']);

        self::assertSame($this->user->public_id, $claims['sub']);
        self::assertSame(
            rtrim((string) config('oauth.issuer'), '/') . '/oauth/avatar/default?label=U',
            $claims['picture'],
        );
        self::assertArrayNotHasKey('real_name', $claims);
        self::assertArrayNotHasKey('identity_document_number', $claims);
        self::assertArrayNotHasKey('realname_verified', $claims);
    }

    public function testReturnsCustomPictureForProfileScope(): void
    {
        $this->user->avatar_url = 'https://cdn.example.invalid/avatar.webp';

        $claims = $this->claims(['openid', 'profile']);

        self::assertSame('https://cdn.example.invalid/avatar.webp', $claims['picture']);
    }

    public function testReturnsMaskedClaimsForRealnameScope(): void
    {
        $claims = $this->claims(['openid', 'realname']);

        self::assertSame('张*', $claims['real_name']);
        self::assertSame('id_card', $claims['identity_document_type']);
        self::assertSame('110***********123X', $claims['identity_document_number']);
        self::assertTrue($claims['realname_verified']);
    }

    public function testFullScopeTakesPrecedenceOverMaskedScope(): void
    {
        $claims = $this->claims(['openid', 'realname', 'realname_full']);

        self::assertSame('张三', $claims['real_name']);
        self::assertSame('11010120000102123X', $claims['identity_document_number']);
    }

    /** @param list<string> $scopes
     *  @return array<string, mixed>
     */
    private function claims(array $scopes): array
    {
        $request = new Request("GET /oauth/userinfo HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->context[AuthenticateAccessToken::CONTEXT_KEY] = new AccessTokenIdentity(
            new OAuthAccessToken(),
            new OAuthClient(),
            $this->user,
            $scopes,
        );

        $response = (new UserInfoController($this->sensitiveData))->get($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeader('Cache-Control'));

        /** @var array<string, mixed> $claims */
        $claims = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);

        return $claims;
    }
}
