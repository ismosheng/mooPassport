<script setup>
import { computed, h, ref } from 'vue'
import { NButton, NDropdown, NIcon } from 'naive-ui'
import { MenuOutline, RefreshOutline, SettingsOutline, MoonOutline, LogOutOutline, SearchOutline } from '@vicons/ionicons5'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth.js'
import { useAdminTabsStore } from '../../stores/adminTabs.js'
import { adminMenus } from '../../config/adminMenu.js'
import { useAdminAccessStore } from '../../stores/adminAccess.js'
const emit = defineEmits(['menu', 'logout', 'settings'])
const route = useRoute(), router = useRouter(), auth = useAuthStore(), tabs = useAdminTabsStore(), access = useAdminAccessStore()
const dark = ref(localStorage.getItem('moo-theme') === 'dark')
const displayName = computed(() => auth.user?.display_name || auth.user?.username || 'Admin')
const searchOptions = computed(() => adminMenus
  .flatMap((group) => group.children
    .filter((item) => !item.pending && access.has(item.permission))
    .map((item) => ({ label: item.title, key: item.path }))))
const userOptions = [{ label: '退出登录', key: 'logout', icon: () => h(NIcon, null, { default: () => h(LogOutOutline) }) }]
function toggleTheme() { dark.value = !dark.value; document.documentElement.classList.toggle('dark-theme', dark.value); localStorage.setItem('moo-theme', dark.value ? 'dark' : 'light') }
async function toggleFullscreen() { if (document.fullscreenElement) await document.exitFullscreen(); else await document.documentElement.requestFullscreen() }
</script>
<template>
  <header class="admin-header">
    <div class="header-left"><n-button quaternary circle title="收起侧边栏" @click="emit('menu')"><template #icon><n-icon
            :component="MenuOutline" /></template></n-button><n-button quaternary circle title="刷新当前页"
        @click="tabs.refresh()"><template #icon><n-icon :component="RefreshOutline" /></template></n-button>
      <div class="crumb"><span>后台管理</span><b>/</b><strong>{{ route.meta.title || '工作台' }}</strong></div>
    </div>
    <div class="header-right">
      <n-dropdown trigger="click" :options="searchOptions" @select="(key) => router.push(key)">
        <button class="search">
          <n-icon :component="SearchOutline" />
          <span>搜索页面</span>
          <kbd>Ctrl K</kbd>
        </button>
      </n-dropdown>
      <n-button quaternary circle title="设置" @click="emit('settings')">
        <template #icon><n-icon :component="SettingsOutline" /></template>
      </n-button>
      <n-button quaternary circle title="主题" @click="toggleTheme">
        <template #icon><n-icon :component="MoonOutline" /></template>
      </n-button>
      <n-dropdown trigger="hover" :options="userOptions" @select="(key) => key === 'logout' && emit('logout')">
        <button class="avatar" :title="displayName">{{ displayName.slice(0, 1).toUpperCase() }}</button>
      </n-dropdown>
    </div>
  </header>
</template>
<style
  scoped>
  .admin-header {
    display: flex;
    height: 60px;
    padding: 0 22px;
    align-items: center;
    justify-content: space-between;
    background: var(--color-bg-page)
  }

  .header-left,
  .header-right {
    display: flex;
    align-items: center;
    gap: 8px
  }

  .crumb {
    display: flex;
    margin-left: 14px;
    align-items: center;
    gap: 8px;
    color: var(--admin-muted);
    font-size: var(--font-size-base)
  }

  .crumb strong {
    color: var(--admin-heading);
    font-weight: 600
  }

  .search {
    display: flex;
    width: 150px;
    height: 34px;
    margin-right: 6px;
    padding: 0 9px;
    align-items: center;
    gap: 7px;
    border: 1px solid var(--admin-border);
    border-radius: var(--radius-md);
    background: var(--color-bg-surface);
    color: var(--admin-muted);
    font: inherit;
    font-size: var(--admin-font-xs)
  }

  .search span {
    flex: 1;
    text-align: left
  }

  .search kbd {
    font-size: 10px
  }

  .avatar {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 50%;
    color: #fff;
    background: var(--color-primary);
    font-weight: 600;
    cursor: pointer
  }

  @media(max-width:800px) {
    .search {
      display: none
    }

    .crumb span,
    .crumb b {
      display: none
    }
  }
</style>
