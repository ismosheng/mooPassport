<script setup>
import { NButton, NDrawer, NDrawerContent, NSpace, NSwitch, useMessage } from 'naive-ui'
import { ref } from 'vue'
const show = ref(false)
const dark = ref(localStorage.getItem('moo-theme') === 'dark')
const compact = ref(localStorage.getItem('moo-admin-density') === 'small')
const message = useMessage()
function open() { show.value = true }
function toggleTheme(value) { dark.value = value; document.documentElement.classList.toggle('dark-theme', value); localStorage.setItem('moo-theme', value ? 'dark' : 'light') }
function toggleDensity(value) { compact.value = value; localStorage.setItem('moo-admin-density', value ? 'small' : 'medium'); message.success('表格密度已更新，刷新页面后全部列表生效') }
defineExpose({ open })
</script>
<template><n-drawer v-model:show="show" :width="340" placement="right"><n-drawer-content title="后台偏好设置" closable><section class="setting-section"><h3>外观</h3><div class="setting-row"><span><strong>深色模式</strong><small>调整后台整体显示主题</small></span><n-switch :value="dark" @update:value="toggleTheme" /></div></section><section class="setting-section"><h3>工作区</h3><div class="setting-row"><span><strong>紧凑表格</strong><small>减少列表行高，适合高密度操作</small></span><n-switch :value="compact" @update:value="toggleDensity" /></div></section><section class="setting-section"><h3>快捷入口</h3><n-space vertical><n-button block @click="$router.push('/admin/settings'); show = false">系统业务设置</n-button><n-button block quaternary @click="localStorage.removeItem('moo-admin-sidebar-collapsed'); location.reload()">恢复侧栏默认状态</n-button></n-space></section></n-drawer-content></n-drawer></template>
<style scoped>.setting-section{padding:4px 0 22px;border-bottom:1px solid var(--admin-border)}.setting-section+ .setting-section{padding-top:22px}.setting-section h3{margin:0 0 14px;color:var(--admin-heading);font-size:var(--admin-font-md)}.setting-row{display:flex;align-items:center;justify-content:space-between;gap:16px}.setting-row span{display:grid;gap:4px}.setting-row strong{font-size:var(--admin-font-sm)}.setting-row small{color:var(--admin-muted);font-size:var(--admin-font-xs)}</style>
