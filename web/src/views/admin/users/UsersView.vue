<script setup>
import { h, onMounted, ref } from 'vue'
import { NButton, NCard, NDescriptions, NDescriptionsItem, NDrawer, NDrawerContent, NDropdown, NIcon, NInput, NSelect, NSkeleton, NSpace, NTag, useDialog, useMessage } from 'naive-ui'
import { PeopleOutline, SearchOutline } from '@vicons/ionicons5'
import { getRoles, grantUserRole, revokeUserRole } from '../../../api/roles.js'
import { forceLogoutUser, getUser, getUsers, updateUserStatus } from '../../../api/users.js'
import AdminDataTable from '../../../components/common/data-table/AdminDataTable.vue'
import { useAdminAccessStore } from '../../../stores/adminAccess.js'

const loading = ref(false)
const rows = ref([])
const total = ref(0)
const page = ref(1)
const perPage = ref(20)
const keyword = ref('')
const status = ref(null)
const emailVerified = ref(null)
const showDetail = ref(false)
const detailLoading = ref(false)
const selectedUser = ref(null)
const availableRoles = ref([])
const roleLoading = ref(false)
const message = useMessage()
const dialog = useDialog()
const access = useAdminAccessStore()
const statusMeta = {
  pending: ['待激活', 'warning'], active: ['正常', 'success'], locked: ['已锁定', 'error'], disabled: ['已禁用', 'default'],
}
const statusOptions = [
  { label: '设为正常', key: 'active' }, { label: '锁定账号', key: 'locked' }, { label: '禁用账号', key: 'disabled' },
]
function formatDateTime(value) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  const parts = new Intl.DateTimeFormat('zh-CN', { timeZone: 'Asia/Shanghai', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' }).formatToParts(date)
  const part = (type) => parts.find((item) => item.type === type)?.value || ''
  return `${part('year')}-${part('month')}-${part('day')} ${part('hour')}:${part('minute')}:${part('second')}`
}
function changeStatus(row, nextStatus) {
  if (row.status === nextStatus) return
  dialog.warning({
    title: '确认修改用户状态',
    content: nextStatus === 'active' ? `确认恢复用户“${row.username || row.email}”？` : `确认将用户“${row.username || row.email}”设为${statusMeta[nextStatus][0]}？其现有会话和 Token 将立即失效。`,
    positiveText: '确认', negativeText: '取消',
    onPositiveClick: async () => {
      try { await updateUserStatus(row.id, nextStatus); message.success('用户状态已更新'); await load() }
      catch (error) { message.error(error.userMessage) }
    },
  })
}
async function openDetail(row) {
  showDetail.value = true
  detailLoading.value = true
  selectedUser.value = null
  try {
    const [userResponse, roleResponse] = await Promise.all([
      getUser(row.id),
      access.has('admin.roles.members.manage') ? getRoles({ page: 1, per_page: 100 }) : Promise.resolve(null),
    ])
    selectedUser.value = userResponse.data.data
    availableRoles.value = roleResponse?.data.data.items || []
  }
  catch (error) { message.error(error.userMessage); showDetail.value = false }
  finally { detailLoading.value = false }
}
async function toggleRole(role, granted) {
  if (!selectedUser.value || roleLoading.value) return
  roleLoading.value = true
  try {
    if (granted) await revokeUserRole(role.code, selectedUser.value.id)
    else await grantUserRole(role.code, selectedUser.value.id)
    message.success(granted ? '角色已撤销' : '角色已授予')
    await openDetail(selectedUser.value)
    await load()
  } catch (error) { message.error(error.userMessage) }
  finally { roleLoading.value = false }
}
function rowProps(row) { return { class: 'clickable-row', onClick: () => openDetail(row) } }
function forceLogout(row) {
  dialog.warning({ title: '强制下线', content: `确认撤销“${row.username || row.email}”的全部登录会话和 OAuth Token？`, positiveText: '确认下线', negativeText: '取消', onPositiveClick: async () => {
    try { const response = await forceLogoutUser(row.id); message.success(`已撤销 ${response.data.data.revoked_sessions} 个登录会话`); await openDetail(row) }
    catch (error) { message.error(error.userMessage) }
  } })
}
const columns = [
  { title: '用户', key: 'user', minWidth: 220, render: (row) => h('div', { class: 'user-cell' }, [h('span', row.avatar_url ? [h('img', { src: row.avatar_url, alt: '' })] : (row.display_name || row.username || 'U').slice(0, 1)), h('div', [h('strong', row.display_name || row.username || '未设置昵称'), h('small', row.username || row.id)])]) },
  { title: '邮箱', key: 'email', minWidth: 220, render: (row) => h('div', { class: 'email-cell' }, [h('span', row.email || '-'), h(NTag, { size: 'small', type: row.email_verified ? 'success' : 'warning', bordered: false }, { default: () => row.email_verified ? '已验证' : '未验证' })]) },
  { title: '角色', key: 'roles', width: 130, render: (row) => row.roles.includes('super_admin') ? h(NTag, { size: 'small', type: 'info' }, { default: () => '超级管理员' }) : '普通用户' },
  { title: '状态', key: 'status', width: 110, render: (row) => h(NTag, { size: 'small', type: statusMeta[row.status]?.[1] || 'default', round: true }, { default: () => statusMeta[row.status]?.[0] || row.status }) },
  { title: '最后登录', key: 'last_login_at', width: 170, render: (row) => formatDateTime(row.last_login_at) },
  { title: '注册时间', key: 'created_at', width: 170, render: (row) => formatDateTime(row.created_at) },
  { title: '操作', key: 'actions', width: 150, fixed: 'right', render: (row) => h(NSpace, { size: 10, onClick: (event) => event.stopPropagation() }, { default: () => [h(NButton, { text: true, type: 'primary', size: 'small', onClick: () => openDetail(row) }, { default: () => '查看详情' }), access.has('admin.users.status.update') && h(NDropdown, { trigger: 'click', options: statusOptions, onSelect: (key) => changeStatus(row, key) }, { default: () => h(NButton, { text: true, size: 'small' }, { default: () => '更多' }) })] }) },
]
async function load() {
  loading.value = true
  try {
    const response = await getUsers({ keyword: keyword.value.trim() || undefined, status: status.value || undefined, email_verified: emailVerified.value ?? undefined, page: page.value, per_page: perPage.value })
    rows.value = response.data.data.items
    total.value = response.data.data.total
  } catch (error) { message.error(error.userMessage) }
  finally { loading.value = false }
}
function search() { page.value = 1; load() }
function reset() { keyword.value = ''; status.value = null; emailVerified.value = null; page.value = 1; load() }
onMounted(load)
</script>

<template>
  <div class="users-page">
    <section class="search-panel"><header><div><n-icon :component="SearchOutline" /><h2>搜索条件</h2></div><small>按用户名、邮箱、昵称或用户 ID 查询</small></header><div class="search-fields"><n-input v-model:value="keyword" clearable placeholder="用户名 / 邮箱 / 昵称 / 用户 ID" @keyup.enter="search" /><n-select v-model:value="status" clearable placeholder="全部状态" :options="Object.entries(statusMeta).map(([value,item])=>({value,label:item[0]}))" /><n-select v-model:value="emailVerified" clearable placeholder="邮箱验证状态" :options="[{label:'已验证',value:1},{label:'未验证',value:0}]" /><div><n-button @click="reset">重置</n-button><n-button type="primary" @click="search">搜索</n-button></div></div></section>
    <AdminDataTable :columns="columns" :data="rows" :loading="loading" :total="total" :page="page" :page-size="perPage" title="用户列表" storage-key="moo-passport-user-table" :scroll-x="1200" :row-props="rowProps" @refresh="load" @update:page="value=>{page=value;load()}" @update:page-size="value=>{perPage=value;page=1;load()}"><template #toolbar-prepend><n-icon :component="PeopleOutline" /></template></AdminDataTable>
    <n-drawer v-model:show="showDetail" :width="560"><n-drawer-content title="用户详情" closable><template v-if="detailLoading"><n-skeleton text :repeat="8" /></template><template v-else-if="selectedUser"><div class="detail-user"><span><img v-if="selectedUser.avatar_url" :src="selectedUser.avatar_url" alt="" /><b v-else>{{ (selectedUser.display_name || selectedUser.username || 'U').slice(0,1) }}</b></span><div><h3>{{ selectedUser.display_name || selectedUser.username }}</h3><p>{{ selectedUser.email || '未绑定邮箱' }}</p><n-space><n-tag :type="statusMeta[selectedUser.status]?.[1] || 'default'">{{ statusMeta[selectedUser.status]?.[0] }}</n-tag><n-tag v-if="selectedUser.roles.includes('super_admin')" type="info">超级管理员</n-tag></n-space></div></div><n-card size="small" title="账号信息"><n-descriptions :column="1" label-placement="left"><n-descriptions-item label="用户 ID"><code>{{ selectedUser.id }}</code></n-descriptions-item><n-descriptions-item label="用户名">{{ selectedUser.username || '-' }}</n-descriptions-item><n-descriptions-item label="邮箱验证">{{ selectedUser.email_verified ? '已验证' : '未验证' }}</n-descriptions-item><n-descriptions-item label="注册时间">{{ formatDateTime(selectedUser.created_at) }}</n-descriptions-item><n-descriptions-item label="最后登录">{{ formatDateTime(selectedUser.last_login_at) }}</n-descriptions-item></n-descriptions></n-card><div class="stat-grid"><n-card size="small"><strong>{{ selectedUser.statistics.active_sessions }}</strong><span>有效会话</span></n-card><n-card size="small"><strong>{{ selectedUser.statistics.active_consents }}</strong><span>授权应用</span></n-card><n-card size="small"><strong>{{ selectedUser.statistics.owned_applications }}</strong><span>创建应用</span></n-card><n-card size="small"><strong>{{ selectedUser.statistics.failed_logins_30d }}</strong><span>30 天失败登录</span></n-card></div><n-card v-if="access.has('admin.roles.members.manage')" size="small" title="角色权限"><div class="user-role-list"><div v-for="role in availableRoles" :key="role.code"><span><strong>{{ role.name }}</strong><small>{{ role.description || role.code }}</small></span><n-button size="small" :loading="roleLoading" :type="selectedUser.roles.includes(role.code) ? 'default' : 'primary'" @click="toggleRole(role,selectedUser.roles.includes(role.code))">{{ selectedUser.roles.includes(role.code) ? '撤销' : '授予' }}</n-button></div></div></n-card><n-card v-if="access.has('admin.users.sessions.revoke') || access.has('admin.users.status.update')" size="small" title="安全操作"><n-space><n-button v-if="access.has('admin.users.sessions.revoke')" @click="forceLogout(selectedUser)">强制下线</n-button><n-dropdown v-if="access.has('admin.users.status.update')" trigger="click" :options="statusOptions" @select="(key)=>changeStatus(selectedUser,key)"><n-button type="primary">修改状态</n-button></n-dropdown></n-space></n-card></template></n-drawer-content></n-drawer>
  </div>
</template>

<style scoped>
.users-page{display:flex;width:100%;height:100%;min-height:0;padding:12px 0;gap:12px;overflow:hidden;flex-direction:column}.search-panel{padding:14px 16px;border:1px solid var(--admin-border);border-radius:var(--admin-radius);background:var(--color-bg-surface);flex:none}.search-panel header,.search-panel header>div{display:flex;align-items:center}.search-panel header{margin-bottom:12px;justify-content:space-between}.search-panel header>div{gap:7px}.search-panel h2{margin:0;font-size:var(--admin-font-md)}.search-panel small{color:var(--admin-muted);font-size:var(--admin-font-xs)}.search-fields{display:grid;grid-template-columns:minmax(260px,1fr) 180px 180px auto;gap:10px}.search-fields>div:last-child{display:flex;gap:8px}:global(.clickable-row){cursor:pointer}:global(.user-cell){display:flex;align-items:center;gap:10px}:global(.user-cell>span){display:grid;width:34px;height:34px;overflow:hidden;place-items:center;border-radius:50%;color:var(--color-primary);background:var(--color-primary-soft);font-weight:600}:global(.user-cell img){width:100%;height:100%;object-fit:cover}:global(.user-cell>div){display:grid}:global(.user-cell small){color:var(--admin-muted);font-size:var(--admin-font-xs)}:global(.email-cell){display:flex;align-items:center;gap:8px}.detail-user{display:flex;margin-bottom:16px;align-items:center;gap:14px}.detail-user>span{display:grid;width:54px;height:54px;overflow:hidden;place-items:center;border-radius:50%;color:var(--color-primary);background:var(--color-primary-soft);font-size:var(--admin-font-lg)}.detail-user img{width:100%;height:100%;object-fit:cover}.detail-user h3{margin:0;font-size:var(--admin-font-lg)}.detail-user p{margin:3px 0 7px;color:var(--admin-muted);font-size:var(--admin-font-sm)}.detail-user+.n-card,.stat-grid,.stat-grid+.n-card,.stat-grid+.n-card+.n-card{margin-top:12px}.stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.stat-grid :deep(.n-card__content){display:grid}.stat-grid strong{font-size:var(--admin-font-xl)}.stat-grid span{color:var(--admin-muted);font-size:var(--admin-font-xs)}.user-role-list{display:grid;gap:10px}.user-role-list>div{display:flex;padding:10px 0;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid var(--admin-border)}.user-role-list>div:last-child{border-bottom:0}.user-role-list span{display:grid}.user-role-list small{color:var(--admin-muted);font-size:var(--admin-font-xs)}@media(max-width:900px){.users-page{height:auto;overflow:visible}.search-fields{grid-template-columns:1fr 1fr}}@media(max-width:600px){.search-fields{grid-template-columns:1fr}.search-panel small{display:none}}
</style>


