<script setup>
import { computed } from 'vue'
import { NIcon } from 'naive-ui'
import BrandLink from '../../components/app/BrandLink.vue'
import { adminMenus } from '../../config/adminMenu.js'
import { useAdminAccessStore } from '../../stores/adminAccess.js'
defineProps({ collapsed: Boolean, mobile: Boolean })
defineEmits(['navigate'])
const access = useAdminAccessStore()
const visibleMenus = computed(() => adminMenus
  .map((group) => ({ ...group, children: group.children.filter((item) => access.has(item.permission)) }))
  .filter((group) => group.children.length))
</script>
<template>
  <aside class="moo-admin-sidebar" :class="{ 'is-collapsed': collapsed && !mobile }">
    <div class="moo-sidebar-brand"><BrandLink to="/admin" /></div>
    <div class="moo-sidebar-scroll">
      <section v-for="group in visibleMenus" :key="group.title" class="moo-menu-group">
        <span class="moo-menu-caption">{{ group.title }}</span>
        <nav><router-link v-for="item in group.children" :key="item.path" :to="item.pending ? $route.fullPath : item.path" class="moo-menu-item" :class="{ 'is-active': $route.path === item.path, 'is-pending': item.pending }" :title="collapsed ? item.title : undefined" @click="$emit('navigate')"><n-icon :component="item.icon" /><span>{{ item.title }}</span><em v-if="item.pending">稍后</em></router-link></nav>
      </section>
    </div>
    <div class="moo-sidebar-version">{{ collapsed && !mobile ? 'M' : 'MOO Passport · v0.1.0' }}</div>
  </aside>
</template>
<style scoped>
.moo-admin-sidebar{display:flex;height:100%;min-height:0;flex-direction:column;background:var(--color-bg-surface)}.moo-sidebar-brand{display:flex;height:60px;padding:0 20px;align-items:center;overflow:hidden;flex:none}.moo-sidebar-brand :deep(.brand-link){white-space:nowrap}.moo-sidebar-scroll{min-height:0;padding:12px 10px;flex:1;overflow-y:auto;scrollbar-width:thin;scrollbar-color:transparent transparent}.moo-sidebar-scroll:hover{scrollbar-color:var(--color-border-strong) transparent}.moo-menu-group{margin-bottom:12px}.moo-menu-caption{display:block;padding:5px 12px 8px;color:var(--color-text-tertiary);font-size:10px;font-weight:600;letter-spacing:.08em;white-space:nowrap}.moo-menu-item{display:flex;min-height:42px;margin-bottom:3px;padding:0 12px;align-items:center;gap:12px;border-radius:var(--radius-lg);color:var(--color-text-tertiary);font-size:13px;font-weight:500;white-space:nowrap;transition:background .18s,color .18s}.moo-menu-item .n-icon{flex:none;font-size:18px;transition:transform .2s}.moo-menu-item:hover{color:var(--color-text-primary);background:var(--color-bg-subtle)}.moo-menu-item:hover .n-icon,.moo-menu-item.is-active .n-icon{transform:scale(1.12)}.moo-menu-item.is-active{color:var(--color-primary);background:var(--color-primary-soft);font-weight:600}.moo-menu-item.is-pending{opacity:.5}.moo-menu-item em{margin-left:auto;font-size:10px;font-style:normal;font-weight:400}.moo-sidebar-version{padding:15px;overflow:hidden;color:var(--color-text-tertiary);font-size:10px;text-align:center;white-space:nowrap}.is-collapsed .moo-sidebar-brand{padding:0 13px}.is-collapsed .moo-sidebar-brand :deep(.brand-link span),.is-collapsed .moo-menu-caption,.is-collapsed .moo-menu-item span,.is-collapsed .moo-menu-item em{display:none}.is-collapsed .moo-menu-item{padding:0;justify-content:center}.is-collapsed .moo-sidebar-scroll{padding-inline:9px}
</style>
