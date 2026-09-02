# Moo Passport

Moo Passport 是一个基于 OAuth 2.1 和 OpenID Connect 的统一身份认证服务，提供账号中心、第三方应用授权、管理后台与安全审计能力。

> 项目目前处于发布前测试阶段。生产部署前请完成真实域名、HTTPS、邮件、数据库、Redis、OIDC 签名密钥和权限边界验收。

## 功能

- 用户注册、邮箱验证、登录和密码重置
- HttpOnly Cookie 会话与登录安全限制
- OAuth 2.1 Authorization Code + PKCE S256
- OpenID Connect Discovery、JWKS、ID Token 和 UserInfo
- Access Token、Refresh Token Rotation 与重放检测
- OAuth 应用、用户、角色和细粒度权限管理
- 管理操作与安全事件审计
- 在线白名单系统设置

## 技术栈

后端使用 PHP 8.4、Webman、MySQL 8、Redis 和 `league/oauth2-server`。前端使用 Vue 3、Vite、Naive UI、Pinia 和 Vue Router。

## 项目结构

```text
moo-passport/
├── api/        # Webman API、OAuth/OIDC 服务、数据库脚本和测试
├── web/        # Vue 账号中心、授权页面和管理后台
├── AGENTS.md   # 仓库编码与架构约束
└── README.md
```

后端开发规范见 [`api/docs/DEVELOPMENT.md`](api/docs/DEVELOPMENT.md)，前端目录和启动说明见 [`web/README.md`](web/README.md)，视觉约束见 [`web/docs/DESIGN_SYSTEM.md`](web/docs/DESIGN_SYSTEM.md)。

## 环境要求

- PHP 8.4
- Composer 2
- MySQL 8.0+
- Redis
- Node.js 与 npm

## 本地启动

### 后端

```bash
cd api
composer install
cp .env.example .env
```

完成 `.env` 配置后执行安装器：

```bash
php install.php
php start.php start
```

安装器会创建数据库结构、初始权限和 Scope、首个超级管理员、OIDC 加密主密钥及签名密钥。启动前请修改 `.env` 中的数据库、Redis、邮件和站点地址。

自动化安装可通过 `--admin-username`、`--admin-email` 和 `--admin-display-name` 提供管理员资料，并通过临时环境变量 `MOO_INSTALL_ADMIN_PASSWORD` 提供密码。管理员密码不会写入 `.env`。检查已有数据库结构时执行：

```bash
php install.php --check
```

安装器只用于空数据库，不会覆盖已有数据。数据库增量脚本位于 `api/database/migrations`，已有数据库升级时必须按文件名顺序执行。

### 前端

```bash
cd web
npm install
npm run dev
```

开发环境默认前端地址为 `http://localhost:3000`，后端地址为 `http://127.0.0.1:8787`。

## 质量检查

```bash
cd api
composer check

cd ../web
npm run build
```

`composer check` 会执行 PHPStan、单元测试以及账号安全和 OAuth/OIDC 集成测试。

## 生产安全

- 使用 HTTPS，并设置 `APP_ENV=production`、`APP_DEBUG=false` 和 `SESSION_SECURE=true`。
- 使用独立的 MySQL、Redis 和 SMTP 凭据，禁止保留示例密码。
- 设置随机且仅保存在服务端环境中的 `OIDC_PRIVATE_KEY_ENCRYPTION_KEY`。
- 明确配置 `CORS_ALLOWED_ORIGINS`、`TRUSTED_PROXIES` 和 Cookie 域。
- 不记录密码、客户端密钥、授权码、Token、MFA 密钥或签名私钥。
- 上线前验证数据库备份、恢复流程和关键权限的 HTTP 403 边界。

## 许可证

本项目使用 MIT License，详见 [`LICENSE`](LICENSE)。
