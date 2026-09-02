import http from './http.js'

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
