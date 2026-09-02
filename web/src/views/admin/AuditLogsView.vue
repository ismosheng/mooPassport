<script setup>
import { h, onMounted, ref } from 'vue'
import { NButton, NCard, NDatePicker, NDescriptions, NDescriptionsItem, NDrawer, NDrawerContent, NIcon, NInput, NSelect, NTag, useMessage } from 'naive-ui'
import { SearchOutline, ShieldCheckmarkOutline } from '@vicons/ionicons5'
import AdminDataTable from '../../components/admin/AdminDataTable.vue'
import { getAuditLogs } from '../../api/admin.js'

const loading = ref(false), rows = ref([]), total = ref(0), page = ref(1), perPage = ref(20)
const keyword = ref(''), eventType = ref(null), success = ref(null), dateRange = ref(null), eventTypes = ref([])
const selected = ref(null), showDetail = ref(false)
const message = useMessage()
const eventLabels = {
  'admin.user.status_changed': '管理员修改用户状态',
  'admin.user.force_logout': '管理员强制用户下线',
  'admin.role.created': '管理员创建角色',
  'admin.role.permissions_replaced': '管理员更新角色权限',
  'admin.role.user_granted': '管理员授予用户角色',
  'admin.role.user_revoked': '管理员撤销用户角色',
  'admin.role.updated': '管理员编辑角色',
  'admin.role.deleted': '管理员删除角色',
  'user.registered': '用户注册',
  'user.login.succeeded': '用户登录成功',
  'user.login.failed': '用户登录失败',
  'user.logout': '用户退出登录',
  'user.email.verified': '邮箱验证成功',
  'user.email.delivery_failed': '验证邮件发送失败',
  'user.password_reset.delivery_failed': '密码重置邮件发送失败',
  'user.password_reset.completed': '密码重置完成',
  'user.password_changed': '用户修改密码',
  'user.profile.updated': '用户资料更新',
  'user.session.revoked_others': '用户下线其他设备',
  'oauth.authorization.approved': 'OAuth 授权通过',
  'oauth.authorization.denied': 'OAuth 授权拒绝',
  'oauth.consent.revoked': '用户撤销应用授权',
  'oauth.token.issued': 'OAuth Token 签发',
  'oauth.token.refreshed': 'OAuth Token 刷新',
  'oauth.token.revoked': 'OAuth Token 撤销',
  'oauth.refresh_token.replayed': 'Refresh Token 重放攻击',
  'oauth.client.created': 'OAuth 客户端创建',
  'oauth.client.updated': 'OAuth 客户端配置更新',
  'oauth.client.secret_rotated': 'AppSecret 轮换',
  'oauth.client.status_changed': 'OAuth 客户端状态变更',
}
const eventLabel = (value) => eventLabels[value] || value.replaceAll('.', ' · ')
function formatDateTime(value) { if (!value) return '-'; const d = new Date(value); if (Number.isNaN(d.getTime())) return value; return new Intl.DateTimeFormat('zh-CN',{timeZone:'Asia/Shanghai',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hourCycle:'h23'}).format(d).replaceAll('/','-') }
function dateText(timestamp) { const d = new Date(timestamp); return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}` }
const columns = [
  { title:'时间',key:'created_at',width:170,render:(row)=>formatDateTime(row.created_at) },
  { title:'事件',key:'event_type',minWidth:230,render:(row)=>h('div',{class:'event-cell'},[h('strong',eventLabel(row.event_type)),h('small',row.event_type)]) },
  { title:'结果',key:'success',width:90,render:(row)=>h(NTag,{size:'small',type:row.success?'success':'error',round:true},{default:()=>row.success?'成功':'失败'}) },
  { title:'数据来源',key:'storage',width:110,render:(row)=>h(NTag,{size:'small',type:row.storage==='archive'?'warning':'default',bordered:false},{default:()=>row.storage==='archive'?'历史归档':'在线数据'}) },
  { title:'用户',key:'user',minWidth:180,render:(row)=>row.user?(row.user.username||row.user.email||row.user.id):'-' },
  { title:'应用',key:'client',minWidth:190,render:(row)=>row.client?(row.client.name||row.client.client_id):'-' },
  { title:'IP 地址',key:'ip_address',width:145,render:(row)=>row.ip_address||'-' },
  { title:'请求 ID',key:'request_id',minWidth:180,ellipsis:{tooltip:true},render:(row)=>row.request_id||'-' },
  { title:'操作',key:'actions',width:90,fixed:'right',render:(row)=>h(NButton,{text:true,type:'primary',size:'small',onClick:(e)=>{e.stopPropagation();selected.value=row;showDetail.value=true}},{default:()=> '查看详情'}) },
]
function rowProps(row){return{class:'clickable-row',onClick:()=>{selected.value=row;showDetail.value=true}}}
async function load(){loading.value=true;try{const params={keyword:keyword.value.trim()||undefined,event_type:eventType.value||undefined,success:success.value??undefined,page:page.value,per_page:perPage.value};if(dateRange.value){params.started_on=dateText(dateRange.value[0]);params.ended_on=dateText(dateRange.value[1])}const r=await getAuditLogs(params);rows.value=r.data.data.items;total.value=r.data.data.total;eventTypes.value=r.data.data.event_types}catch(e){message.error(e.userMessage)}finally{loading.value=false}}
function search(){page.value=1;load()} function reset(){keyword.value='';eventType.value=null;success.value=null;dateRange.value=null;page.value=1;load()}
onMounted(load)
</script>

<template><div class="audit-page"><section class="search-panel"><header><div><n-icon :component="SearchOutline"/><h2>搜索条件</h2></div><small>审计日志永久只读，不允许编辑或删除</small></header><div class="search-fields"><n-input v-model:value="keyword" clearable placeholder="事件 / 用户 / AppID / 请求 ID" @keyup.enter="search"/><n-select v-model:value="eventType" clearable filterable placeholder="全部事件" :options="eventTypes.map(value=>({value,label:eventLabel(value)}))"/><n-select v-model:value="success" clearable placeholder="全部结果" :options="[{label:'成功',value:1},{label:'失败',value:0}]"/><n-date-picker v-model:value="dateRange" type="daterange" clearable/><div><n-button @click="reset">重置</n-button><n-button type="primary" @click="search">搜索</n-button></div></div></section><AdminDataTable :columns="columns" :data="rows" :loading="loading" :total="total" :page="page" :page-size="perPage" title="安全审计" storage-key="moo-passport-audit-table" :scroll-x="1400" :row-props="rowProps" @refresh="load" @update:page="value=>{page=value;load()}" @update:page-size="value=>{perPage=value;page=1;load()}"><template #toolbar-prepend><n-icon :component="ShieldCheckmarkOutline"/></template></AdminDataTable><n-drawer v-model:show="showDetail" :width="560"><n-drawer-content title="审计详情" closable><template v-if="selected"><n-card size="small"><n-descriptions :column="1" label-placement="left"><n-descriptions-item label="事件">{{eventLabel(selected.event_type)}}<br><code>{{selected.event_type}}</code></n-descriptions-item><n-descriptions-item label="结果"><n-tag :type="selected.success?'success':'error'">{{selected.success?'成功':'失败'}}</n-tag></n-descriptions-item><n-descriptions-item label="数据来源"><n-tag :type="selected.storage==='archive'?'warning':'default'">{{selected.storage==='archive'?'历史归档':'在线数据'}}</n-tag></n-descriptions-item><n-descriptions-item label="发生时间">{{formatDateTime(selected.created_at)}}</n-descriptions-item><n-descriptions-item label="用户">{{selected.user?.username||selected.user?.email||'-'}}</n-descriptions-item><n-descriptions-item label="应用">{{selected.client?.name||selected.client?.client_id||'-'}}</n-descriptions-item><n-descriptions-item label="IP 地址">{{selected.ip_address||'-'}}</n-descriptions-item><n-descriptions-item label="请求 ID"><code>{{selected.request_id||'-'}}</code></n-descriptions-item><n-descriptions-item label="User-Agent">{{selected.user_agent||'-'}}</n-descriptions-item></n-descriptions></n-card><n-card size="small" title="事件数据" class="details-card"><pre><code>{{JSON.stringify(selected.details,null,2)}}</code></pre></n-card></template></n-drawer-content></n-drawer></div></template>

<style scoped>
.audit-page{display:flex;width:100%;height:100%;min-height:0;padding:12px 0;gap:12px;overflow:hidden;flex-direction:column}.search-panel{padding:14px 16px;border:1px solid var(--admin-border);border-radius:var(--admin-radius);background:var(--color-bg-surface);flex:none}.search-panel header,.search-panel header>div{display:flex;align-items:center}.search-panel header{margin-bottom:12px;justify-content:space-between}.search-panel header>div{gap:8px}.search-panel h2{margin:0;font-size:var(--admin-font-md)}.search-panel small{color:var(--admin-muted);font-size:var(--admin-font-xs)}.search-fields{display:grid;grid-template-columns:minmax(220px,1fr) 220px 140px 260px auto;gap:10px}.search-fields>div:last-child{display:flex;gap:8px}:global(.clickable-row){cursor:pointer}:global(.event-cell){display:grid}:global(.event-cell small){color:var(--admin-muted);font-size:var(--admin-font-xs)}.details-card{margin-top:12px}.details-card pre{max-height:360px;margin:0;overflow:auto}.details-card code{font-family:"SFMono-Regular",Consolas,monospace;font-size:var(--admin-font-xs)}@media(max-width:1100px){.search-fields{grid-template-columns:1fr 1fr 1fr}.search-fields>div:last-child{grid-column:3}}@media(max-width:700px){.audit-page{height:auto;overflow:visible}.search-fields{grid-template-columns:1fr}.search-fields>div:last-child{grid-column:auto}.search-panel small{display:none}}
</style>
