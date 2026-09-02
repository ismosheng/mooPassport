import http from './http.js'
export const getSettings = () => http.get('/admin/v1/settings')
export const updateSettings = (values, versions) => http.put('/admin/v1/settings', { values, versions })
