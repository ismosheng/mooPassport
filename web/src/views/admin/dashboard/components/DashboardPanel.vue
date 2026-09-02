<script setup>
import { NIcon } from 'naive-ui'
defineProps({ title: { type: String, required: true }, description: { type: String, default: '' }, icon: [Object, Function], compact: Boolean })
</script>
<template><article class="dashboard-panel" :class="{ compact }"><header><div class="panel-title"><span v-if="icon"><n-icon :component="icon" /></span><div><h2>{{ title }}</h2><p v-if="description">{{ description }}</p></div></div><slot name="action" /></header><div class="dashboard-panel__body"><slot /></div></article></template>
<style scoped>
.dashboard-panel{padding:18px;border:1px solid var(--admin-border);border-radius:var(--admin-radius);background:var(--color-bg-surface);box-shadow:var(--admin-shadow)}.dashboard-panel>header{display:flex;min-height:38px;align-items:flex-start;justify-content:space-between;gap:12px}.panel-title{display:flex;min-width:0;align-items:flex-start;gap:10px}.panel-title>span{display:grid;width:30px;height:30px;place-items:center;border-radius:7px;background:var(--color-primary-soft);color:var(--color-primary);flex:none}.panel-title>span .n-icon{font-size:17px}.panel-title h2{margin:0;color:var(--admin-heading);font-size:var(--admin-font-lg);font-weight:600;line-height:1.35}.panel-title p{margin:3px 0 0;color:var(--admin-muted);font-size:var(--admin-font-xs)}.dashboard-panel__body{margin-top:15px}.compact{padding:16px}.dashboard-panel :deep(.n-button){font-size:var(--admin-font-sm);font-weight:500}
</style>
