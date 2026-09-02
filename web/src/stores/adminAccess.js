import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

/** 保存当前登录态下的后台权限，仅用于界面裁剪；接口仍必须由后端鉴权。 */
export const useAdminAccessStore = defineStore('admin-access', () => {
  const roles = ref([])
  const permissions = ref([])
  const loaded = ref(false)
  const permissionSet = computed(() => new Set(permissions.value))
  const isSuperAdmin = computed(() => roles.value.includes('super_admin'))

  function setAccess(access) {
    roles.value = Array.isArray(access?.roles) ? access.roles : []
    permissions.value = Array.isArray(access?.permissions) ? access.permissions : []
    loaded.value = true
  }

  function clear() {
    roles.value = []
    permissions.value = []
    loaded.value = false
  }

  function has(permission) {
    // 后端将 super_admin 作为不可裁剪的恢复入口；前端保持同一语义，避免新增权限
    // 尚未写入数据库时错误隐藏管理按钮。真正的访问控制仍由后端中间件执行。
    return !permission || isSuperAdmin.value || permissionSet.value.has(permission)
  }

  return { roles, permissions, loaded, setAccess, clear, has }
})
