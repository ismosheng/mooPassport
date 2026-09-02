<script setup>
import { computed, h, onMounted, reactive, ref } from 'vue'
import { NButton, NCheckbox, NCheckboxGroup, NForm, NFormItem, NIcon, NInput, NModal, NSelect, NSpace, NSwitch, NTag, useDialog, useMessage } from 'naive-ui'
import { AddOutline, KeyOutline } from '@vicons/ionicons5'
import { createRole, deleteRole, getRoles, updateRole, updateRolePermissions } from '../../../api/roles.js'
import AdminDataTable from '../../../components/common/data-table/AdminDataTable.vue'
import { useAdminAccessStore } from '../../../stores/adminAccess.js'

const message = useMessage(), dialog = useDialog()
const access = useAdminAccessStore()
const loading = ref(false), saving = ref(false), roles = ref([]), permissions = ref([]), total = ref(0), page = ref(1), perPage = ref(20)
const keyword = ref(''), status = ref(null), showEditor = ref(false), showPermissions = ref(false), editing = ref(null)
const form = reactive({ code: '', name: '', description: '', status: 'active', version: 1, permissions: [] })
const moduleNames = { dashboard: '工作台', applications: '应用管理', users: '用户管理', audit: '安全审计', roles: '角色权限', settings: '系统设置' }
const permissionGroups = computed(() => Object.entries(permissions.value.reduce((groups, permission) => {
  ;(groups[permission.module] ||= []).push(permission)
  return groups
}, {})).map(([module, items]) => ({ module, name: moduleNames[module] || module, items })))

function formatDateTime(value) {
  if (!value) return '-'
  return new Intl.DateTimeFormat('zh-CN', { timeZone: 'Asia/Shanghai', dateStyle: 'medium', timeStyle: 'medium' }).format(new Date(value))
}
function openCreate() {
  editing.value = null
  Object.assign(form, { code: '', name: '', description: '', status: 'active', version: 1, permissions: [] })
  showEditor.value = true
}
function openEdit(role) {
  editing.value = role
  Object.assign(form, { code: role.code, name: role.name, description: role.description || '', status: role.status, version: role.version, permissions: [] })
  showEditor.value = true
}
function openPermissions(role) {
  editing.value = role
  form.permissions = [...role.permission_codes]
  showPermissions.value = true
}
async function saveRole() {
  if (!form.name.trim() || (!editing.value && !/^[a-z][a-z0-9_]{2,63}$/.test(form.code))) return message.warning('请填写有效的角色名称和标识')
  saving.value = true
  try {
    if (editing.value) {
      await updateRole(form.code, { name: form.name.trim(), description: form.description.trim() || null, status: form.status, version: form.version })
      message.success('角色资料已更新')
      showEditor.value = false
      await load()
    } else {
      const createdCode = form.code
      await createRole({ code: form.code, name: form.name.trim(), description: form.description.trim() || null })
      message.success('角色已创建，请继续分配权限')
      showEditor.value = false
      await load()
      const role = roles.value.find(item => item.code === createdCode)
      if (role) openPermissions(role)
    }
  } catch (error) { message.error(error.userMessage) }
  finally { saving.value = false }
}
async function savePermissions() {
  if (!editing.value || editing.value.is_system) return
  saving.value = true
  try {
    await updateRolePermissions(editing.value.code, form.permissions)
    message.success('角色权限已更新')
    showPermissions.value = false
    await load()
  } catch (error) { message.error(error.userMessage) }
  finally { saving.value = false }
}
function remove(role) {
  dialog.warning({ title: '删除角色', content: `确认删除“${role.name}”？仅无成员的自定义角色可以删除。`, positiveText: '删除', negativeText: '取消', async onPositiveClick() {
    try { await deleteRole(role.code); message.success('角色已删除'); await load() } catch (error) { message.error(error.userMessage) }
  } })
}
const columns = [
  { title: '角色', key: 'name', minWidth: 220, render: (row) => h('div', { class: 'role-name' }, [h('strong', row.name), h('code', row.code)]) },
  { title: '类型', key: 'is_system', width: 110, render: (row) => h(NTag, { size: 'small', type: row.is_system ? 'info' : 'default' }, { default: () => row.is_system ? '系统内置' : '自定义' }) },
  { title: '权限', key: 'permission_count', width: 110, render: (row) => `${row.permission_count} 项` },
  { title: '成员', key: 'user_count', width: 110, render: (row) => `${row.user_count} 人` },
  { title: '状态', key: 'status', width: 110, render: (row) => h(NTag, { size: 'small', type: row.status === 'active' ? 'success' : 'default' }, { default: () => row.status === 'active' ? '已启用' : '已停用' }) },
  { title: '更新时间', key: 'updated_at', width: 180, render: (row) => formatDateTime(row.updated_at) },
  { title: '操作', key: 'actions', fixed: 'right', width: 210, render: (row) => h(NSpace, { size: 12 }, { default: () => [h(NButton, { text: true, type: 'primary', size: 'small', onClick: () => openPermissions(row) }, { default: () => row.is_system ? '查看权限' : '配置权限' }), access.has('admin.roles.update') && h(NButton, { text: true, size: 'small', onClick: () => openEdit(row) }, { default: () => '编辑资料' }), access.has('admin.roles.delete') && !row.is_system && h(NButton, { text: true, type: 'error', size: 'small', onClick: () => remove(row) }, { default: () => '删除' })] }) },
]
async function load() {
  loading.value = true
  try {
    const data = (await getRoles({ keyword: keyword.value.trim() || undefined, status: status.value || undefined, page: page.value, per_page: perPage.value })).data.data
    roles.value = data.items
    permissions.value = data.permissions
    total.value = data.total
  }
  catch (error) { message.error(error.userMessage) }
  finally { loading.value = false }
}
function search() { page.value = 1; load() }
function reset() { keyword.value = ''; status.value = null; page.value = 1; load() }
onMounted(load)
</script>

<template>
  <div class="roles-page">
    <section class="role-search"><n-input v-model:value="keyword" clearable placeholder="搜索角色名称、标识或说明" @keyup.enter="search" /><n-select v-model:value="status" clearable placeholder="全部状态" :options="[{label:'已启用',value:'active'},{label:'已停用',value:'disabled'}]" /><n-space><n-button @click="reset">重置</n-button><n-button type="primary" @click="search">搜索</n-button></n-space></section>
    <AdminDataTable :columns="columns" :data="roles" :loading="loading" :total="total" :page="page" :page-size="perPage" title="角色列表" storage-key="moo-passport-role-table" :scroll-x="1000" @refresh="load" @update:page="value=>{page=value;load()}" @update:page-size="value=>{perPage=value;page=1;load()}"><template #toolbar-prepend><n-button v-if="access.has('admin.roles.create')" type="primary" @click="openCreate"><template #icon><n-icon :component="AddOutline" /></template>创建角色</n-button><n-icon :component="KeyOutline" /></template></AdminDataTable>
    <n-modal v-model:show="showEditor" preset="card" :title="editing ? '编辑角色资料' : '创建角色'" class="role-editor" :bordered="false">
      <n-form label-placement="top"><div class="form-grid"><n-form-item label="角色名称" required><n-input v-model:value="form.name" maxlength="100" show-count /></n-form-item><n-form-item label="角色标识" required><n-input v-model:value="form.code" :disabled="!!editing" placeholder="例如 content_operator" /></n-form-item></div><n-form-item label="角色说明"><n-input v-model:value="form.description" type="textarea" maxlength="500" show-count /></n-form-item><n-form-item v-if="editing" label="启用状态"><n-switch v-model:value="form.status" checked-value="active" unchecked-value="disabled" :disabled="editing.is_system"><template #checked>已启用</template><template #unchecked>已停用</template></n-switch></n-form-item></n-form>
      <template #footer><n-space justify="end"><n-button @click="showEditor=false">取消</n-button><n-button type="primary" :loading="saving" @click="saveRole">{{ editing ? '保存资料' : '创建并配置权限' }}</n-button></n-space></template>
    </n-modal>
    <n-modal v-model:show="showPermissions" preset="card" :title="`${editing?.name || ''} · 权限配置`" class="role-editor" :bordered="false">
      <p class="permission-hint">权限决定后台菜单、页面和操作按钮是否可用；后端接口会再次校验同一权限码。</p>
      <n-checkbox-group v-model:value="form.permissions" class="permission-groups"><section v-for="group in permissionGroups" :key="group.module"><strong>{{ group.name }}</strong><label v-for="permission in group.items" :key="permission.code"><n-checkbox :value="permission.code" :disabled="editing?.is_system || !access.has('admin.roles.permissions.update')">{{ permission.name }}</n-checkbox><small>{{ permission.description }}</small></label></section></n-checkbox-group>
      <template #footer><n-space justify="end"><n-button @click="showPermissions=false">{{ editing?.is_system || !access.has('admin.roles.permissions.update') ? '关闭' : '取消' }}</n-button><n-button v-if="!editing?.is_system && access.has('admin.roles.permissions.update')" type="primary" :loading="saving" @click="savePermissions">保存权限</n-button></n-space></template>
    </n-modal>
  </div>
</template>

<style scoped>
.roles-page{display:flex;width:100%;height:100%;min-height:0;padding:12px 0;gap:12px;overflow:hidden;flex-direction:column}.role-search{display:grid;padding:14px 16px;grid-template-columns:minmax(260px,1fr) 180px;gap:10px;border:1px solid var(--admin-border);border-radius:var(--admin-radius);background:var(--color-bg-surface)}:global(.role-name){display:grid;gap:3px}:global(.role-name code){color:var(--admin-muted);font-size:var(--admin-font-xs)}:global(.role-editor){width:min(760px,calc(100vw - 32px))}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.permission-groups{display:grid;width:100%;grid-template-columns:1fr 1fr;gap:10px}.permission-groups section{display:grid;padding:12px;gap:8px;border:1px solid var(--admin-border);border-radius:var(--radius-lg)}.permission-groups strong{font-size:var(--admin-font-md)}.permission-groups label{display:grid;grid-template-columns:auto 1fr;column-gap:8px}.permission-groups label small{grid-column:2;color:var(--admin-muted);font-size:var(--admin-font-xs)}@media(max-width:700px){.roles-page{height:auto;overflow:visible}.role-search,.form-grid,.permission-groups{grid-template-columns:1fr}}
.role-search{grid-template-columns:minmax(260px,1fr) 180px auto}
.permission-hint{margin:0 0 14px;color:var(--admin-muted);font-size:var(--admin-font-sm)}
</style>

