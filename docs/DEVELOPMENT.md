# 哞哞通行证开发规范

## 1. 技术基线

- PHP 8.4
- Webman 2.2
- MySQL 8.0+
- Redis
- `league/oauth2-server` 9.4
- PHP 代码遵循 PSR-12，新文件必须声明 `strict_types=1`
- 数据库、Token 和协议时间统一使用 UTC；展示层按用户时区转换

## 2. 应用与目录

```text
app/
├── passport/                 # 登录、授权和账号中心 Web 端
│   ├── controller/
│   ├── repository/           # 仅 passport 使用的特殊查询
│   ├── service/
│   └── validator/
├── api/                      # Bearer Token 保护的业务 API
│   ├── controller/
│   ├── repository/           # 仅 api 使用的特殊查询
│   ├── service/
│   └── validator/
├── admin/                    # 运营与应用管理端
│   ├── controller/
│   ├── repository/           # 仅 admin 使用的报表、聚合查询
│   ├── service/
│   └── validator/
└── common/                   # 三个应用共享的领域与基础设施代码
    ├── dto/
    ├── enum/
    ├── exception/
    ├── middleware/
    ├── model/
    ├── oauth/
    │   ├── entity/
    │   └── repository/
    ├── repository/
    │   ├── contract/
    │   ├── eloquent/
    │   └── redis/
    ├── process/
    ├── service/              # 两个以上应用共享的 Service
    └── support/
```

共享 MySQL Model 统一放在 `app/common/model`，命名空间为
`app\common\model`。`common` 只允许放跨应用共享的代码，禁止将无法分类的
代码随意放入其中。

## 3. 调用方向

```text
Route / Middleware
        ↓
Controller → Validator
        ↓
     Service
        ↓
Repository Contract
     ↙       ↘
Eloquent       Redis / 外部设施
Repository
     ↓
   Model
     ↓
   MySQL
```

### Controller

- 只处理 HTTP 输入、调用 Validator/Service、构造 HTTP 响应。
- 禁止直接调用 Model、Repository、Redis 或编写 SQL。
- 禁止生成 Token、发送邮件或实现事务。
- 方法保持简短，不承载业务分支。

### Validator

- 负责字段格式、长度、枚举值及请求级校验。
- 权限判断和业务状态判断属于 Service，不属于 Validator。

### Service

- 负责注册、登录、授权、Token 轮换、密钥轮换等完整业务流程。
- 负责事务边界以及协调多个 Repository。
- 不读取 `Request`，不返回 `Response`，使用 DTO/值对象传递数据。
- 内部单实现 Service 使用 `final` 具体类，不机械创建同名接口。
- 只服务一个应用的 Service 放在该应用目录；两个以上应用复用时才下沉到
  `common/service`。
- 禁止一个应用的 Service 直接调用另一个应用的 Service；共同能力必须下沉。

### Repository

- Service 通过 Repository Contract 访问持久化数据。
- Eloquent 实现放在 `repository/eloquent`。
- Repository 只负责查询和持久化，不决定业务流程。
- OAuth Repository 必须实现 `league/oauth2-server` 对应接口。
- 用户、客户端、Token 等核心 Repository 统一放在 `common/repository`。
- 单个应用独有的报表或聚合查询可放在该应用的 `repository` 中。

### Model

- 只包含表映射、字段 cast、关联关系和可复用查询 scope。
- 禁止发送邮件、签发 Token、操作 Session 或组织业务流程。
- 表名必须显式声明，并统一使用 `moo_` 前缀。

## 4. 接口使用原则

只在实现可替换或跨边界的位置定义接口，例如：

- Repository Contract
- MailSender、SmsSender
- Clock、RandomGenerator
- 密钥存储和审计输出
- `league/oauth2-server` 要求的 Repository Interface

只有一个内部实现的 `UserService`、`AuthService` 等直接使用 `final class`，
不创建无意义的 `UserServiceInterface`。

## 5. 路由规范

- 统一使用 PHP Attribute 路由。
- 全局关闭 Webman 默认路由。
- 禁止 `#[Any]`，每个动作必须声明明确的 HTTP Method。
- 每条路由必须命名。
- 普通业务 API 使用 `/api/v1` 前缀。
- OAuth/OIDC 使用标准固定路径，不添加 `/api/v1`。

```php
#[RouteGroup('/api/v1')]
final class UserController
{
    #[Get('/me', 'api.v1.user.me')]
    public function me(Request $request): Response
    {
        // Call service and return an API response.
    }
}
```

协议端点包括：

```text
GET  /oauth/authorize
POST /oauth/token
POST /oauth/revoke
POST /oauth/introspect
GET  /oauth/userinfo
GET  /.well-known/oauth-authorization-server
GET  /.well-known/openid-configuration
GET  /.well-known/jwks.json
```

## 6. 响应规范

普通业务 API：

```json
{
  "code": 0,
  "message": "success",
  "data": {},
  "request_id": "01..."
}
```

- 成功 `code` 为 `0`。
- HTTP 状态码表达 HTTP 结果，业务 `code` 表达具体业务错误。
- 时间输出使用 RFC 3339 UTC 格式。
- 禁止在响应中暴露堆栈、SQL、密钥和内部异常信息。

OAuth/OIDC 端点严格按相关标准返回，不套普通业务响应结构。例如：

```json
{
  "error": "invalid_request",
  "error_description": "The request is missing a required parameter"
}
```

## 7. OAuth 2.1 与 OIDC 安全规则

- 交互式登录仅允许 Authorization Code + PKCE S256。
- 机器间调用允许 Client Credentials。
- 允许 Refresh Token，并强制 Refresh Token Rotation 和重放检测。
- 禁止 Implicit Grant 和 Resource Owner Password Credentials Grant。
- redirect URI 使用注册值的精确字符串匹配。
- public client 不发放 AppSecret；confidential client 才能使用 AppSecret。
- Authorization Code 单次使用且短时过期。
- Access Token 必须限制 scope、audience 和有效期。
- AppSecret 只在创建时展示一次，持久化时使用密码哈希。
- 授权码和不透明 Token 只保存 SHA-256 哈希。
- 密钥与 Token 不得出现在 URL、日志、异常和监控标签中。
- Token 响应必须包含 `Cache-Control: no-store`。

## 8. 数据库规范

- 表名前缀固定为 `moo_`。
- 内部主键使用 `BIGINT UNSIGNED`，外部公开 ID 使用 ULID。
- 外部接口不得暴露自增主键。
- 表和列使用 snake_case，类和枚举使用 PascalCase。
- 所有外键、唯一约束和常用查询条件必须建立索引。
- 多表写入由 Service 开启事务。
- 迁移或 schema 变更必须进入版本控制，禁止只修改线上数据库。
- 禁止物理删除安全审计记录；业务数据优先使用状态或软删除。

## 9. 配置与机密

- 环境差异通过 `.env` 或环境变量配置，`.env` 不得提交。
- 仓库只提供无真实密码的 `.env.example`。
- 数据库密码、Redis 密码、AppSecret、邮件密码和私钥不得写入代码。
- 生产环境必须关闭 debug，使用 HTTPS，并开启 Secure/HttpOnly Cookie。
- 私钥加密主密钥必须保存在数据库之外。

## 10. 异常、日志和审计

- 业务异常使用明确的异常类型和稳定错误码。
- Controller 不捕获无法处理的异常，由全局异常处理器统一转换。
- 日志必须携带 `request_id`，可用时携带 `user_public_id` 和 `client_id`。
- 严禁记录密码、验证码、完整 Cookie、Authorization Header 或 Token。
- 登录、授权、换 Token、撤销、密钥轮换和管理员操作必须写审计日志。

## 11. 测试与质量门槛

```text
tests/
├── Unit/
├── Integration/
└── Feature/
```

- Service 的安全分支必须有单元测试。
- Repository 与 MySQL/Redis 行为使用集成测试。
- OAuth/OIDC 端点使用 Feature 测试覆盖成功和失败响应。
- 必测：PKCE 失败、redirect URI 不匹配、授权码重放、Refresh Token
  重放、scope 越权、错误 client secret、过期与撤销 Token。
- 新代码必须通过 PHPUnit、PHPStan 和 PHP-CS-Fixer 检查。

## 12. Git 规范

- 分支使用小写短横线名称，例如 `feature/oauth-token-endpoint`。
- 提交信息使用 Conventional Commits：`feat:`、`fix:`、`refactor:`、
  `test:`、`docs:`、`chore:`。
- 一次提交只处理一个逻辑目标，禁止提交 `.env`、运行日志和真实密钥。

## 13. 注释规范

注释是代码契约的一部分，必须保持准确并随代码一起更新。

- 新增的 Controller、Service、Repository、Model、中间件和 Process 必须有
  类级 DocBlock，说明职责、边界以及不负责的内容。
- public 方法在参数、返回值、异常或安全语义无法通过类型完整表达时，必须写
  DocBlock。
- OAuth、PKCE、Token 轮换、重放检测、密码和密钥处理必须写“为什么这样做”
  以及必须保持的安全不变量。
- 复杂 SQL、事务边界、并发锁、缓存一致性和非直观兼容逻辑必须写解释性注释。
- Model 的敏感字段 cast、隐藏字段、特殊时间字段和关联关系必须有说明。
- 禁止逐行翻译代码、重复方法名或保留已经失效的注释。
- `TODO` 必须关联任务编号或写明负责人和清理条件，禁止无期限 TODO。
- 修复缺陷时，如果缺陷原因不直观，应留下原因注释并增加回归测试。

推荐：

```php
// Reusing any token in the family indicates theft, so revoke the whole family.
$this->refreshTokens->revokeFamily($familyId);
```

禁止：

```php
// Set status to active.
$user->status = UserStatus::Active;
```

## 14. 代码生命周期

- 代码按“创建、维护、弃用、删除”管理，公开接口不得无通知直接改变语义。
- 普通业务 API 使用版本前缀；破坏性变更发布新版本，旧版本经过明确弃用期后删除。
- OAuth/OIDC 标准端点不通过 URL 版本化，行为升级必须保持协议兼容。
- 废弃类、方法或路由必须使用 `@deprecated`，写明替代方案和计划删除版本。
- 数据库结构变更必须提供向前迁移方案；涉及数据转换时提供验证与回滚说明。
- 配置项新增时同步更新 `.env.example` 和文档；删除前至少经过一个弃用周期。
- 外部依赖必须锁定版本、提交 lock 文件，并定期执行安全审计和兼容性升级。
- 密钥、AppSecret 和 Refresh Token 设计时必须支持轮换，不得假设永久有效。
- 临时兼容代码必须标明删除条件，并由测试覆盖新旧行为。
- 每个关键模块必须有明确职责、测试和调用者，失去调用者的代码应及时删除。
- 注释、测试、schema 和开发文档与实现具有相同生命周期，修改行为时同步更新。
