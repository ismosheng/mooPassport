# Moo Passport OAuth Demo

这个 Demo 展示第三方 Web 应用如何通过 Moo Passport 完成：

- 弹窗授权登录
- 页面跳转授权登录
- Authorization Code + PKCE S256
- 服务端换取 Token
- 调用 `/oauth/userinfo` 展示用户资料
- 撤销 Access Token 并退出 Demo 会话

浏览器不会接触 `AppSecret`、`Access Token` 或 `Refresh Token`。Demo 服务端使用内存
保存会话，因此重启后登录状态会自动清空，适合本地接入调试，不作为生产会话实现。

## 1. 创建测试应用

在 Moo Passport 管理后台创建一个“Web 应用”，登记回调地址：

```text
http://127.0.0.1:4174/callback
```

Demo 默认只申请 `openid profile`。需要测试实名数据时，先在应用后台勾选对应 Scope，
再把它加入 `.env` 的 `MOO_SCOPE`：

- `realname`：返回脱敏实名信息
- `realname_full`：返回完整实名信息，属于高敏感权限

## 2. 配置

复制 `.env.example` 为 `.env`，填写后台生成的 AppID 和 AppSecret：

```ini
MOO_CLIENT_ID=你的AppID
MOO_CLIENT_SECRET=你的AppSecret
MOO_CLIENT_AUTH_METHOD=client_secret_basic
```

`MOO_CLIENT_AUTH_METHOD` 必须与应用详情中的“认证方式”完全一致，可选
`client_secret_basic`、`client_secret_post` 或 `none`。后台创建的 Web 机密客户端默认
使用 `client_secret_basic`。如果创建的是公开客户端，则把认证方式改为 `none`，并让
`MOO_CLIENT_SECRET` 保持为空。修改 `DEMO_BASE_URL` 或 `DEMO_PORT` 后，管理后台登记的
回调地址必须同步修改并保持完全一致。

本地 Moo Passport 默认配置为：

```ini
MOO_PASSPORT_WEB_URL=http://127.0.0.1:3000
MOO_PASSPORT_API_URL=http://127.0.0.1:8787
```

浏览器侧请统一使用 `127.0.0.1` 访问 Demo 和通行证页面，不要混用
`localhost` 与 `127.0.0.1`。否则弹窗 iframe 会处于跨站上下文，浏览器可能不会回传
`SameSite=Lax` 登录 Cookie。

Demo 会在服务端校验最终 Token 的 Scope 必须与 `MOO_SCOPE` 完全一致。OAuth 标准允许
授权请求主动缩小 Scope，但本 Demo 是固定权限演示；如果手动修改 URL 中的 `scope`，
换 Token 会被拒绝，不会创建 Demo 登录会话。

## 3. 启动

先启动 Moo Passport 后端和前端，再启动 Demo：

```bash
cd demo
npm start
```

浏览器访问：

```text
http://127.0.0.1:4174
```

这个 Demo 只使用 Node.js 内置模块，不需要执行 `npm install`。
