import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '../views/auth/LoginView.vue'
import RegisterView from '../views/auth/RegisterView.vue'

export default createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/account' },
    { path: '/login', name: 'login', component: LoginView, meta: { authBackground: true } },
    { path: '/register', name: 'register', component: RegisterView, meta: { authBackground: true } },
    { path: '/forgot-password', name: 'forgot-password', component: () => import('../views/auth/ForgotPasswordView.vue'), meta: { authBackground: true } },
    { path: '/reset-password', name: 'reset-password', component: () => import('../views/auth/ResetPasswordView.vue'), meta: { authBackground: true } },
    { path: '/check-email', name: 'check-email', component: () => import('../views/auth/CheckEmailView.vue'), meta: { authBackground: true } },
    { path: '/verify-email', name: 'verify-email', component: () => import('../views/auth/VerifyEmailView.vue'), meta: { authBackground: true } },
    { path: '/terms', name: 'terms', component: () => import('../views/legal/TermsView.vue') },
    { path: '/privacy', name: 'privacy', component: () => import('../views/legal/PrivacyView.vue') },
    { path: '/oauth/authorize', name: 'oauth-authorize', component: () => import('../views/oauth/ConsentView.vue') },
    // 外部应用从该页面入口发起授权，避免开发环境的 /oauth API 代理截获页面导航。
    { path: '/connect/authorize', name: 'connect-authorize', component: () => import('../views/oauth/EmbeddedAuthorizeView.vue') },
    { path: '/oauth/error', name: 'oauth-error', component: () => import('../views/oauth/OAuthErrorView.vue') },
    { path: '/oauth/callback', name: 'oauth-callback', component: () => import('../views/oauth/OAuthCallbackView.vue') },
    {
      path: '/account',
      component: () => import('../layouts/AccountLayout.vue'),
      children: [
        { path: '', name: 'account', component: () => import('../views/account/AccountHomeView.vue') },
        { path: 'profile', name: 'account-profile', component: () => import('../views/account/ProfileView.vue') },
        { path: 'security', name: 'account-security', component: () => import('../views/account/SecurityView.vue') },
        { path: 'change-password', name: 'change-password', component: () => import('../views/account/ChangePasswordView.vue') },
        { path: 'sessions', name: 'account-sessions', component: () => import('../views/account/SessionsView.vue') },
        { path: 'authorized-apps', name: 'account-authorized-apps', component: () => import('../views/account/AuthorizedAppsView.vue') },
      ],
    },
    {
      path: '/admin',
      component: () => import('../layouts/AdminLayout.vue'),
      children: [
        { path: '', name: 'admin-dashboard', component: () => import('../views/admin/DashboardView.vue'), meta: { title: '工作台', permission: 'admin.dashboard.read' } },
        { path: 'applications', name: 'admin-applications', component: () => import('../views/admin/ApplicationsView.vue'), meta: { title: 'OAuth 应用', permission: 'admin.applications.read', fixedContent: true } },
        { path: 'applications/:id', name: 'admin-application-detail', component: () => import('../views/admin/ApplicationDetailView.vue'), meta: { title: '应用详情', permission: 'admin.applications.read' } },
        { path: 'users', name: 'admin-users', component: () => import('../views/admin/UsersView.vue'), meta: { title: '用户管理', permission: 'admin.users.read', fixedContent: true } },
        { path: 'roles', name: 'admin-roles', component: () => import('../views/admin/RolesView.vue'), meta: { title: '角色与权限', permission: 'admin.roles.read', fixedContent: true } },
        { path: 'audit', name: 'admin-audit', component: () => import('../views/admin/AuditLogsView.vue'), meta: { title: '安全审计', permission: 'admin.audit.read', fixedContent: true } },
        { path: 'settings', name: 'admin-settings', component: () => import('../views/admin/SettingsView.vue'), meta: { title: '系统设置', permission: 'admin.settings.read', fixedContent: true } },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/login' },
  ],
})
