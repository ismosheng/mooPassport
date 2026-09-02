import http from './http.js'

export function getRoles(params = {}) {
  return http.get('/admin/v1/roles', { params })
}

export function createRole(payload) {
  return http.post('/admin/v1/roles', payload)
}

export function updateRole(roleCode, payload) {
  return http.put(`/admin/v1/roles/${encodeURIComponent(roleCode)}`, payload)
}

export function updateRolePermissions(roleCode, permissions) {
  return http.put(`/admin/v1/roles/${encodeURIComponent(roleCode)}/permissions`, { permissions })
}

export function deleteRole(roleCode) {
  return http.delete(`/admin/v1/roles/${encodeURIComponent(roleCode)}`)
}

export function getRoleMembers(roleCode) {
  return http.get(`/admin/v1/roles/${encodeURIComponent(roleCode)}/members`)
}

export function grantUserRole(roleCode, userId) {
  return http.post(`/admin/v1/roles/${encodeURIComponent(roleCode)}/users/${encodeURIComponent(userId)}`)
}

export function revokeUserRole(roleCode, userId) {
  return http.delete(`/admin/v1/roles/${encodeURIComponent(roleCode)}/users/${encodeURIComponent(userId)}`)
}
