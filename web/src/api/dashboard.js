import http from './http.js'

export function getDashboardSummary() {
  return http.get('/admin/v1/dashboard/summary')
}
