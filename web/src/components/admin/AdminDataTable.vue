<script setup>
import { computed, ref } from 'vue'
import { NButton, NCheckbox, NDataTable, NDropdown, NIcon, NPagination, NPopover } from 'naive-ui'
import { GridOutline, OptionsOutline, RefreshOutline } from '@vicons/ionicons5'

const props = defineProps({
  columns: { type: Array, required: true }, data: { type: Array, required: true }, loading: Boolean,
  total: { type: Number, default: 0 }, page: { type: Number, default: 1 }, pageSize: { type: Number, default: 20 },
  title: { type: String, required: true }, storageKey: { type: String, required: true }, scrollX: { type: [Number, String], default: 1000 },
  rowProps: Function,
})
const emit = defineEmits(['update:page', 'update:pageSize', 'refresh'])
function storedColumns() { try { const value = JSON.parse(localStorage.getItem(`${props.storageKey}-columns`) || '[]'); return Array.isArray(value) ? value : [] } catch { return [] } }
const tableSize = ref(localStorage.getItem(`${props.storageKey}-size`) || 'medium')
const hiddenColumns = ref(storedColumns())
const densityOptions = [{ label: '紧凑', key: 'small' }, { label: '标准', key: 'medium' }, { label: '宽松', key: 'large' }]
const visibleColumns = computed(() => props.columns.filter(column => column.key === 'actions' || !hiddenColumns.value.includes(column.key)))
const configurableColumns = computed(() => props.columns.filter(column => column.key !== 'actions'))
function setTableSize(value) { tableSize.value = value; localStorage.setItem(`${props.storageKey}-size`, value) }
function toggleColumn(key) { hiddenColumns.value = hiddenColumns.value.includes(key) ? hiddenColumns.value.filter(value => value !== key) : [...hiddenColumns.value, key]; localStorage.setItem(`${props.storageKey}-columns`, JSON.stringify(hiddenColumns.value)) }
function changePage(value) { emit('update:page', value) }
function changePageSize(value) { emit('update:page', 1); emit('update:pageSize', value) }
</script>

<template><section class="admin-table" :class="`density-${tableSize}`"><div class="table-toolbar"><div class="toolbar-left"><slot name="toolbar-prepend"/><strong>{{title}}</strong><span>共 {{total}} 条数据</span></div><div class="toolbar-right"><slot name="toolbar-append"/><n-button quaternary circle :loading="loading" title="刷新" @click="$emit('refresh')"><template #icon><n-icon :component="RefreshOutline"/></template></n-button><n-dropdown trigger="click" :options="densityOptions" @select="setTableSize"><n-button quaternary circle :title="`表格密度：${densityOptions.find(item=>item.key===tableSize)?.label}`"><template #icon><n-icon :component="OptionsOutline"/></template></n-button></n-dropdown><n-popover trigger="click" placement="bottom-end" :show-arrow="false"><template #trigger><n-button quaternary circle title="列设置"><template #icon><n-icon :component="GridOutline"/></template></n-button></template><div class="column-settings"><strong>显示字段</strong><n-checkbox v-for="column in configurableColumns" :key="column.key" :checked="!hiddenColumns.includes(column.key)" @update:checked="toggleColumn(column.key)">{{column.title}}</n-checkbox></div></n-popover></div></div><div class="table-body"><n-data-table :columns="visibleColumns" :data="data" :loading="loading" :bordered="false" :single-line="true" :scroll-x="scrollX" :size="tableSize" :row-props="rowProps" flex-height/><slot name="overlay"/></div><footer><n-pagination :page="page" :page-size="pageSize" :item-count="total" :page-sizes="[10,20,50,100]" show-size-picker @update:page="changePage" @update:page-size="changePageSize"/></footer></section></template>

<style scoped>
.admin-table{display:flex;min-height:0;overflow:hidden;border:1px solid var(--admin-border);border-radius:var(--admin-radius);background:var(--color-bg-surface);box-shadow:var(--admin-shadow);flex:1;flex-direction:column}.table-toolbar{display:flex;min-height:54px;padding:8px 12px;align-items:center;justify-content:space-between;gap:16px;border-bottom:1px solid var(--admin-border);flex:none}.toolbar-left,.toolbar-right{display:flex;align-items:center;gap:8px}.toolbar-left>span{color:var(--admin-muted);font-size:var(--admin-font-xs)}.toolbar-right{padding-left:10px;border-left:1px solid var(--admin-border)}.table-body{position:relative;display:flex;min-height:0;overflow:hidden;flex:1}.table-body>.n-data-table{min-height:0;flex:1}.admin-table :deep(.n-data-table-wrapper),.admin-table :deep(.n-data-table-base-table){height:100%}.admin-table :deep(.n-data-table-th){background:var(--color-bg-subtle);font-size:var(--admin-font-xs);font-weight:600}.admin-table :deep(.n-data-table-td){font-size:var(--admin-font-sm)}.density-small :deep(.n-data-table-th){height:36px}.density-small :deep(.n-data-table-td){height:48px}.density-medium :deep(.n-data-table-th){height:42px}.density-medium :deep(.n-data-table-td){height:62px}.density-large :deep(.n-data-table-th){height:48px}.density-large :deep(.n-data-table-td){height:76px}footer{display:flex;height:50px;padding:0 14px;align-items:center;justify-content:flex-end;border-top:1px solid var(--admin-border);flex:none}.column-settings{display:grid;min-width:180px;padding:4px;gap:8px}.column-settings>strong{padding-bottom:5px;border-bottom:1px solid var(--admin-border);font-size:var(--admin-font-sm)}@media(max-width:700px){.admin-table{min-height:520px;overflow:visible}.table-toolbar{align-items:flex-start;flex-direction:column}.toolbar-right{padding-left:0;border-left:0}}
</style>
