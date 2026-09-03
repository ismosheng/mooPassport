# Moo Passport 数据库

数据库目标版本为 MySQL 8.0+，所有业务表使用 `moo_` 前缀。

## 全新安装

先在 `api/.env` 中配置数据库账号，然后在 `api` 目录执行。目标数据库可以提前创建；不存在时，安装账号需要具有建库权限。

```bash
php install.php
```

安装器会完成以下工作：

- 创建 `.env` 模板并在配置缺失时停止，避免使用示例密码安装
- 创建目标数据库和最终版 `database/schema.sql`
- 写入系统角色、细粒度权限和 OAuth Scope
- 交互式创建首个超级管理员
- 生成 `OIDC_PRIVATE_KEY_ENCRYPTION_KEY` 和首个 OIDC 签名密钥
- 校验关键表、字段和超级管理员权限种子

管理员密码使用隐藏输入，不会保存到命令参数、日志或 `.env`。无人值守安装使用：

```bash
MOO_INSTALL_ADMIN_PASSWORD='Replace-With-A-Strong!Password' \
php install.php \
  --admin-username=admin \
  --admin-email=admin@example.com \
  --admin-display-name='系统管理员'
```

Windows PowerShell 可先设置当前进程环境变量：

```powershell
$env:MOO_INSTALL_ADMIN_PASSWORD = 'Replace-With-A-Strong!Password'
php install.php --admin-username=admin --admin-email=admin@example.com --admin-display-name='系统管理员'
Remove-Item Env:MOO_INSTALL_ADMIN_PASSWORD
```

不创建管理员时可使用 `--no-admin`，但服务投入使用前必须通过维护流程创建并授予至少一个 `super_admin`。

## 结构检查

对已有数据库执行只读检查：

```bash
php install.php --check
```

检查模式验证 26 张业务表、关键增量字段、`service` Scope 和超级管理员权限种子，不修改数据。

## 已有数据库升级

`install.php` 只接受空数据库，检测到任何 `moo_` 表都会拒绝安装。已有数据库必须备份后，按文件名顺序执行 `database/migrations` 中尚未应用的迁移。

仓库不会保存明文密码、客户端密钥、授权码、Token、MFA 密钥或签名私钥。签名私钥在数据库中加密保存，其主密钥必须位于数据库之外。
