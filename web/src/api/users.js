import http from './http.js'

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
