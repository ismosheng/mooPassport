import http from './http.js'

export function checkAdminAccess() {
  return http.get('/admin/v1/access')
}

export function getDashboardSummary() {
  return http.get('/admin/v1/dashboard/summary')
}

export function getApplications(params = {}) {
  return http.get('/admin/v1/applications', { params })
}

export function createApplication(payload) {
  return http.post('/admin/v1/applications', payload)
}

export function uploadApplicationLogo(file) {
  const body = new FormData()
  body.append('logo', file)
  return http.post('/admin/v1/application-assets/logo', body)
}

export function getApplication(id) {
  return http.get(`/admin/v1/applications/${encodeURIComponent(id)}`)
}

export function updateApplication(id, payload) {
  return http.put(`/admin/v1/applications/${encodeURIComponent(id)}`, payload)
}

export function deleteApplication(id) {
  return http.delete(`/admin/v1/applications/${encodeURIComponent(id)}`)
}

export function getOAuthClient(clientId) {
  return http.get(`/admin/v1/oauth/clients/${encodeURIComponent(clientId)}`)
}

export function updateOAuthClient(clientId, payload) {
  return http.put(`/admin/v1/oauth/clients/${encodeURIComponent(clientId)}`, payload)
}

export function rotateOAuthClientSecret(clientId) {
  return http.post(`/admin/v1/oauth/clients/${encodeURIComponent(clientId)}/rotate-secret`)
}

export function updateOAuthClientStatus(clientId, status) {
  return http.post(`/admin/v1/oauth/clients/${encodeURIComponent(clientId)}/status`, { status })
}

export function getUsers(params = {}) {
  return http.get('/admin/v1/users', { params })
}

export function updateUserStatus(userId, status) {
  return http.put(`/admin/v1/users/${encodeURIComponent(userId)}/status`, { status })
}

export function getUser(userId) {
  return http.get(`/admin/v1/users/${encodeURIComponent(userId)}`)
}

export function forceLogoutUser(userId) {
  return http.post(`/admin/v1/users/${encodeURIComponent(userId)}/force-logout`)
}

export function getAuditLogs(params = {}) {
  return http.get('/admin/v1/audit-logs', { params })
}

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
