import http from './http.js'

export const getPublicSite = () => http.get('/api/v1/public/site')
