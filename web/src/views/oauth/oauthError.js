import { useRoute, useRouter } from 'vue-router'

const ERROR_MESSAGES = {
  invalid_request: '授权请求参数无效。',
  unauthorized_client: '该应用未被允许发起此授权请求。',
  unsupported_response_type: '应用请求的响应类型不受支持。',
  invalid_scope: '应用请求的权限范围无效。',
  access_denied: '你已拒绝此次授权。',
  login_required: '请先登录后再继续授权。',
}

export function resolveOAuthError(routeQuery) {
  const error = typeof routeQuery.error === 'string' ? routeQuery.error : 'invalid_request'
  const description = typeof routeQuery.error_description === 'string' && routeQuery.error_description
    ? routeQuery.error_description
    : ERROR_MESSAGES[error] || '授权请求无法继续，请返回应用重试。'

  return { error, description }
}

export function redirectToOAuthError(router, route, payload) {
  return router.replace({
    name: 'oauth-error',
    query: {
      error: payload.error || 'invalid_request',
      error_description: payload.error_description || payload.message || '',
      client_id: typeof route.query.client_id === 'string' ? route.query.client_id : '',
    },
  })
}

export function useOAuthAuthorizeRedirect() {
  const route = useRoute()
  const router = useRouter()
  return { route, router }
}
