# Moo Passport Web

Moo Passport 的 Vue 3 前端，包含账号中心、OAuth/OIDC 授权页面和管理后台。

## 技术栈

- Vue 3 + JavaScript
- Vite
- Naive UI
- Pinia
- Vue Router

前端视觉实现必须遵循 [`docs/DESIGN_SYSTEM.md`](docs/DESIGN_SYSTEM.md)，不要在业务页面绕过全局 Token 自定义品牌色、基础字号或圆角体系。

## 本地开发

```bash
npm install
npm run dev
```

默认由 Vite 启动开发服务器。API 代理和开发端口配置见 [`vite.config.js`](vite.config.js)。

生产构建：

```bash
npm run build
```

## 目录结构

```text
src/
├── api/                 # 按业务域划分的 HTTP 接口
├── assets/              # 项目实际使用的图片、字体和媒体
├── components/          # 跨页面复用组件
│   └── common/          # 与具体业务域无关的基础业务组件
├── config/              # 菜单等静态应用配置
├── layouts/             # 页面壳层及其私有组件
├── router/              # 路由实例与路由表
├── stores/              # Pinia 状态
├── styles/              # 全局样式与设计 Token
├── theme/               # Naive UI 主题覆盖
└── views/               # 按产品区域和业务域组织的页面
    ├── account/
    ├── admin/
    ├── auth/
    ├── legal/
    └── oauth/
```

## 代码归属

- 只被一个业务域使用的组件放在对应 `views/<domain>/components`。
- 跨页面复用且不依赖具体业务域的组件放在 `components/common`。
- 页面壳层专用组件放在 `layouts/components`。
- API 文件按后端资源域拆分，不创建不断扩大的聚合 API 文件。
- 路由定义放在 `router/routes.js`，路由实例创建放在 `router/index.js`。

提交前至少执行一次 `npm run build`。
