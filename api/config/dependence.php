<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

declare(strict_types=1);

use app\common\repository\contract\AccessTokenRepositoryInterface;
use app\common\repository\contract\ApplicationRepositoryInterface;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\AuditArchiveRepositoryInterface;
use app\common\repository\contract\AuthorizationCodeRepositoryInterface;
use app\common\repository\contract\EmailVerificationTokenRepositoryInterface;
use app\common\repository\contract\LoginAttemptRepositoryInterface;
use app\common\repository\contract\OAuthClientRepositoryInterface;
use app\common\repository\contract\OAuthClientManagementRepositoryInterface;
use app\common\repository\contract\OAuthClientRedirectUriRepositoryInterface;
use app\common\repository\contract\OAuthClientSecretRepositoryInterface;
use app\common\repository\contract\OAuthConsentRepositoryInterface;
use app\common\repository\contract\OAuthPushedAuthorizationRequestRepositoryInterface;
use app\common\repository\contract\OAuthScopeRepositoryInterface;
use app\common\repository\contract\OAuthSigningKeyRepositoryInterface;
use app\common\repository\contract\RefreshTokenRepositoryInterface;
use app\common\repository\contract\PasswordResetTokenRepositoryInterface;
use app\common\repository\contract\UserRepositoryInterface;
use app\common\repository\contract\UserSessionRepositoryInterface;
use app\common\repository\contract\SystemSettingReaderInterface;
use app\common\repository\contract\RoleRepositoryInterface;
use app\common\repository\eloquent\AccessTokenRepository;
use app\common\repository\eloquent\ApplicationRepository;
use app\common\repository\eloquent\AuditLogRepository;
use app\common\repository\eloquent\AuditArchiveRepository;
use app\common\service\AuditArchiveService;
use app\common\repository\eloquent\AuthorizationCodeRepository;
use app\common\repository\eloquent\EmailVerificationTokenRepository;
use app\common\repository\eloquent\LoginAttemptRepository;
use app\common\repository\eloquent\OAuthClientRepository;
use app\common\repository\eloquent\OAuthClientManagementRepository;
use app\common\repository\eloquent\OAuthClientRedirectUriRepository;
use app\common\repository\eloquent\OAuthClientSecretRepository;
use app\common\repository\eloquent\OAuthConsentRepository;
use app\common\repository\eloquent\OAuthPushedAuthorizationRequestRepository;
use app\common\repository\eloquent\OAuthScopeRepository;
use app\common\repository\eloquent\OAuthSigningKeyRepository;
use app\common\repository\eloquent\RefreshTokenRepository;
use app\common\repository\eloquent\PasswordResetTokenRepository;
use app\common\repository\eloquent\UserRepository;
use app\common\repository\eloquent\UserSessionRepository;
use app\common\repository\eloquent\SystemSettingReader;
use app\common\repository\eloquent\RoleRepository;
use app\common\service\RoleService;
use app\admin\middleware\RequirePermission;
use app\admin\controller\DashboardController;
use app\admin\controller\ApplicationController;
use app\admin\controller\ApplicationAssetController;
use app\admin\controller\UserController;
use app\admin\controller\AuditLogController;
use app\admin\controller\RoleController;
use app\admin\repository\contract\AuditLogQueryRepositoryInterface;
use app\admin\repository\eloquent\AuditLogQueryRepository;
use app\admin\service\AuditLogQueryService;
use app\admin\repository\contract\UserManagementRepositoryInterface;
use app\admin\repository\eloquent\UserManagementRepository;
use app\admin\service\UserManagementService;
use app\admin\repository\contract\RoleManagementRepositoryInterface;
use app\admin\repository\eloquent\RoleManagementRepository;
use app\admin\service\RoleManagementService;
use app\admin\repository\contract\DashboardRepositoryInterface;
use app\admin\repository\eloquent\DashboardRepository;
use app\admin\repository\contract\SystemSettingsRepositoryInterface;
use app\admin\repository\eloquent\SystemSettingsRepository;
use app\admin\service\DashboardService;
use app\admin\service\ApplicationManagementService;
use app\admin\service\ApplicationLogoService;
use app\admin\service\SystemSettingsService;
use app\admin\controller\SystemSettingsController;
use app\common\support\IpAddress;
use app\common\support\PasswordHasher;
use app\common\support\SecureToken;
use app\common\infrastructure\mail\MailSenderInterface;
use app\common\infrastructure\mail\SymfonyMailSender;
use app\common\infrastructure\storage\ConfiguredObjectStorage;
use app\common\infrastructure\storage\ObjectStorageInterface;
use app\common\infrastructure\database\DatabaseTransactionManager;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\passport\controller\AuthController;
use app\passport\controller\EmailVerificationController;
use app\passport\controller\AccountController;
use app\passport\controller\ConsentController;
use app\passport\controller\SessionController;
use app\passport\controller\PublicSiteController;
use app\passport\middleware\AuthenticateSession;
use app\passport\middleware\ResolveSession;
use app\passport\service\ConsentManagementService;
use app\passport\service\EmailVerificationService;
use app\passport\service\ProfileService;
use app\passport\service\ProfileAvatarService;
use app\passport\service\SessionAuthenticationService;
use app\passport\service\SessionManagementService;
use app\passport\service\PublicSiteService;
use app\oauth\service\OAuthClientValidationService;
use app\oauth\service\AuthorizationService;
use app\oauth\controller\AuthorizationController;
use app\oauth\controller\PushedAuthorizationController;
use app\oauth\service\TokenService;
use app\oauth\controller\TokenController;
use app\oauth\service\AccessTokenAuthenticationService;
use app\oauth\middleware\AuthenticateAccessToken;
use app\oauth\controller\UserInfoController;
use app\oauth\service\TokenRevocationService;
use app\oauth\support\ClientCredentialsParser;
use app\oauth\controller\TokenRevocationController;
use app\oauth\service\TokenIntrospectionService;
use app\oauth\controller\TokenIntrospectionController;
use app\oauth\service\OidcMetadataService;
use app\oauth\service\JwksService;
use app\oauth\controller\OidcMetadataController;
use app\common\infrastructure\crypto\PrivateKeyCipher;
use app\common\infrastructure\crypto\SensitiveDataCipher;
use app\common\service\UserSensitiveDataService;
use app\oauth\service\SigningKeyService;
use app\oauth\service\IdTokenService;
use app\common\service\OAuthClientManagementService;
use app\admin\controller\OAuthClientController;
use app\passport\service\PasswordService;
use app\passport\controller\PasswordController;
use app\passport\service\LoginService;
use app\passport\service\RegisterService;
use app\passport\service\MailTemplateService;
use Psr\Container\ContainerInterface;

return [
    TransactionManagerInterface::class => static fn (): TransactionManagerInterface => new DatabaseTransactionManager(),
    UserRepositoryInterface::class => static fn (): UserRepositoryInterface => new UserRepository(),
    ApplicationRepositoryInterface::class => static fn (): ApplicationRepositoryInterface => new ApplicationRepository(),
    UserSessionRepositoryInterface::class => static fn (): UserSessionRepositoryInterface => new UserSessionRepository(),
    LoginAttemptRepositoryInterface::class => static fn (): LoginAttemptRepositoryInterface => new LoginAttemptRepository(),
    OAuthClientRepositoryInterface::class => static fn (): OAuthClientRepositoryInterface => new OAuthClientRepository(),
    OAuthClientManagementRepositoryInterface::class => static fn (): OAuthClientManagementRepositoryInterface => new OAuthClientManagementRepository(),
    RoleRepositoryInterface::class => static fn (): RoleRepositoryInterface => new RoleRepository(),
    RoleService::class => static fn (ContainerInterface $container): RoleService => new RoleService(
        $container->get(RoleRepositoryInterface::class),
    ),
    RequirePermission::class => static fn (ContainerInterface $container): RequirePermission => new RequirePermission(
        $container->get(RoleService::class),
    ),
    DashboardRepositoryInterface::class => static fn (): DashboardRepositoryInterface => new DashboardRepository(),
    DashboardService::class => static fn (ContainerInterface $container): DashboardService => new DashboardService(
        $container->get(DashboardRepositoryInterface::class),
    ),
    DashboardController::class => static fn (ContainerInterface $container): DashboardController => new DashboardController(
        $container->get(DashboardService::class),
        $container->get(RoleService::class),
    ),
    ApplicationManagementService::class => static fn (ContainerInterface $container): ApplicationManagementService => new ApplicationManagementService(
        $container->get(ApplicationRepositoryInterface::class),
        $container->get(OAuthClientManagementRepositoryInterface::class),
        $container->get(OAuthClientManagementService::class),
        $container->get(OAuthClientRepositoryInterface::class),
        $container->get(AccessTokenRepositoryInterface::class),
        $container->get(RefreshTokenRepositoryInterface::class),
        $container->get(AuthorizationCodeRepositoryInterface::class),
        $container->get(OAuthPushedAuthorizationRequestRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        $container->get(TransactionManagerInterface::class),
    ),
    SystemSettingsRepositoryInterface::class => static fn (): SystemSettingsRepositoryInterface => new SystemSettingsRepository(),
    SystemSettingReaderInterface::class => static fn (): SystemSettingReaderInterface => new SystemSettingReader(),
    SystemSettingsService::class => static fn (ContainerInterface $container): SystemSettingsService => new SystemSettingsService(
        $container->get(SystemSettingsRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        $container->get(TransactionManagerInterface::class),
    ),
    SystemSettingsController::class => static fn (ContainerInterface $container): SystemSettingsController => new SystemSettingsController($container->get(SystemSettingsService::class)),
    PublicSiteService::class => static fn (ContainerInterface $container): PublicSiteService => new PublicSiteService(
        $container->get(SystemSettingReaderInterface::class),
    ),
    PublicSiteController::class => static fn (ContainerInterface $container): PublicSiteController => new PublicSiteController(
        $container->get(PublicSiteService::class),
    ),
    ApplicationController::class => static fn (ContainerInterface $container): ApplicationController => new ApplicationController(
        $container->get(ApplicationManagementService::class),
    ),
    UserManagementRepositoryInterface::class => static fn (): UserManagementRepositoryInterface => new UserManagementRepository(),
    UserManagementService::class => static fn (ContainerInterface $container): UserManagementService => new UserManagementService(
        $container->get(UserManagementRepositoryInterface::class),
        $container->get(UserSessionRepositoryInterface::class),
        $container->get(AccessTokenRepositoryInterface::class),
        $container->get(RefreshTokenRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        $container->get(TransactionManagerInterface::class),
    ),
    UserController::class => static fn (ContainerInterface $container): UserController => new UserController(
        $container->get(UserManagementService::class),
    ),
    AuditLogQueryRepositoryInterface::class => static fn (): AuditLogQueryRepositoryInterface => new AuditLogQueryRepository(),
    AuditLogQueryService::class => static fn (ContainerInterface $container): AuditLogQueryService => new AuditLogQueryService(
        $container->get(AuditLogQueryRepositoryInterface::class),
    ),
    AuditLogController::class => static fn (ContainerInterface $container): AuditLogController => new AuditLogController(
        $container->get(AuditLogQueryService::class),
    ),
    RoleManagementRepositoryInterface::class => static fn (): RoleManagementRepositoryInterface => new RoleManagementRepository(),
    RoleManagementService::class => static fn (ContainerInterface $container): RoleManagementService => new RoleManagementService(
        $container->get(RoleManagementRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        $container->get(TransactionManagerInterface::class),
    ),
    RoleController::class => static fn (ContainerInterface $container): RoleController => new RoleController(
        $container->get(RoleManagementService::class),
    ),
    OAuthClientRedirectUriRepositoryInterface::class => static fn (): OAuthClientRedirectUriRepositoryInterface => new OAuthClientRedirectUriRepository(),
    OAuthClientSecretRepositoryInterface::class => static fn (): OAuthClientSecretRepositoryInterface => new OAuthClientSecretRepository(),
    OAuthScopeRepositoryInterface::class => static fn (): OAuthScopeRepositoryInterface => new OAuthScopeRepository(),
    OAuthSigningKeyRepositoryInterface::class => static fn (): OAuthSigningKeyRepositoryInterface => new OAuthSigningKeyRepository(),
    PrivateKeyCipher::class => static fn (): PrivateKeyCipher => new PrivateKeyCipher(
        (string) config('oauth.private_key_encryption_key'),
    ),
    SensitiveDataCipher::class => static fn (): SensitiveDataCipher => new SensitiveDataCipher(
        (string) config('oauth.user_data_encryption_key'),
    ),
    UserSensitiveDataService::class => static fn (ContainerInterface $container): UserSensitiveDataService => new UserSensitiveDataService(
        $container->get(SensitiveDataCipher::class),
    ),
    SigningKeyService::class => static fn (ContainerInterface $container): SigningKeyService => new SigningKeyService(
        $container->get(OAuthSigningKeyRepositoryInterface::class),
        $container->get(PrivateKeyCipher::class),
    ),
    IdTokenService::class => static fn (ContainerInterface $container): IdTokenService => new IdTokenService(
        $container->get(SigningKeyService::class),
    ),
    OAuthClientManagementService::class => static fn (ContainerInterface $container): OAuthClientManagementService => new OAuthClientManagementService(
        $container->get(OAuthClientRepositoryInterface::class),
        $container->get(OAuthClientManagementRepositoryInterface::class),
        $container->get(OAuthScopeRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        $container->get(AccessTokenRepositoryInterface::class),
        $container->get(RefreshTokenRepositoryInterface::class),
        $container->get(AuthorizationCodeRepositoryInterface::class),
        $container->get(OAuthPushedAuthorizationRequestRepositoryInterface::class),
        new SecureToken(),
        new PasswordHasher(),
        $container->get(TransactionManagerInterface::class),
    ),
    OAuthConsentRepositoryInterface::class => static fn (): OAuthConsentRepositoryInterface => new OAuthConsentRepository(),
    OAuthPushedAuthorizationRequestRepositoryInterface::class => static fn (): OAuthPushedAuthorizationRequestRepositoryInterface => new OAuthPushedAuthorizationRequestRepository(),
    AuthorizationCodeRepositoryInterface::class => static fn (): AuthorizationCodeRepositoryInterface => new AuthorizationCodeRepository(),
    EmailVerificationTokenRepositoryInterface::class => static fn (): EmailVerificationTokenRepositoryInterface => new EmailVerificationTokenRepository(),
    AccessTokenRepositoryInterface::class => static fn (): AccessTokenRepositoryInterface => new AccessTokenRepository(),
    RefreshTokenRepositoryInterface::class => static fn (): RefreshTokenRepositoryInterface => new RefreshTokenRepository(),
    PasswordResetTokenRepositoryInterface::class => static fn (): PasswordResetTokenRepositoryInterface => new PasswordResetTokenRepository(),
    AuditLogRepositoryInterface::class => static fn (): AuditLogRepositoryInterface => new AuditLogRepository(),
    AuditArchiveRepositoryInterface::class => static fn (): AuditArchiveRepositoryInterface => new AuditArchiveRepository(),
    AuditArchiveService::class => static fn (ContainerInterface $container): AuditArchiveService => new AuditArchiveService(
        $container->get(AuditArchiveRepositoryInterface::class),
    ),
    MailSenderInterface::class => static fn (): MailSenderInterface => new SymfonyMailSender(
        (string) config('mail.dsn'),
        (string) config('mail.from_address'),
        (string) config('mail.from_name'),
    ),
    ObjectStorageInterface::class => static fn (ContainerInterface $container): ObjectStorageInterface => new ConfiguredObjectStorage(
        $container->get(SystemSettingReaderInterface::class),
    ),
    ApplicationLogoService::class => static fn (ContainerInterface $container): ApplicationLogoService => new ApplicationLogoService(
        $container->get(ObjectStorageInterface::class),
    ),
    ApplicationAssetController::class => static fn (ContainerInterface $container): ApplicationAssetController => new ApplicationAssetController(
        $container->get(ApplicationLogoService::class),
    ),
    MailTemplateService::class => static fn (ContainerInterface $container): MailTemplateService => new MailTemplateService(
        $container->get(SystemSettingReaderInterface::class),
        (string) config('mail.verification_url'),
        (string) config('app.name'),
    ),
    EmailVerificationService::class => static fn (ContainerInterface $container): EmailVerificationService => new EmailVerificationService(
        $container->get(UserRepositoryInterface::class),
        $container->get(EmailVerificationTokenRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        $container->get(MailSenderInterface::class),
        new SecureToken(),
        $container->get(MailTemplateService::class),
        $container->get(TransactionManagerInterface::class),
    ),
    PasswordService::class => static fn (ContainerInterface $container): PasswordService => new PasswordService(
        $container->get(UserRepositoryInterface::class),
        $container->get(PasswordResetTokenRepositoryInterface::class),
        $container->get(UserSessionRepositoryInterface::class),
        $container->get(AccessTokenRepositoryInterface::class),
        $container->get(RefreshTokenRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        $container->get(MailSenderInterface::class),
        new SecureToken(),
        new PasswordHasher(),
        new IpAddress(),
        $container->get(MailTemplateService::class),
        $container->get(TransactionManagerInterface::class),
    ),
    SessionAuthenticationService::class => static fn (ContainerInterface $container): SessionAuthenticationService => new SessionAuthenticationService(
        $container->get(UserSessionRepositoryInterface::class),
        $container->get(UserRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        new SecureToken(),
        new IpAddress(),
    ),
    SessionManagementService::class => static fn (ContainerInterface $container): SessionManagementService => new SessionManagementService(
        $container->get(UserSessionRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        new IpAddress(),
    ),
    ProfileService::class => static fn (ContainerInterface $container): ProfileService => new ProfileService(
        $container->get(UserRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        new IpAddress(),
        $container->get(UserSensitiveDataService::class),
    ),
    ProfileAvatarService::class => static fn (ContainerInterface $container): ProfileAvatarService => new ProfileAvatarService(
        $container->get(UserRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        new IpAddress(),
        $container->get(ObjectStorageInterface::class),
    ),
    ConsentManagementService::class => static fn (ContainerInterface $container): ConsentManagementService => new ConsentManagementService(
        $container->get(OAuthConsentRepositoryInterface::class),
        $container->get(OAuthClientRepositoryInterface::class),
        $container->get(AccessTokenRepositoryInterface::class),
        $container->get(RefreshTokenRepositoryInterface::class),
        $container->get(AuthorizationCodeRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        new IpAddress(),
        $container->get(TransactionManagerInterface::class),
    ),
    OAuthClientValidationService::class => static fn (ContainerInterface $container): OAuthClientValidationService => new OAuthClientValidationService(
        $container->get(OAuthClientRepositoryInterface::class),
        $container->get(OAuthClientRedirectUriRepositoryInterface::class),
        $container->get(OAuthClientSecretRepositoryInterface::class),
    ),
    AuthorizationService::class => static fn (ContainerInterface $container): AuthorizationService => new AuthorizationService(
        $container->get(OAuthClientValidationService::class),
        $container->get(OAuthScopeRepositoryInterface::class),
        $container->get(OAuthConsentRepositoryInterface::class),
        $container->get(OAuthPushedAuthorizationRequestRepositoryInterface::class),
        $container->get(AuthorizationCodeRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        new SecureToken(),
        new IpAddress(),
        $container->get(TransactionManagerInterface::class),
    ),
    TokenService::class => static fn (ContainerInterface $container): TokenService => new TokenService(
        $container->get(OAuthClientValidationService::class),
        $container->get(AuthorizationCodeRepositoryInterface::class),
        $container->get(AccessTokenRepositoryInterface::class),
        $container->get(RefreshTokenRepositoryInterface::class),
        $container->get(OAuthClientManagementRepositoryInterface::class),
        $container->get(UserRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        new SecureToken(),
        new IpAddress(),
        $container->get(IdTokenService::class),
        $container->get(TransactionManagerInterface::class),
    ),
    ClientCredentialsParser::class => static fn (): ClientCredentialsParser => new ClientCredentialsParser(),
    TokenRevocationService::class => static fn (ContainerInterface $container): TokenRevocationService => new TokenRevocationService(
        $container->get(OAuthClientValidationService::class),
        $container->get(AccessTokenRepositoryInterface::class),
        $container->get(RefreshTokenRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        new SecureToken(),
        new IpAddress(),
        $container->get(TransactionManagerInterface::class),
    ),
    TokenIntrospectionService::class => static fn (ContainerInterface $container): TokenIntrospectionService => new TokenIntrospectionService(
        $container->get(OAuthClientValidationService::class),
        $container->get(AccessTokenRepositoryInterface::class),
        $container->get(RefreshTokenRepositoryInterface::class),
        $container->get(UserRepositoryInterface::class),
        new SecureToken(),
    ),
    OidcMetadataService::class => static fn (ContainerInterface $container): OidcMetadataService => new OidcMetadataService(
        $container->get(OAuthScopeRepositoryInterface::class),
    ),
    JwksService::class => static fn (ContainerInterface $container): JwksService => new JwksService(
        $container->get(OAuthSigningKeyRepositoryInterface::class),
    ),
    AccessTokenAuthenticationService::class => static fn (ContainerInterface $container): AccessTokenAuthenticationService => new AccessTokenAuthenticationService(
        $container->get(AccessTokenRepositoryInterface::class),
        $container->get(OAuthClientRepositoryInterface::class),
        $container->get(UserRepositoryInterface::class),
        new SecureToken(),
    ),
    AuthenticateAccessToken::class => static fn (ContainerInterface $container): AuthenticateAccessToken => new AuthenticateAccessToken(
        $container->get(AccessTokenAuthenticationService::class),
    ),
    AuthenticateSession::class => static fn (ContainerInterface $container): AuthenticateSession => new AuthenticateSession(
        $container->get(SessionAuthenticationService::class),
    ),
    ResolveSession::class => static fn (ContainerInterface $container): ResolveSession => new ResolveSession(
        $container->get(SessionAuthenticationService::class),
    ),
    RegisterService::class => static fn (ContainerInterface $container): RegisterService => new RegisterService(
        $container->get(UserRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        new PasswordHasher(),
        new IpAddress(),
        $container->get(EmailVerificationService::class),
        $container->get(TransactionManagerInterface::class),
    ),
    LoginService::class => static fn (ContainerInterface $container): LoginService => new LoginService(
        $container->get(UserRepositoryInterface::class),
        $container->get(UserSessionRepositoryInterface::class),
        $container->get(LoginAttemptRepositoryInterface::class),
        $container->get(AuditLogRepositoryInterface::class),
        new PasswordHasher(),
        new SecureToken(),
        new IpAddress(),
        $container->get(TransactionManagerInterface::class),
    ),
    AuthController::class => static fn (ContainerInterface $container): AuthController => new AuthController(
        $container->get(RegisterService::class),
        $container->get(LoginService::class),
    ),
    EmailVerificationController::class => static fn (ContainerInterface $container): EmailVerificationController => new EmailVerificationController(
        $container->get(EmailVerificationService::class),
    ),
    AccountController::class => static fn (ContainerInterface $container): AccountController => new AccountController(
        $container->get(SessionAuthenticationService::class),
        $container->get(ProfileService::class),
        $container->get(ProfileAvatarService::class),
        $container->get(RoleService::class),
        $container->get(UserSensitiveDataService::class),
    ),
    ConsentController::class => static fn (ContainerInterface $container): ConsentController => new ConsentController(
        $container->get(ConsentManagementService::class),
    ),
    SessionController::class => static fn (ContainerInterface $container): SessionController => new SessionController(
        $container->get(SessionManagementService::class),
    ),
    AuthorizationController::class => static fn (ContainerInterface $container): AuthorizationController => new AuthorizationController(
        $container->get(AuthorizationService::class),
    ),
    PushedAuthorizationController::class => static fn (ContainerInterface $container): PushedAuthorizationController => new PushedAuthorizationController(
        $container->get(AuthorizationService::class),
        $container->get(ClientCredentialsParser::class),
    ),
    TokenController::class => static fn (ContainerInterface $container): TokenController => new TokenController(
        $container->get(TokenService::class),
        $container->get(ClientCredentialsParser::class),
    ),
    UserInfoController::class => static fn (ContainerInterface $container): UserInfoController => new UserInfoController(
        $container->get(UserSensitiveDataService::class),
    ),
    TokenRevocationController::class => static fn (ContainerInterface $container): TokenRevocationController => new TokenRevocationController(
        $container->get(TokenRevocationService::class),
        $container->get(ClientCredentialsParser::class),
    ),
    TokenIntrospectionController::class => static fn (ContainerInterface $container): TokenIntrospectionController => new TokenIntrospectionController(
        $container->get(TokenIntrospectionService::class),
        $container->get(ClientCredentialsParser::class),
    ),
    OidcMetadataController::class => static fn (ContainerInterface $container): OidcMetadataController => new OidcMetadataController(
        $container->get(OidcMetadataService::class),
        $container->get(JwksService::class),
    ),
    OAuthClientController::class => static fn (ContainerInterface $container): OAuthClientController => new OAuthClientController(
        $container->get(OAuthClientManagementService::class),
    ),
    PasswordController::class => static fn (ContainerInterface $container): PasswordController => new PasswordController(
        $container->get(PasswordService::class),
    ),
];
