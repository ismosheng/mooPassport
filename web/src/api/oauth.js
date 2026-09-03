import http from './http.js'

export const inspectAuthorization = (parameters) => http.get('/oauth/authorize', { params: parameters })
export const listConsents = (params) => http.get('/passport/v1/oauth/consents', { params })
export const revokeConsent = (clientId) => http.delete(`/passport/v1/oauth/consents/${encodeURIComponent(clientId)}`)
