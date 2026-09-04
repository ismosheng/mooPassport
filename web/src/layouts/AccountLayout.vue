<script setup>
import { computed, h, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NButton, NDrawer, NDrawerContent, NDropdown, NIcon, NModal, NSkeleton, useMessage } from 'naive-ui'
import {
  AppsOutline,
  CloseOutline,
  HomeOutline,
  KeyOutline,
  LogOutOutline,
  MenuOutline,
  PersonCircleOutline,
  PhonePortraitOutline,
  ShieldCheckmarkOutline,
  WarningOutline,
} from '@vicons/ionicons5'
import BrandLink from '../components/app/BrandLink.vue'
import { useAuthStore } from '../stores/auth.js'

const route = useRoute()
const router = useRouter()
const message = useMessage()
const auth = useAuthStore()
const loading = ref(true)
const loggingOut = ref(false)
const showLogoutConfirm = ref(false)
const avatarLoadFailed = ref(false)
const mobileMenuOpen = ref(false)

const displayName = computed(() => auth.user?.display_name || auth.user?.username || '用户')
const avatarUrl = computed(() => avatarLoadFailed.value ? '' : auth.user?.avatar_url || '')
const userOptions = [
  { label: '个人资料', key: 'profile', icon: () => h(NIcon, null, { default: () => h(PersonCircleOutline) }) },
  { type: 'divider', key: 'divider' },
  { label: '退出登录', key: 'logout', icon: () => h(NIcon, null, { default: () => h(LogOutOutline) }) },
]

const navItems = [
  { key: 'overview', label: '概览', to: '/account', icon: HomeOutline, exact: true },
  { key: 'profile', label: '个人资料', to: '/account/profile', icon: PersonCircleOutline },
  { key: 'security', label: '账号安全', to: '/account/security', icon: ShieldCheckmarkOutline, match: ['/account/security', '/account/change-password', '/account/sessions'] },
  { key: 'password', label: '修改密码', to: '/account/change-password', icon: KeyOutline, nested: true },
  { key: 'sessions', label: '登录设备', to: '/account/sessions', icon: PhonePortraitOutline, nested: true },
  { key: 'authorized-apps', label: '已授权应用', to: '/account/authorized-apps', icon: AppsOutline },
]

watch(() => auth.user?.avatar_url, () => { avatarLoadFailed.value = false })
watch(() => route.fullPath, () => { mobileMenuOpen.value = false })

function isActive(item) {
  if (item.disabled || !item.to) return false
  if (item.exact) return route.path === item.to
  if (item.match) return item.match.some((path) => route.path === path || route.path.startsWith(`${path}/`))
  return route.path === item.to || route.path.startsWith(`${item.to}/`)
}

async function signOut() {
  loggingOut.value = true
  try {
    await auth.signOut()
    showLogoutConfirm.value = false
    await router.replace('/login')
  } catch (error) {
    message.error(error.userMessage || '退出失败，请稍后重试')
    return false
  } finally {
    loggingOut.value = false
  }
}

function handleUserSelect(key) {
  if (key === 'profile') router.push('/account/profile')
  if (key === 'logout') showLogoutConfirm.value = true
}

onMounted(async () => {
  try {
    if (!auth.user) await auth.loadCurrentUser()
  } catch (error) {
    if (error.response?.status === 401) {
      await router.replace('/login')
      return
    }
    message.error(error.userMessage)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="account-shell">
    <header class="account-topbar">
      <div class="account-topbar-leading">
        <button class="account-menu-button" type="button" aria-label="打开账号中心菜单" @click="mobileMenuOpen = true">
          <n-icon :component="MenuOutline" />
        </button>
        <BrandLink to="/account" />
      </div>
      <div class="account-topbar-actions">
        <n-skeleton v-if="loading" circle :width="34" :height="34" />
        <n-dropdown v-if="auth.user" trigger="hover" placement="bottom-end" :options="userOptions" @select="handleUserSelect">
          <button class="account-topbar-avatar" type="button" :title="displayName" aria-label="打开用户菜单">
            <img v-if="avatarUrl" :src="avatarUrl" alt="" @error="avatarLoadFailed = true" />
          </button>
        </n-dropdown>
      </div>
    </header>

    <div v-if="loading" class="account-frame account-loading" aria-label="正在加载账号信息" aria-busy="true">
      <aside class="account-sidebar account-loading-sidebar">
        <div class="account-loading-profile">
          <n-skeleton circle :width="42" :height="42" />
          <div><n-skeleton text width="96px" /><n-skeleton text width="72px" /></div>
        </div>
        <div class="account-loading-nav">
          <n-skeleton v-for="item in 6" :key="item" text height="38px" />
        </div>
      </aside>
      <section class="account-main account-loading-main">
        <n-skeleton text width="132px" height="24px" />
        <n-skeleton text :repeat="3" />
        <n-skeleton text width="108px" height="36px" />
      </section>
    </div>

    <div v-else-if="auth.user" class="account-frame">
        <aside class="account-sidebar">
          <div class="account-sidebar-profile">
            <div class="account-avatar">
              <img v-if="avatarUrl" :src="avatarUrl" alt="" @error="avatarLoadFailed = true" />
            </div>
            <div>
              <strong>{{ displayName }}</strong>
              <span>@{{ auth.user.username }}</span>
            </div>
          </div>

          <nav class="account-sidebar-nav" aria-label="账号中心导航">
            <component
              :is="item.disabled ? 'span' : 'router-link'"
              v-for="item in navItems"
              :key="item.key"
              class="account-nav-item"
              :class="{
                'is-active': isActive(item),
                'is-nested': item.nested,
                'is-disabled': item.disabled,
              }"
              :to="item.to || undefined"
            >
              <n-icon :component="item.icon" />
              <span>{{ item.label }}</span>
              <em v-if="item.disabled">即将开放</em>
            </component>
          </nav>
        </aside>

        <section class="account-main">
          <router-view />
        </section>
    </div>

    <n-drawer v-model:show="mobileMenuOpen" placement="left" :width="280">
      <n-drawer-content title="账号中心" closable>
        <div v-if="auth.user" class="account-drawer-profile">
          <span class="account-drawer-avatar">
            <img v-if="avatarUrl" :src="avatarUrl" alt="" @error="avatarLoadFailed = true" />
          </span>
          <div>
            <strong>{{ displayName }}</strong>
            <span>@{{ auth.user.username }}</span>
          </div>
        </div>
        <nav class="account-drawer-nav" aria-label="账号中心移动导航">
          <router-link
            v-for="item in navItems"
            :key="item.key"
            class="account-nav-item"
            :class="{ 'is-active': isActive(item), 'is-nested': item.nested }"
            :to="item.to"
          >
            <n-icon :component="item.icon" />
            <span>{{ item.label }}</span>
          </router-link>
        </nav>
      </n-drawer-content>
    </n-drawer>

    <n-modal v-model:show="showLogoutConfirm" :mask-closable="!loggingOut" :close-on-esc="!loggingOut">
      <section class="logout-modal" role="dialog" aria-modal="true" aria-labelledby="logout-modal-title">
        <button class="logout-modal-close" type="button" aria-label="关闭" :disabled="loggingOut" @click="showLogoutConfirm = false">
          <n-icon :component="CloseOutline" />
        </button>
        <header>
          <span class="logout-modal-icon"><n-icon :component="WarningOutline" /></span>
          <div>
            <h2 id="logout-modal-title">退出登录</h2>
            <p>确定要退出当前账号吗？</p>
          </div>
        </header>
        <div class="logout-modal-account">
          <span class="logout-modal-avatar">
            <img v-if="avatarUrl" :src="avatarUrl" alt="" @error="avatarLoadFailed = true" />
          </span>
          <div><strong>{{ displayName }}</strong><small>@{{ auth.user?.username }} · 退出后需重新登录</small></div>
        </div>
        <footer>
          <n-button :disabled="loggingOut" @click="showLogoutConfirm = false">继续使用</n-button>
          <n-button type="error" :loading="loggingOut" @click="signOut">退出登录</n-button>
        </footer>
      </section>
    </n-modal>
  </main>
</template>

<style scoped>
.account-topbar-leading{display:flex;min-width:0;align-items:center;gap:8px}.account-menu-button{display:none;width:34px;height:34px;padding:0;place-items:center;flex:none;border:0;border-radius:var(--radius-md);background:transparent;color:var(--color-text-primary);font-size:var(--font-size-xl);cursor:pointer}.account-topbar-avatar{display:grid;width:34px;height:34px;padding:0;place-items:center;overflow:hidden;border:0;border-radius:50%;background:var(--color-primary);color:var(--color-text-inverse);font:inherit;font-size:var(--font-size-sm);font-weight:600;cursor:pointer}.account-topbar-avatar img,.account-avatar img,.logout-modal-avatar img,.account-drawer-avatar img{width:100%;height:100%;display:block;object-fit:cover}.account-drawer-profile{display:flex;margin-bottom:14px;padding:2px 2px 16px;align-items:center;gap:12px;border-bottom:1px solid var(--color-border)}.account-drawer-avatar{width:44px;height:44px;display:grid;place-items:center;overflow:hidden;flex:none;border-radius:50%;background:var(--color-primary);color:var(--color-text-inverse);font-size:var(--font-size-xl)}.account-drawer-profile>div{display:grid;min-width:0}.account-drawer-profile strong,.account-drawer-profile span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.account-drawer-profile strong{font-size:var(--font-size-sm)}.account-drawer-profile span{color:var(--color-text-tertiary);font-size:var(--font-size-xs)}.account-drawer-nav{display:grid;gap:4px}.logout-modal{box-sizing:border-box;position:relative;width:420px;max-width:calc(100vw - 32px);padding:24px;border-radius:var(--radius-xl);background:var(--color-bg-surface);box-shadow:var(--shadow-panel)}.logout-modal-close{position:absolute;right:12px;top:12px;width:30px;height:30px;display:grid;padding:0;place-items:center;border:0;border-radius:var(--radius-md);background:transparent;color:var(--color-text-tertiary);font-size:var(--font-size-lg);cursor:pointer}.logout-modal-close:hover{background:var(--color-bg-subtle);color:var(--color-text-primary)}.logout-modal>header{display:flex;padding-right:28px;align-items:center;gap:12px}.logout-modal-icon{width:40px;height:40px;display:grid;place-items:center;flex:none;border-radius:50%;background:color-mix(in srgb,var(--color-error) 8%,var(--color-bg-surface));color:var(--color-error);font-size:var(--font-size-lg)}.logout-modal h2{margin:0;color:var(--color-text-primary);font-size:var(--font-size-md);font-weight:600}.logout-modal header p{margin:3px 0 0;color:var(--color-text-tertiary);font-size:var(--font-size-sm)}.logout-modal-account{display:flex;margin:20px 0 0;padding:16px 0;align-items:center;gap:11px;border-top:1px solid var(--color-border);border-bottom:1px solid var(--color-border)}.logout-modal-avatar{width:38px;height:38px;display:grid;place-items:center;overflow:hidden;flex:none;border-radius:50%;background:var(--color-primary);color:var(--color-text-inverse);font-size:var(--font-size-sm);font-weight:600}.logout-modal-account div{min-width:0;display:grid}.logout-modal-account strong,.logout-modal-account small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.logout-modal-account strong{color:var(--color-text-primary);font-size:var(--font-size-sm)}.logout-modal-account small{color:var(--color-text-tertiary);font-size:var(--font-size-xs)}.logout-modal footer{display:flex;margin-top:20px;justify-content:flex-end;gap:10px}.logout-modal footer :deep(.n-button){height:36px;margin:0;min-width:88px}
.account-sidebar-profile .account-avatar{overflow:hidden;border-radius:50%}
.account-loading-sidebar{position:static}.account-loading-profile{display:flex;padding-bottom:18px;align-items:center;gap:12px;border-bottom:1px solid var(--color-border)}.account-loading-profile>div{width:110px;display:grid;gap:8px}.account-loading-nav{display:grid;margin-top:18px;gap:10px}.account-loading-main{min-height:320px;padding:28px;border:1px solid var(--color-border);border-radius:var(--radius-xl);background:var(--color-bg-surface)}.account-loading-main{display:flex;flex-direction:column;gap:18px}.account-loading-main :last-child{margin-top:8px}
@media (max-width: 860px){.account-menu-button{display:grid}.account-loading-sidebar{display:none}.account-loading-main{min-height:280px;padding:20px 18px}}
</style>
