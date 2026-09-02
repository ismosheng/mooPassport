# Moo Passport 下一步执行清单

## 立即修复

- [x] 修复 `web/src/layouts/components/AdminHeader.vue` 中残留的 `n+` 字符。
- [x] 确认 Header 模板所有 `n-button`、`n-dropdown` 标签闭合正确。
- [x] 用 Node 运行 Vite 生产构建，必须通过。

## 后台壳层

- [ ] Header 与 Tabs 作为一个连续 sticky 工作区。
- [ ] Header 和 Tabs 使用一致的左右内边距。
- [ ] Tabs 下方不显示额外边线。
- [ ] 左右滚动箭头与标签保持小间距，不贴边也不过远。
- [x] 侧栏收起状态持久化。
- [x] 头像支持 hover 弹出退出菜单。
- [x] 设置按钮打开右侧设置抽屉。
- [x] 搜索按钮展示当前有权限的后台路由并支持跳转。

## 系统设置

- [x] 创建 `moo_system_settings` 表。
- [x] 添加白名单设置、类型校验、范围校验和版本号。
- [x] 添加系统设置读写接口和审计日志。
- [x] 接通后台系统设置路由和页面。
- [x] 将设置页面改为分组导航 + 右侧表单布局，参考 `D:/moodown/moodownAdmin/src/views/settings`。
- [x] 敏感配置只展示状态，不进入设置表。（当前服务端白名单未包含敏感配置）

## RBAC 与应用管理验收

- [x] 超级管理员始终拥有全部权限。
- [ ] 普通管理员按细粒度权限显示按钮。
- [ ] 后端无权限接口返回 403。
- [ ] `admin.applications.read` 可查看所有应用。
- [ ] 应用编辑、启停、密钥轮换分别按权限校验。

## 验收命令

```powershell
cd D:\mooPassport\api
php vendor/bin/phpunit tests/Unit
php vendor/bin/phpstan analyse app tests/Unit --no-progress

cd D:\mooPassport\web
node .\node_modules\vite\bin\vite.js build
```

## 完成标准

- [ ] 不再出现模板 Invalid end tag。
- [ ] Header、Tabs、侧栏视觉统一。
- [x] 系统设置可读取、保存、冲突提示和审计。
- [ ] 关键权限越权测试通过。
