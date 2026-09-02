<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NButton, NIcon, NSpin, useMessage } from 'naive-ui'
import {
  AppsOutline,
  HomeOutline,
  KeyOutline,
  LogOutOutline,
  PersonCircleOutline,
  PhonePortraitOutline,
  ShieldCheckmarkOutline,
} from '@vicons/ionicons5'
import BrandLink from '../components/BrandLink.vue'
import { useAuthStore } from '../stores/auth.js'

const route = useRoute()
const router = useRouter()
const message = useMessage()
const auth = useAuthStore()
const loading = ref(true)
const loggingOut = ref(false)

const displayName = computed(() => auth.user?.display_name || auth.user?.username || '用户')
const initials = computed(() => displayName.value.slice(0, 1).toUpperCase())

const navItems = [
  { key: 'overview', label: '概览', to: '/account', icon: HomeOutline, exact: true },
  { key: 'profile', label: '个人资料', to: '/account/profile', icon: PersonCircleOutline },
  { key: 'security', label: '账号安全', to: '/account/security', icon: ShieldCheckmarkOutline, match: ['/account/security', '/account/change-password', '/account/sessions'] },
  { key: 'password', label: '修改密码', to: '/account/change-password', icon: KeyOutline, nested: true },
  { key: 'sessions', label: '登录设备', to: '/account/sessions', icon: PhonePortraitOutline, nested: true },
  { key: 'authorized-apps', label: '已授权应用', to: '/account/authorized-apps', icon: AppsOutline },
]

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
    await router.replace('/login')
  } catch (error) {
    message.error(error.userMessage)
  } finally {
    loggingOut.value = false
  }
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
      <BrandLink to="/account" />
      <div class="account-topbar-actions">
        <div v-if="auth.user" class="account-topbar-user">
          <span class="account-topbar-avatar">{{ initials }}</span>
          <span class="account-topbar-name">{{ displayName }}</span>
        </div>
        <n-button quaternary :loading="loggingOut" @click="signOut">
          <template #icon><n-icon :component="LogOutOutline" /></template>
          退出登录
        </n-button>
      </div>
    </header>

    <n-spin :show="loading">
      <div v-if="auth.user" class="account-frame">
        <aside class="account-sidebar">
          <div class="account-sidebar-profile">
            <div class="account-avatar">{{ initials }}</div>
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
    </n-spin>
  </main>
</template>
