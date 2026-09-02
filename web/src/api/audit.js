import http from './http.js'

export function getAuditLogs(params = {}) {
  return http.get('/admin/v1/audit-logs', { params })
}
