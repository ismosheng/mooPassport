<script setup>
import { h, onMounted, ref } from 'vue'
import { NButton, NIcon, NInput, NSelect, NTag } from 'naive-ui'
import { AddOutline, AppsOutline, FilterOutline, SearchOutline } from '@vicons/ionicons5'
import CreateOAuthApplicationModal from '../../components/admin/CreateOAuthApplicationModal.vue'
import AdminDataTable from '../../components/admin/AdminDataTable.vue'
import { getApplications } from '../../api/admin.js'
import { useMessage } from 'naive-ui'
import { useRouter } from 'vue-router'
import { useAdminAccessStore } from '../../stores/adminAccess.js'

const loading = ref(false)
const showCreate = ref(false)
const message = useMessage()
const router = useRouter()
const access = useAdminAccessStore()
const keyword = ref('')
const status = ref(null)
const rows = ref([])
const page = ref(1)
const perPage = ref(20)
const total = ref(0)
function formatDateTime(value) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  const parts = new Intl.DateTimeFormat('zh-CN', {
    timeZone: 'Asia/Shanghai',
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(date)
  const part = (type) => parts.find((item) => item.type === type)?.value || ''
  return `${part('year')}-${part('month')}-${part('day')} ${part('hour')}:${part('minute')}:${part('second')}`
}
const columns = [
  { title: '应用', key: 'name', minWidth: 240, render: (row) => h('div', { class: 'app-cell' }, [h('span', row.logo_url ? [h('img', { src: row.logo_url, alt: '' })] : row.name?.slice(0, 1) || 'A'), h('div', [h('strong', row.name), h('small', row.id)])]) },
  { title: 'AppID', key: 'app_id', minWidth: 280, render: (row) => h('div', { class: 'appid-cell' }, row.clients.map((client) => h('code', { key: client.client_id }, client.client_id))) },
  { title: '接入能力', key: 'capabilities', width: 220, render: (row) => h('div', { class: 'capability-tags' }, row.capabilities.map((item) => h(NTag, { size: 'small', type: item === 'login' ? 'info' : 'default' }, { default: () => item === 'login' ? '用户登录' : '服务端 API' }))) },
  { title: '客户端', key: 'clients', width: 100, render: (row) => `${row.clients.length} 个` },
  { title: '状态', key: 'status', width: 100, render: (row) => h(NTag, { type: row.status === 'active' ? 'success' : 'default', size: 'small', round: true }, { default: () => row.status === 'active' ? '正常' : '已禁用' }) },
  { title: '创建时间', key: 'created_at', width: 170, render: (row) => formatDateTime(row.created_at) },
  { title: '操作', key: 'actions', width: 120, fixed: 'right', render: (row) => h(NButton, { text: true, type: 'primary', size: 'small', onClick: () => router.push(`/admin/applications/${row.id}`) }, { default: () => '管理' }) },
]
async function load() {
  loading.value = true
  try {
    const response = await getApplications({ keyword: keyword.value.trim() || undefined, status: status.value || undefined, page: page.value, per_page: perPage.value })
    rows.value = response.data.data.items
    total.value = response.data.data.total
  }
  catch (error) { message.error(error.userMessage) }
  finally { loading.value = false }
}
function search() { page.value = 1; load() }
function resetSearch() { keyword.value = ''; status.value = null; page.value = 1; load() }
onMounted(load)
</script>
<template>
  <div class="applications-page">
    <section class="search-panel">
      <header><div><n-icon :component="FilterOutline" /><h2>搜索条件</h2></div><small>按应用名称、AppID 和状态筛选接入应用</small></header>
      <div class="search-fields"><n-input v-model:value="keyword" clearable placeholder="应用名称 / AppID" @keyup.enter="search"><template #prefix><n-icon :component="SearchOutline" /></template></n-input><n-select v-model:value="status" clearable placeholder="全部状态" :options="[{ label: '正常', value: 'active' }, { label: '已禁用', value: 'disabled' }]" /><div><n-button @click="resetSearch">重置</n-button><n-button type="primary" @click="search">搜索</n-button></div></div>
    </section>
    <AdminDataTable :columns="columns" :data="rows" :loading="loading" :total="total" :page="page" :page-size="perPage" title="应用列表" storage-key="moo-passport-app-table" :scroll-x="1180" @refresh="load" @update:page="value=>{page=value;load()}" @update:page-size="value=>{perPage=value;page=1;load()}"><template #toolbar-prepend><n-button v-if="access.has('admin.applications.create')" type="primary" @click="showCreate=true"><template #icon><n-icon :component="AddOutline" /></template>创建应用</n-button></template><template #overlay><div v-if="!loading && !rows.length" class="table-empty"><n-icon :component="AppsOutline" /><strong>还没有 OAuth 应用</strong><span>创建第一个应用后，即可获得用于接入通行证的 AppID。</span><n-button v-if="access.has('admin.applications.create')" type="primary" @click="showCreate=true"><template #icon><n-icon :component="AddOutline" /></template>创建应用</n-button></div></template></AdminDataTable>
    <CreateOAuthApplicationModal v-if="access.has('admin.applications.create')" v-model:show="showCreate" @created="load" />
  </div>
</template>
<style scoped>
.applications-page{display:flex;width:100%;height:100%;min-height:0;padding:12px 0;gap:12px;overflow:hidden;flex-direction:column}.search-panel{padding:14px 16px;border:1px solid var(--admin-border);border-radius:var(--admin-radius);background:var(--color-bg-surface);flex:none}.search-panel header{display:flex;margin-bottom:12px;align-items:center;justify-content:space-between}.search-panel header>div{display:flex;align-items:center;gap:7px}.search-panel h2{margin:0;font-size:var(--admin-font-md);font-weight:600}.search-panel header .n-icon{color:var(--color-primary);font-size:18px}.search-panel small{color:var(--admin-muted);font-size:var(--admin-font-xs)}.search-fields{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto;align-items:center;gap:10px}.search-fields>div:last-child{display:flex;gap:8px}.table-empty{position:absolute;inset:42px 0 0;display:grid;place-content:center;justify-items:center;text-align:center;color:var(--admin-muted);background:var(--color-bg-surface)}.table-empty>.n-icon{font-size:34px}.table-empty strong{margin-top:10px;color:var(--admin-heading);font-size:var(--admin-font-md)}.table-empty span{margin:5px 0 16px;font-size:var(--admin-font-xs)}:global(.app-cell){display:flex;align-items:center;gap:10px}:global(.app-cell>span){display:grid;width:34px;height:34px;overflow:hidden;place-items:center;border-radius:var(--admin-radius);color:var(--color-primary);background:var(--color-primary-soft);font-weight:600}:global(.app-cell>span img){width:100%;height:100%;object-fit:cover}:global(.app-cell>div){display:grid}:global(.app-cell strong){font-size:var(--admin-font-md)}:global(.app-cell small){margin-top:2px;color:var(--admin-muted);font-size:var(--admin-font-xs)}:global(.appid-cell){display:grid;min-width:0;gap:4px}:global(.appid-cell code){display:block;overflow:hidden;color:var(--color-primary);font-family:"SFMono-Regular",Consolas,"Liberation Mono",monospace;font-size:var(--admin-font-xs);text-overflow:ellipsis;white-space:nowrap}@media(max-width:700px){.applications-page{height:auto;overflow:visible}.search-fields{grid-template-columns:1fr}.search-panel small{display:none}}
</style>
