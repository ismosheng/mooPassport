# Moo Passport repository rules

后端贡献者和编码代理必须遵循 `api/docs/DEVELOPMENT.md`。前端位于 `web`，使用
Vue 3、JavaScript、Vite、Naive UI、Pinia 和 Vue Router，不引入 Tailwind CSS。
前端视觉实现必须遵循 `web/docs/DESIGN_SYSTEM.md`，页面不得绕过全局 Token
自行定义品牌色、基础字号或圆角体系。

Hard requirements:

- Target PHP 8.4 and add `declare(strict_types=1);` to new PHP files.
- Define HTTP routes with PHP Attributes. Never use `#[Any]`.
- Keep Webman's default routes disabled.
- Controllers may call validators and services, but never models, repositories,
  Redis, or SQL directly.
- Controllers are reused by Webman and must be stateless. Never store request or
  user-specific data on controller instances.
- Services own business workflows and transaction boundaries.
- Services access persistence through repository interfaces.
- Application-specific services live in that application; only genuinely shared
  services belong in `app/common/service`.
- MySQL-backed models live in `app/common/model` and contain mapping,
  relationships, casts, and query scopes only.
- OAuth and OpenID Connect endpoints must return their standard protocol
  response shapes; never wrap them in the business API response envelope.
- Never store or log plaintext passwords, client secrets, authorization codes,
  access tokens, refresh tokens, MFA secrets, or private signing keys.
- Do not enable the implicit or resource-owner-password grants.
- Interactive authorization must use Authorization Code with PKCE S256.
- Add useful class-level documentation and explain security invariants, complex
  transactions, concurrency, and compatibility decisions. Comments must explain
  why and must be updated or removed when behavior changes.
- Follow the creation, maintenance, deprecation, and removal rules in the code
  lifecycle section of `docs/DEVELOPMENT.md`.
