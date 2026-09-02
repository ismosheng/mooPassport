<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NDropdown, NIcon } from 'naive-ui'
import { ChevronBackOutline, ChevronDownOutline, ChevronForwardOutline, CloseOutline } from '@vicons/ionicons5'
import { useAdminTabsStore } from '../../stores/adminTabs.js'

const route = useRoute(), router = useRouter(), tabs = useAdminTabsStore()
const contextVisible = ref(false), contextX = ref(0), contextY = ref(0), contextTab = ref(null)
const tabList = ref(null)
watch(() => route.fullPath, () => tabs.add(route), { immediate: true })
const contextIndex = computed(() => tabs.opened.findIndex((item) => item.path === contextTab.value?.path))
const contextOptions = computed(() => [
  { label: '刷新当前', key: 'refresh' },
  { type: 'divider', key: 'd1' },
  { label: '关闭当前', key: 'close', disabled: Boolean(contextTab.value?.fixed) },
  { label: '关闭左侧', key: 'left', disabled: contextIndex.value <= 1 },
  { label: '关闭右侧', key: 'right', disabled: contextIndex.value < 0 || contextIndex.value >= tabs.opened.length - 1 },
  { label: '关闭其他', key: 'others' },
  { label: '关闭全部', key: 'all' },
])
const moreOptions = [
  { label: '刷新当前', key: 'refresh' }, { type: 'divider', key: 'd1' },
  { label: '关闭其他', key: 'others' }, { label: '关闭全部', key: 'all' },
]
function close(item) {
  if (!item || item.fixed) return
  const index = tabs.opened.findIndex((tab) => tab.path === item.path)
  tabs.remove(item.path)
  if (route.path === item.path) router.push(tabs.opened[Math.min(index, tabs.opened.length - 1)]?.path || '/admin')
}
function showContext(event, item) {
  contextVisible.value = false; contextTab.value = item; contextX.value = event.clientX; contextY.value = event.clientY
  nextTick(() => { contextVisible.value = true })
}
function action(key, item = contextTab.value) {
  if (key === 'refresh') { if (item?.path && item.path !== route.path) router.push(item.path); nextTick(() => tabs.refresh()) }
  if (key === 'close') close(item)
  if (key === 'left') tabs.removeLeft(item.path)
  if (key === 'right') tabs.removeRight(item.path)
  if (key === 'others') { tabs.closeOthers(item?.path || route.path); if (item?.path && route.path !== item.path) router.push(item.path) }
  if (key === 'all') { tabs.closeAll(); router.push('/admin') }
  contextVisible.value = false
}
function scrollTabs(offset) { tabList.value?.scrollBy({ left: offset, behavior: 'smooth' }) }
</script>
<template>
  <div class="work-tabs">
    <button class="tabs-scroll" aria-label="向左滚动" @click="scrollTabs(-240)"><n-icon :component="ChevronBackOutline" /></button>
    <div ref="tabList" class="work-tabs__list">
      <button v-for="item in tabs.opened" :key="item.path" class="work-tab" :class="{ selected: route.path === item.path }" @click="router.push(item.path)" @contextmenu.prevent="showContext($event,item)"><span>{{ item.title }}</span><span v-if="!item.fixed" class="work-tab__close" @click.stop="close(item)"><n-icon :component="CloseOutline" /></span></button>
    </div>
    <button class="tabs-scroll" aria-label="向右滚动" @click="scrollTabs(240)"><n-icon :component="ChevronForwardOutline" /></button>
    <n-dropdown trigger="click" :options="moreOptions" @select="(key) => action(key, { path: route.path })"><button class="tabs-more" aria-label="标签操作"><n-icon :component="ChevronDownOutline" /></button></n-dropdown>
    <n-dropdown placement="bottom-start" trigger="manual" :x="contextX" :y="contextY" :show="contextVisible" :options="contextOptions" :on-clickoutside="() => contextVisible = false" @select="action" />
  </div>
</template>
<style scoped>
.work-tabs{display:flex;height:46px;padding:0 20px 10px;align-items:center;background:var(--color-bg-page);user-select:none}.work-tabs__list{display:flex;min-width:0;align-items:center;gap:6px;flex:1;overflow-x:auto;scrollbar-width:none}.work-tab{position:relative;display:flex;min-width:auto;height:32px;padding:0 12px;align-items:center;gap:7px;overflow:hidden;border:1px solid transparent;border-radius:7px;background:var(--color-bg-surface);color:var(--admin-muted);font-family:inherit;font-size:var(--admin-font-md);font-weight:400;cursor:pointer;white-space:nowrap}.work-tab:hover{color:var(--color-primary);background:var(--color-primary-soft)}.work-tab.selected{color:var(--color-primary);font-weight:500}.work-tab__close{display:grid;width:18px;height:18px;place-items:center;border-radius:50%;opacity:.65}.work-tab__close .n-icon{font-size:14px}.work-tab__close:hover{background:rgba(31,35,41,.11);opacity:1}.tabs-more{display:grid;width:32px;height:32px;margin-left:7px;place-items:center;border:1px solid var(--admin-border);border-radius:7px;background:var(--color-bg-surface);color:var(--admin-muted);cursor:pointer}.tabs-more:hover{border-color:rgba(44,130,255,.28);background:var(--color-primary-soft);color:var(--color-primary)}.tabs-more .n-icon{font-size:19px}@media(max-width:960px){.work-tabs{height:42px;padding-inline:8px}}
.tabs-scroll{display:grid;width:30px;height:30px;margin:0 3px;place-items:center;flex:none;border:1px solid var(--admin-border);border-radius:7px;background:var(--color-bg-surface);color:var(--admin-muted);cursor:pointer}.tabs-scroll:hover{border-color:rgba(44,130,255,.28);background:var(--color-primary-soft);color:var(--color-primary)}.tabs-scroll .n-icon{font-size:18px}
</style>
<style scoped>
.work-tabs{height:42px;padding:2px 22px 4px;gap:3px;background:var(--color-bg-page)}
.work-tabs__list{gap:7px;padding:0 1px}
.tabs-scroll{width:30px;height:30px;margin:0 2px;border-radius:var(--radius-md)}
.tabs-more{width:30px;height:30px;margin-left:3px;border-radius:var(--radius-md)}
@media(max-width:960px){.work-tabs{padding-inline:14px}.tabs-scroll{margin-inline:1px}}
</style>
