import http from './http.js'

export function checkAdminAccess() {
  return http.get('/admin/v1/access')
}
