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
├── demo/       # 弹窗与页面跳转 OAuth 接入示例
├── AGENTS.md   # 仓库编码与架构约束
└── README.md
```

后端开发规范见 [`api/docs/DEVELOPMENT.md`](api/docs/DEVELOPMENT.md)，前端目录和启动说明见 [`web/README.md`](web/README.md)，视觉约束见 [`web/docs/DESIGN_SYSTEM.md`](web/docs/DESIGN_SYSTEM.md)，第三方应用接入示例见 [`demo/README.md`](demo/README.md)。

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

安装器会创建数据库结构、初始权限和 Scope、首个超级管理员、OIDC 加密主密钥、用户实名资料加密主密钥及签名密钥。启动前请修改 `.env` 中的数据库、Redis、邮件和站点地址。两把加密主密钥都必须随 `.env` 安全备份；遗失 `USER_DATA_ENCRYPTION_KEY` 后，已保存的实名资料将无法恢复。

自动化安装可通过 `--admin-username`、`--admin-email` 和 `--admin-display-name` 提供管理员资料，并通过临时环境变量 `MOO_INSTALL_ADMIN_PASSWORD` 提供密码。管理员密码不会写入 `.env`。检查已有数据库结构时执行：

```bash
php install.php --check
```

安装器只用于空数据库，不会覆盖已有数据。数据库增量脚本位于 `api/database/migrations`，已有数据库升级时必须按文件名顺序执行。

从旧版本升级实名资料和 PAR 功能时，先备份数据库和 `.env`，按文件名顺序执行
`database/migrations/20260903_001_extend_user_profiles.sql` 和
`database/migrations/20260903_002_add_pushed_authorization_requests.sql`。同时为
`.env` 增加独立的 `USER_DATA_ENCRYPTION_KEY`（32 字节随机值的 Base64），最后重启
Webman。不要复用 `OIDC_PRIVATE_KEY_ENCRYPTION_KEY`，也不要在密钥生成后随意更换。

### 前端

```bash
cd web
npm install
npm run dev
```

开发环境默认前端地址为 `http://127.0.0.1:3000`，后端地址为 `http://127.0.0.1:8787`。

## 质量检查

```bash
cd api
composer check

cd ../web
npm run build
```

`composer check` 会执行 PHPStan、单元测试以及账号安全和 OAuth/OIDC 集成测试。

## 服务器部署

以前后端使用同一个域名 `id.niusir.com`，且后端内容直接上传到域名目录为例：

```text
/www/wwwroot/id.niusir.com/
├── app/
├── config/
├── public/       # 宝塔网站运行目录，同时存放前端构建产物
├── runtime/
├── vendor/
├── .env
└── start.php
```

在本地前端目录完成构建：

```bash
cd web
npm install
npm run build
```

将 `web/dist` 里面的文件合并上传到服务器的 `/www/wwwroot/id.niusir.com/public`。最终应存在：

```text
/www/wwwroot/id.niusir.com/public/index.html
/www/wwwroot/id.niusir.com/public/assets/
```

上传时不要整体删除 `public`，尤其不要删除运行后产生的 `public/uploads`。宝塔网站的运行目录设置为 `/www/wwwroot/id.niusir.com/public`，不能设置为后端项目根目录。

### 上传文件存储

用户头像等上传文件默认保存在后端的 `public/uploads`。使用本地存储时，需要让运行
Webman 的用户拥有该目录的写权限，并将目录纳入服务器备份。Nginx 继续使用上文的
`location ^~ /uploads/` 直接提供这些文件。

也可以在管理后台“系统设置 > 文件存储”切换到七牛云。先在后端项目根目录的 `.env`
配置七牛密钥；按本文示例部署时，该文件是
`/www/wwwroot/id.niusir.com/.env`：

```dotenv
QINIU_ACCESS_KEY=your-access-key
QINIU_SECRET_KEY=your-secret-key
```

AK 和 SK 属于敏感凭据，只从环境变量读取，不会写入数据库，也不会在后台接口中
返回。修改 `.env` 后必须重启 Webman。随后在后台填写 Bucket、访问域名和可选的
存储目录，再选择“七牛云存储”并保存。访问域名必须是完整的 HTTP 或 HTTPS 地址，
生产环境建议填写绑定到 Bucket 的 HTTPS CDN 域名，例如
`https://cdn.example.com`。

也可以通过 `.env` 提供初始默认值；后台保存的非敏感配置会覆盖这些默认值：

```dotenv
STORAGE_DRIVER=local
QINIU_BUCKET=
QINIU_DOMAIN=
QINIU_PREFIX=moo-passport
```

切换存储位置不会迁移历史文件，数据库中已保存的旧头像 URL 仍会继续使用。更换头像
时，新文件写入当前选定的存储，并尽力删除旧对象。因此不要在确认旧 URL 已不再使用
前关闭原存储或删除原 Bucket。七牛 Bucket 还应按实际业务配置备份或对象生命周期策略。

前端使用相对 API 地址，因此同域部署不需要前端 `.env`；Vite 的开发代理只在 `npm run dev` 时生效。

Nginx 配置需要让前端路由回退到 `index.html`，并把后端路径转发到 Webman：

```nginx
location ^~ /.well-known/ {
    proxy_set_header Host $http_host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_pass http://127.0.0.1:8789;
}

location ^~ /passport/ {
    proxy_set_header Host $http_host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_http_version 1.1;
    proxy_set_header Connection "";
    proxy_pass http://127.0.0.1:8789;
}

location ^~ /oauth/ {
    proxy_set_header Host $http_host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_http_version 1.1;
    proxy_set_header Connection "";
    proxy_pass http://127.0.0.1:8789;
}

location ^~ /admin/v1/ {
    proxy_set_header Host $http_host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_http_version 1.1;
    proxy_set_header Connection "";
    proxy_pass http://127.0.0.1:8789;
}

location ^~ /api/v1/ {
    proxy_set_header Host $http_host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_http_version 1.1;
    proxy_set_header Connection "";
    proxy_pass http://127.0.0.1:8789;
}

location ^~ /uploads/ {
    try_files $uri =404;
}

location / {
    try_files $uri $uri/ /index.html;
}

location ~ \.php$ {
    return 404;
}

location ~ /\. {
    return 404;
}
```

这些后端 `location` 必须与前端的 `location /` 同时存在。`proxy_pass` 后不要添加 `/`，否则可能改写并丢失原始接口路径。不要继续保留“所有不存在的文件都转发给 Webman”的通用规则，否则 `/login`、`/admin` 等 Vue Router 页面会被后端接管。

生产环境后端 `.env` 至少需要对应设置：

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://id.niusir.com
OAUTH_ISSUER=https://id.niusir.com
PASSPORT_WEB_URL=https://id.niusir.com
SESSION_SECURE=true
CORS_ALLOWED_ORIGINS=https://id.niusir.com
TRUSTED_PROXIES=127.0.0.1,::1
HTTP_LISTEN=http://0.0.0.0:8789
```

`OAUTH_ISSUER` 决定 Discovery 元数据和 ID Token 的 `iss`，必须填写外部 HTTPS
地址，不能填写 Webman 内网监听地址。`PASSPORT_WEB_URL` 是未保存后台设置时的邮件
链接回退地址；部署后还应在“系统设置 > 基础设置”将“公开访问地址”设为
`https://id.niusir.com`。邮件发件人地址由 `MAIL_FROM_ADDRESS` 控制，例如
`no-reply@niusir.com`；SMTP 密码等敏感信息只允许保存在 `.env`。

同机 Nginx 反向代理时必须信任环回地址 `127.0.0.1,::1`，否则服务端会按安全
策略忽略 `X-Forwarded-For`，登录会话中只能看到 Nginx 的地址。若 Nginx 在容器、
负载均衡或另一台主机上，改填实际直连 Webman 的代理 IP/CIDR，不要填写任意公网段。

真实 IP 只在登录、创建会话和写入审计事件时保存，升级代码后不会改写数据库中的
历史 `127.0.0.1`。上传后必须彻底重启 Webman，再退出当前账号并重新登录，随后查看
新会话的 IP。可先用一个不涉及登录凭据的请求确认 Nginx 是否确实添加转发头：

```bash
curl -i http://127.0.0.1:8789/.well-known/openid-configuration \
  -H "Host: id.niusir.com" \
  -H "X-Forwarded-For: 203.0.113.10" \
  -H "X-Real-IP: 203.0.113.10"
```

该命令只验证服务和请求头可达；最终仍应从公网浏览器重新登录，并在“登录设备”或
审计日志中核对新记录。若 Webman 前面还有 CDN、容器网关或负载均衡，必须把直接
连接 Webman 的那一层代理地址加入 `TRUSTED_PROXIES`，否则系统会有意忽略转发头。

`AUTH_COOKIE_DOMAIN` 留空即可使用更安全的当前主机 Cookie；只有明确需要跨子域共享登录态时才设置 Cookie 域。

宝塔添加 Webman 服务时，启动命令使用：

```bash
php /www/wwwroot/id.niusir.com/start.php start
```

不要添加 `-d`，宝塔会负责进程守护。网站运行目录必须指向 `public`，不能暴露后端项目根目录。完整操作可参考 [Webman 官方宝塔安装指南](https://www.workerman.net/doc/webman/bt-install.html)。

### 启动失败与端口占用

先检查服务状态和日志：

```bash
php start.php status
tail -n 100 runtime/logs/webman.log
```

检查 8789 端口是否被占用：

```bash
ss -lntp | grep :8789
# 或
lsof -i :8789
```

如端口已被其他服务占用，可在服务器项目根目录的 `.env` 中修改监听端口：

```dotenv
HTTP_LISTEN=http://0.0.0.0:8790
```

修改后重启 Webman，并把 Nginx 中的 `proxy_pass http://127.0.0.1:8789` 同步改为新端口。也可以直接修改 `config/process.php`，但生产环境推荐通过 `HTTP_LISTEN` 配置，避免升级代码时产生冲突。

### API 请求返回 405

如果登录页面可以打开，但 `POST /passport/v1/login` 返回 Nginx 的 `405 Not Allowed`，说明 API 请求落入了前端静态文件规则，Nginx 正在尝试用 `index.html` 响应 POST。确认已经配置上文的 `location ^~ /passport/`，并且它代理到 Webman 实际监听端口。

先绕过 Nginx 测试 Webman：

```bash
curl -i -X POST http://127.0.0.1:8789/passport/v1/login \
  -H "Content-Type: application/json" \
  -d '{}'
```

再测试域名转发：

```bash
curl -i -X POST https://id.niusir.com/passport/v1/login \
  -H "Content-Type: application/json" \
  -d '{}'
```

两次请求都应返回后端 JSON 响应，而不是 Nginx HTML 错误页。修改 Nginx 后先执行 `nginx -t`，确认通过再重载配置。

### OAuth 端点检查

Discovery 中的 `token_endpoint`、`revocation_endpoint` 和
`introspection_endpoint` 是协议地址，不是浏览器页面。它们只接受
`application/x-www-form-urlencoded` 的 POST 请求；直接在浏览器地址栏打开会
返回 `405 Method Not Allowed`。这表示路由存在但请求方法不对，并非接口缺失。

不携带真实 Token 或 AppSecret 时，可用以下请求确认 Nginx 转发和路由已生效：

```bash
curl -i -X POST https://id.niusir.com/oauth/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data "grant_type=authorization_code"

curl -i -X POST https://id.niusir.com/oauth/revoke \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data "token=test"

curl -i -X POST https://id.niusir.com/oauth/introspect \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data "token=test"
```

这些探测请求因缺少合法客户端认证而返回 OAuth JSON 错误是正常的；只要不是
Nginx HTML 404，就说明请求已经到达 Webman。`/oauth/authorize` 还需要合法的
`client_id`、`redirect_uri`、`response_type=code`、Scope 和 PKCE 参数，不能把
裸地址当作可浏览页面测试。

## 生产安全

- 使用 HTTPS，并设置 `APP_ENV=production`、`APP_DEBUG=false` 和 `SESSION_SECURE=true`。
- 使用独立的 MySQL、Redis 和 SMTP 凭据，禁止保留示例密码。
- 设置随机且仅保存在服务端环境中的 `OIDC_PRIVATE_KEY_ENCRYPTION_KEY`。
- 设置并安全备份独立的 `USER_DATA_ENCRYPTION_KEY`，用于加密实名资料和生成证件去重摘要。
- 明确配置 `CORS_ALLOWED_ORIGINS`、`TRUSTED_PROXIES` 和 Cookie 域。
- 不记录密码、客户端密钥、授权码、Token、MFA 密钥或签名私钥。
- 上线前验证数据库备份、恢复流程和关键权限的 HTTP 403 边界。
- 使用 PAR 时先向 `/oauth/par` 推送完整授权请求，再仅将返回的 `request_uri` 交给 `/oauth/authorize`；不要在授权 URL 中重复提交 `scope`、`redirect_uri` 或 PKCE 参数。

## 许可证

本项目使用 MIT License，详见 [`LICENSE`](LICENSE)。
