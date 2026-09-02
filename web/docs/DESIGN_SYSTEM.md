# 哞哞通行证前端设计约束

## 视觉方向

界面以黑、白、灰为基础，蓝色只表达主要操作、链接、焦点和当前状态。
页面服务于账号任务，不使用宣传型大渐变、无意义装饰图形和多层悬浮卡片。
登录、注册和邮箱验证可统一使用 `--background-auth` 的低对比度认证背景，业务
页面不得自行创建其他渐变。

## Token 来源

- CSS 语义变量：`src/styles/tokens.scss`
- Naive UI 组件主题：`src/theme/index.js`

业务页面禁止新增品牌色十六进制值。状态色必须优先使用已有的
`--color-success` 等语义变量；确需新增时先补充全局 Token。
浅色强调背景使用 `--color-primary-soft`、`--color-success-soft`。

## 字号

只允许使用 `--font-size-xs`、`--font-size-sm`、`--font-size-base`、
`--font-size-md`、`--font-size-lg`、`--font-size-xl` 和 `--font-size-2xl`。
正文默认 14px，辅助文字最低 12px。

管理后台为匹配高密度工作区，统一使用全局 `--admin-font-xs`、
`--admin-font-sm`、`--admin-font-md`、`--admin-font-lg` 和
`--admin-font-xl`，页面仍不得直接写基础字号。

## 圆角与阴影

只允许使用 `--radius-sm`、`--radius-md`、`--radius-lg` 和
`--radius-xl`。普通组件不超过 8px，页面容器最多 12px。阴影仅用于需要与
背景区分的浮层，普通信息分组优先使用边框和间距。

## 页面层级

每个页面只能有一个主要操作。表单、状态提示和导航优先使用 Naive UI 组件，
通过全局 `themeOverrides` 保持一致，不在页面中重复覆盖主题。
