<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NButton, NIcon, NSkeleton, NSpin, useDialog, useMessage } from 'naive-ui'
import { ShieldCheckmarkOutline } from '@vicons/ionicons5'
import { checkAdminAccess } from '../api/admin.js'
import { useAuthStore } from '../stores/auth.js'
import { useAdminTabsStore } from '../stores/adminTabs.js'
import { useAdminAccessStore } from '../stores/adminAccess.js'
import { firstAccessibleAdminPath } from '../config/adminMenu.js'
import AdminSidebar from './components/AdminSidebar.vue'
import AdminHeader from './components/AdminHeader.vue'
import AdminWorkTabs from './components/AdminWorkTabs.vue'
import AdminSettingsDrawer from './components/AdminSettingsDrawer.vue'

const route = useRoute(), router = useRouter(), auth = useAuthStore(), tabs = useAdminTabsStore()
const access = useAdminAccessStore()
const message = useMessage(), dialog = useDialog()
const loading = ref(true), permitted = ref(false), collapsed = ref(localStorage.getItem('moo-admin-sidebar-collapsed') === '1'), mobileOpen = ref(false)
const settingsDrawer = ref(null)
watch(collapsed, (value) => localStorage.setItem('moo-admin-sidebar-collapsed', value ? '1' : '0'))
function toggleMenu() { if (window.innerWidth <= 960) mobileOpen.value = !mobileOpen.value; else collapsed.value = !collapsed.value }
function confirmLogout() {
  dialog.warning({ title: '确认退出登录？', content: '退出后需要重新登录才能进入管理后台。', positiveText: '退出登录', negativeText: '取消', async onPositiveClick() { await auth.signOut(); await router.replace('/login') } })
}
async function enforceRoutePermission() {
  if (!access.loaded || access.has(route.meta.permission)) return
  await router.replace(firstAccessibleAdminPath(access.has))
}
watch(() => route.fullPath, enforceRoutePermission)
onMounted(async () => {
  try {
    if (!auth.user) await auth.loadCurrentUser()
    const response = await checkAdminAccess()
    access.setAccess(response.data.data)
    // 本地标签属于上一位或上一版权限状态；权限加载后立即清理，避免展示不可访问入口。
    tabs.prune((path) => {
      const resolved = router.resolve(path)
      return resolved.matched.some((record) => record.path.startsWith('/admin')) && access.has(resolved.meta.permission)
    })
    permitted.value = true
    await enforceRoutePermission()
  }
  catch (error) { if (error.response?.status === 401) return router.replace({ path: '/login', query: { redirect: route.fullPath } }); if (error.response?.status !== 403) message.error(error.userMessage) }
  finally { loading.value = false }
})
</script>
<template>
  <main v-if="loading" class="admin-loading" aria-label="正在加载管理后台">
    <aside><n-skeleton circle width="34px" height="34px" /><n-skeleton text :repeat="5" /></aside>
    <section><header><n-skeleton text width="28%" /><n-skeleton text width="160px" /></header><div class="loading-tabs"><n-skeleton text width="90px" /><n-skeleton text width="120px" /></div><div class="loading-content"><n-spin size="large" /><span>正在验证管理员权限…</span></div></section>
  </main>
  <main v-else-if="permitted" class="moo-admin-shell" :class="{ 'sidebar-collapsed': collapsed }">
      <div v-if="mobileOpen" class="moo-mobile-mask" @click="mobileOpen = false" />
      <div class="moo-sidebar-wrap" :class="{ 'is-mobile-open': mobileOpen }"><AdminSidebar :collapsed="collapsed" :mobile="mobileOpen" @navigate="mobileOpen = false" /></div>
      <section class="moo-admin-stage"><div class="moo-sticky-shell"><AdminHeader @menu="toggleMenu" @logout="confirmLogout" @settings="settingsDrawer?.open()" /><AdminWorkTabs /></div><main class="moo-admin-content" :class="{ 'is-fixed': route.meta.fixedContent }"><router-view v-slot="{ Component, route: currentRoute }"><transition name="content-fade" mode="out-in"><component :is="Component" :key="`${currentRoute.path}-${tabs.refreshKey}`" /></transition></router-view></main></section><AdminSettingsDrawer ref="settingsDrawer" />
  </main>
  <main v-else class="admin-denied"><n-icon :component="ShieldCheckmarkOutline" /><h1>无法进入管理后台</h1><p>当前账号没有任何有效的后台管理权限。账号中心仍可正常使用。</p><n-button type="primary" @click="router.replace('/account')">返回账号中心</n-button></main>
</template>
<style scoped>
.moo-admin-shell{height:100dvh;min-height:0;display:grid;grid-template-columns:240px minmax(0,1fr);overflow:hidden;background:var(--color-bg-page);transition:grid-template-columns .2s}.moo-admin-shell.sidebar-collapsed{grid-template-columns:64px minmax(0,1fr)}.moo-sidebar-wrap{height:100dvh;border-right:1px solid var(--color-border);z-index:300}.moo-admin-stage{display:flex;min-width:0;height:100dvh;min-height:0;flex-direction:column;overflow:hidden}.moo-sticky-shell{position:relative;z-index:200;flex:none;background:var(--color-bg-page)}.moo-admin-content{width:calc(100% - 40px);min-height:0;margin:0 auto;padding:0 0 24px;flex:1;overflow-y:auto;scrollbar-width:none}.moo-admin-content.is-fixed{display:flex;overflow:hidden}.moo-admin-content::-webkit-scrollbar{display:none}.content-fade-enter-active,.content-fade-leave-active{transition:opacity .14s}.content-fade-enter-from,.content-fade-leave-to{opacity:0}.moo-mobile-mask{display:none}@media(max-width:960px){.moo-admin-shell,.moo-admin-shell.sidebar-collapsed{display:block}.moo-sidebar-wrap{position:fixed;left:0;top:0;width:240px;transform:translateX(-100%);transition:transform .2s}.moo-sidebar-wrap.is-mobile-open{transform:translateX(0)}.moo-mobile-mask{position:fixed;inset:0;z-index:250;display:block;background:rgba(15,23,42,.35)}.moo-admin-content{width:calc(100% - 28px)}}
.admin-loading{display:grid;width:100%;height:100dvh;grid-template-columns:224px minmax(0,1fr);overflow:hidden;background:var(--color-bg-page)}.admin-loading>aside{display:grid;padding:16px 20px;align-content:start;gap:28px;border-right:1px solid var(--color-border);background:var(--color-bg-surface)}.admin-loading>section{display:grid;min-width:0;grid-template-rows:60px 46px 1fr}.admin-loading>section>header{display:flex;padding:0 22px;align-items:center;justify-content:space-between;border-bottom:1px solid var(--color-border)}.loading-tabs{display:flex;padding:7px 20px;align-items:center;gap:12px}.loading-content{display:grid;place-content:center;justify-items:center;gap:14px;color:var(--admin-muted);font-size:var(--admin-font-sm)}
@media(min-width:961px){.moo-admin-shell{grid-template-columns:224px minmax(0,1fr)}.moo-admin-shell.sidebar-collapsed{grid-template-columns:72px minmax(0,1fr)}}
.moo-sticky-shell + .moo-admin-content{padding-top:0}
</style>
