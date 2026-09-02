<script setup>
import { computed, onMounted, ref } from 'vue'
import { NButton, NIcon, NTag, useMessage } from 'naive-ui'
import { AddOutline, AppsOutline, CheckmarkCircleOutline, ChevronForwardOutline, CloudDoneOutline, KeyOutline, PeopleOutline, RefreshOutline, ShieldCheckmarkOutline } from '@vicons/ionicons5'
import { useRouter } from 'vue-router'
import DashboardPageHeader from './components/DashboardPageHeader.vue'
import DashboardMetricCard from './components/DashboardMetricCard.vue'
import DashboardPanel from './components/DashboardPanel.vue'
import { getDashboardSummary } from '../../../api/dashboard.js'
import { useAdminAccessStore } from '../../../stores/adminAccess.js'

const router = useRouter()
const message = useMessage()
const access = useAdminAccessStore()
const loading = ref(false)
const summary = ref({ applications: 0, users: 0, active_sessions: 0, security_events_today: 0 })
const metrics = computed(() => [
  { label: '接入应用', value: summary.value.applications.toLocaleString(), change: '实时', hint: 'OAuth 2.1 / OIDC', icon: AppsOutline, tone: 'primary' },
  { label: '注册用户', value: summary.value.users.toLocaleString(), change: '实时', hint: '全部通行证账号', icon: PeopleOutline, tone: 'info' },
  { label: '活跃会话', value: summary.value.active_sessions.toLocaleString(), change: '实时', hint: '当前有效登录态', icon: KeyOutline, tone: 'success' },
  { label: '安全事件', value: summary.value.security_events_today.toLocaleString(), change: '今日', hint: '登录与授权审计', icon: ShieldCheckmarkOutline, tone: 'warning' },
])
const shortcuts = computed(() => [
  access.has('admin.applications.create') && { title: '创建应用', desc: '签发 AppID 与 AppSecret', icon: AddOutline, path: '/admin/applications' },
  access.has('admin.applications.read') && { title: '应用管理', desc: '回调地址和 Scope', icon: AppsOutline, path: '/admin/applications' },
  access.has('admin.users.read') && { title: '用户管理', desc: '账号状态与安全处置', icon: PeopleOutline, path: '/admin/users' },
  access.has('admin.audit.read') && { title: '审计日志', desc: '查看关键安全操作', icon: ShieldCheckmarkOutline, path: '/admin/audit' },
].filter(Boolean))
async function loadSummary(feedback = false) {
  loading.value = true
  try {
    const response = await getDashboardSummary()
    summary.value = response.data.data.metrics
    if (feedback) message.success('工作台数据已刷新')
  } catch (error) { message.error(error.userMessage) }
  finally { loading.value = false }
}
onMounted(() => loadSummary())
</script>
<template>
  <div class="workbench-page">
    <DashboardPageHeader title="工作台" description="汇总通行证运行状态与管理员待办事项。"><n-button :loading="loading" @click="loadSummary(true)"><template #icon><n-icon :component="RefreshOutline" /></template>刷新数据</n-button><n-button v-if="access.has('admin.applications.create')" type="primary" @click="router.push('/admin/applications')"><template #icon><n-icon :component="AddOutline" /></template>创建应用</n-button></DashboardPageHeader>
    <section class="metric-grid"><DashboardMetricCard v-for="item in metrics" :key="item.label" v-bind="item" /></section>
    <section class="workbench-grid">
      <div class="workbench-main">
        <DashboardPanel title="快捷操作" description="常用管理入口" :icon="ChevronForwardOutline"><div class="shortcut-grid"><button v-for="item in shortcuts" :key="item.title" :class="{ disabled: !item.path }" @click="item.path && router.push(item.path)"><span><n-icon :component="item.icon" /></span><div><strong>{{ item.title }}</strong><small>{{ item.desc }}</small></div><n-icon :component="ChevronForwardOutline" /></button></div></DashboardPanel>
        <DashboardPanel title="最近活动" description="关键管理操作与安全事件" :icon="CheckmarkCircleOutline"><div class="activity-empty"><n-icon :component="CheckmarkCircleOutline" /><strong>暂无需要关注的事件</strong><span>审计接口接入后将在这里显示最新动态</span></div></DashboardPanel>
      </div>
      <div class="workbench-side">
        <DashboardPanel title="待办事项" description="需要尽快处理" :icon="CheckmarkCircleOutline" compact><div class="todo-list"><button><span>待审核应用</span><n-tag size="small" round>0</n-tag><n-icon :component="ChevronForwardOutline" /></button><button><span>异常登录事件</span><n-tag size="small" round>0</n-tag><n-icon :component="ChevronForwardOutline" /></button></div></DashboardPanel>
        <DashboardPanel title="系统状态" :icon="CloudDoneOutline" compact><div class="system-list"><div><span><i />Webman API</span><strong>正常</strong></div><div><span><i />MySQL</span><strong>正常</strong></div><div><span><i class="unknown" />Redis 会话</span><strong>未检测</strong></div><div><span><i class="unknown" />OIDC 签名</span><strong>未检测</strong></div></div></DashboardPanel>
      </div>
    </section>
  </div>
</template>
<style scoped>
.workbench-page{display:grid;padding:20px 0 28px;gap:16px}.metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.workbench-grid{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(300px,.75fr);gap:16px}.workbench-main,.workbench-side{display:grid;align-content:start;gap:16px}.shortcut-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.shortcut-grid button{display:flex;min-height:70px;padding:12px;align-items:center;gap:11px;border:1px solid var(--admin-border);border-radius:var(--admin-radius);background:var(--color-bg-page);color:inherit;text-align:left;cursor:pointer;transition:background .18s,border-color .18s,transform .18s}.shortcut-grid button:hover:not(.disabled){transform:translateY(-1px);border-color:rgba(44,130,255,.28);background:var(--color-primary-soft)}.shortcut-grid button>span{display:grid;width:36px;height:36px;place-items:center;border-radius:var(--admin-radius);background:var(--color-bg-surface);color:var(--color-primary);flex:none}.shortcut-grid button>span .n-icon{font-size:19px}.shortcut-grid button>div{display:grid;flex:1}.shortcut-grid button strong{font-size:var(--admin-font-md);font-weight:500}.shortcut-grid button small{margin-top:3px;color:var(--admin-muted);font-size:var(--admin-font-xs)}.shortcut-grid button>.n-icon{color:var(--admin-muted)}.shortcut-grid button.disabled{opacity:.5;cursor:default}.activity-empty{min-height:112px;display:grid;place-content:center;justify-items:center;color:var(--admin-muted)}.activity-empty>.n-icon{font-size:26px}.activity-empty strong{margin-top:8px;color:var(--admin-heading);font-size:var(--admin-font-sm)}.activity-empty span{margin-top:3px;font-size:var(--admin-font-xs)}.todo-list{display:grid}.todo-list button{display:grid;grid-template-columns:1fr auto auto;min-height:42px;padding:0 2px;align-items:center;gap:8px;border:0;border-bottom:1px solid var(--admin-border);background:transparent;color:var(--admin-heading);font:var(--admin-font-sm) inherit;text-align:left}.todo-list button:last-child{border-bottom:0}.system-list{display:grid;gap:12px}.system-list>div{display:flex;align-items:center;justify-content:space-between;font-size:var(--admin-font-sm)}.system-list span{display:flex;align-items:center;gap:8px;color:var(--admin-muted)}.system-list strong{font-weight:500}.system-list i{width:7px;height:7px;border-radius:50%;background:var(--color-success);box-shadow:0 0 0 3px var(--color-success-soft)}@media(max-width:1200px){.metric-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:920px){.workbench-grid{grid-template-columns:1fr}}@media(max-width:620px){.metric-grid,.shortcut-grid{grid-template-columns:1fr}}
.todo-list button{font-family:inherit;font-size:var(--admin-font-sm)}
.system-list i.unknown{background:var(--color-text-tertiary);box-shadow:0 0 0 3px var(--color-bg-subtle)}
</style>
