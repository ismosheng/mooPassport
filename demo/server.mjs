import { createServer } from 'node:http'
import { randomBytes } from 'node:crypto'
import { readFile, stat } from 'node:fs/promises'
import { extname, join, normalize, resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('.', import.meta.url))
const publicRoot = resolve(root, 'public')
const publicRootPrefix = `${publicRoot}${sep}`
const env = await loadEnvironment(join(root, '.env'))
const port = positiveInteger(env.DEMO_PORT, 4174)
const baseUrl = trimUrl(env.DEMO_BASE_URL || `http://127.0.0.1:${port}`)
const passportWebUrl = trimUrl(env.MOO_PASSPORT_WEB_URL || 'http://127.0.0.1:3000')
const passportApiUrl = trimUrl(env.MOO_PASSPORT_API_URL || 'http://127.0.0.1:8787')
const clientId = env.MOO_CLIENT_ID || ''
const clientSecret = env.MOO_CLIENT_SECRET || ''
const clientAuthMethod = env.MOO_CLIENT_AUTH_METHOD || (clientSecret ? 'client_secret_basic' : 'none')
const scope = env.MOO_SCOPE || 'openid profile'
const configuredScopes = parseScope(scope)
const callbackUrl = `${baseUrl}/callback`
const sessions = new Map()
const sessionTtl = 60 * 60 * 1000

validateClientConfiguration()

const contentTypes = {
  '.css': 'text/css; charset=utf-8',
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
}

const server = createServer(async (request, response) => {
  try {
    const url = new URL(request.url || '/', baseUrl)
    cleanExpiredSessions()

    if (url.pathname === '/api/config' && request.method === 'GET') {
      return json(response, 200, {
        configured: Boolean(clientId),
        clientId,
        scope,
        callbackUrl,
        passportWebUrl,
        pushedAuthorizationEndpoint: `${baseUrl}/api/oauth/push`,
        sdkUrl: `${passportWebUrl}/sdk/moo-auth-sdk.js?v=1.2.0`,
        authorizeUrl: `${passportWebUrl}/connect/authorize`,
      })
    }
    if (url.pathname === '/api/session' && request.method === 'GET') {
      const session = currentSession(request)
      return json(response, 200, session
        ? { authenticated: true, user: session.user, scope: session.tokens.scope }
        : { authenticated: false })
    }
    if (url.pathname === '/api/oauth/exchange' && request.method === 'POST') {
      requireConfiguration()
      const input = await readJson(request)
      const code = requiredString(input.code, 'code')
      const verifier = requiredString(input.code_verifier, 'code_verifier')
      const tokenParameters = {
        grant_type: 'authorization_code',
        code,
        code_verifier: verifier,
        redirect_uri: callbackUrl,
      }

      const tokens = await passportRequest('/oauth/token', {
        method: 'POST',
        ...authenticatedForm(tokenParameters),
      })
      if (!sameScope(tokens.scope, configuredScopes)) {
        throw httpError(409, '授权范围与 Demo 配置不一致，请重新发起登录。', 'scope_mismatch')
      }
      const user = await passportRequest('/oauth/userinfo', {
        headers: { Authorization: `Bearer ${tokens.access_token}` },
      })
      const sessionId = randomBytes(32).toString('base64url')
      sessions.set(sessionId, { tokens, user, expiresAt: Date.now() + sessionTtl })
      response.setHeader('Set-Cookie', sessionCookie(sessionId, request))
      return json(response, 200, { authenticated: true, user, scope: tokens.scope })
    }
    if (url.pathname === '/api/oauth/push' && request.method === 'POST') {
      requireConfiguration()
      const input = await readJson(request)
      const parameters = {
        client_id: requiredString(input.client_id, 'client_id'),
        redirect_uri: requiredString(input.redirect_uri, 'redirect_uri'),
        response_type: requiredString(input.response_type, 'response_type'),
        scope: requiredString(input.scope, 'scope'),
        state: requiredString(input.state, 'state'),
        code_challenge: requiredString(input.code_challenge, 'code_challenge'),
        code_challenge_method: requiredString(input.code_challenge_method, 'code_challenge_method'),
      }
      if (parameters.client_id !== clientId || parameters.redirect_uri !== callbackUrl) {
        throw httpError(400, '授权请求与 Demo 配置不一致。', 'invalid_request')
      }
      if (parameters.response_type !== 'code' || parameters.code_challenge_method !== 'S256') {
        throw httpError(400, 'Demo 只支持 Authorization Code + PKCE S256。', 'invalid_request')
      }
      if (!sameScope(parameters.scope, configuredScopes)) {
        throw httpError(400, '授权范围与 Demo 配置不一致，请重新发起登录。', 'scope_mismatch')
      }
      if (typeof input.nonce === 'string' && input.nonce) parameters.nonce = input.nonce

      const pushed = await passportRequest('/oauth/par', {
        method: 'POST',
        ...authenticatedForm(parameters),
      })
      return json(response, 201, pushed)
    }
    if (url.pathname === '/api/logout' && request.method === 'POST') {
      const sessionId = cookieValue(request, 'moo_demo_session')
      const session = sessionId ? sessions.get(sessionId) : null
      if (sessionId) sessions.delete(sessionId)
      if (session?.tokens?.access_token && clientId) {
        const parameters = {
          token: session.tokens.access_token,
          token_type_hint: 'access_token',
        }
        await passportRequest('/oauth/revoke', {
          method: 'POST',
          ...authenticatedForm(parameters),
        }).catch(() => null)
      }
      response.setHeader('Set-Cookie', expiredSessionCookie(request))
      return json(response, 200, { authenticated: false })
    }
    if (url.pathname.startsWith('/api/')) {
      return json(response, 404, { error: 'not_found', message: 'Demo API 不存在。' })
    }

    await serveStatic(url.pathname === '/callback' ? '/index.html' : url.pathname, response)
  } catch (error) {
    const status = Number.isInteger(error.status) ? error.status : 500
    json(response, status, {
      error: error.code || 'demo_error',
      message: status === 500 ? 'Demo 服务发生错误，请查看终端日志。' : error.message,
    })
    if (status === 500) console.error(error)
  }
})

server.on('error', (error) => {
  if (error.code === 'EADDRINUSE') {
    console.error(`Demo 启动失败：127.0.0.1:${port} 已被占用。请结束占用进程，或修改 demo/.env 中的 DEMO_PORT 和 DEMO_BASE_URL。`)
    process.exitCode = 1
    return
  }
  throw error
})

server.listen(port, '127.0.0.1', () => {
  console.log(`Moo Passport OAuth Demo: ${baseUrl}`)
  console.log(`Redirect URI: ${callbackUrl}`)
  if (!clientId) console.log('尚未配置 MOO_CLIENT_ID，请先复制 .env.example 为 .env。')
})

async function passportRequest(path, options = {}) {
  const upstream = await fetch(`${passportApiUrl}${path}`, {
    ...options,
    headers: { Accept: 'application/json', ...options.headers },
  })
  const body = await upstream.json().catch(() => ({}))
  if (!upstream.ok) {
    const error = new Error(body.error_description || body.message || `上游请求失败 (${upstream.status})`)
    error.status = upstream.status >= 400 && upstream.status < 500 ? 400 : 502
    error.code = body.error || 'passport_request_failed'
    throw error
  }
  return body
}

async function serveStatic(pathname, response) {
  const requested = pathname === '/' ? '/index.html' : pathname
  const relative = normalize(decodeURIComponent(requested)).replace(/^([/\\])+/, '')
  const file = resolve(publicRoot, relative)
  if (!file.startsWith(publicRootPrefix) || !(await isFile(file))) {
    response.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' })
    return response.end('404 Not Found')
  }
  response.writeHead(200, {
    'Content-Type': contentTypes[extname(file)] || 'application/octet-stream',
    'Cache-Control': extname(file) === '.html' ? 'no-store' : 'public, max-age=300',
    'X-Content-Type-Options': 'nosniff',
  })
  response.end(await readFile(file))
}

async function loadEnvironment(file) {
  const values = { ...process.env }
  try {
    const contents = await readFile(file, 'utf8')
    for (const line of contents.split(/\r?\n/)) {
      const match = line.match(/^\s*([A-Z][A-Z0-9_]*)\s*=\s*(.*)\s*$/)
      if (!match || values[match[1]] !== undefined) continue
      values[match[1]] = match[2].replace(/^(['"])(.*)\1$/, '$2')
    }
  } catch (error) {
    if (error.code !== 'ENOENT') throw error
  }
  return values
}

async function readJson(request) {
  let body = ''
  for await (const chunk of request) {
    body += chunk
    if (body.length > 16 * 1024) throw httpError(413, '请求体过大。', 'payload_too_large')
  }
  try {
    return JSON.parse(body || '{}')
  } catch {
    throw httpError(400, '请求 JSON 格式无效。', 'invalid_json')
  }
}

function currentSession(request) {
  const sessionId = cookieValue(request, 'moo_demo_session')
  const session = sessionId ? sessions.get(sessionId) : null
  if (!session || session.expiresAt <= Date.now()) {
    if (sessionId) sessions.delete(sessionId)
    return null
  }
  return session
}

function cleanExpiredSessions() {
  const now = Date.now()
  for (const [id, session] of sessions) {
    if (session.expiresAt <= now) sessions.delete(id)
  }
}

function cookieValue(request, name) {
  const cookies = Object.fromEntries((request.headers.cookie || '').split(';').map((item) => {
    const separator = item.indexOf('=')
    return separator < 0 ? ['', ''] : [item.slice(0, separator).trim(), item.slice(separator + 1)]
  }))
  return cookies[name] || null
}

function sessionCookie(value, request) {
  return `moo_demo_session=${value}; Path=/; HttpOnly; SameSite=Lax; Max-Age=3600${isHttps(request) ? '; Secure' : ''}`
}

function expiredSessionCookie(request) {
  return `moo_demo_session=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0${isHttps(request) ? '; Secure' : ''}`
}

function isHttps(request) {
  return baseUrl.startsWith('https://') || request.headers['x-forwarded-proto'] === 'https'
}

function requireConfiguration() {
  if (!clientId) throw httpError(503, '请先在 demo/.env 中配置 MOO_CLIENT_ID。', 'demo_not_configured')
}

function authenticatedForm(parameters) {
  const body = new URLSearchParams(parameters)
  const headers = { 'Content-Type': 'application/x-www-form-urlencoded' }
  if (clientAuthMethod === 'client_secret_basic') {
    body.delete('client_id')
    body.delete('client_secret')
    const credentials = `${encodeURIComponent(clientId)}:${encodeURIComponent(clientSecret)}`
    headers.Authorization = `Basic ${Buffer.from(credentials).toString('base64')}`
  } else {
    body.set('client_id', clientId)
    if (clientAuthMethod === 'client_secret_post') body.set('client_secret', clientSecret)
  }
  return { headers, body }
}

function validateClientConfiguration() {
  const supported = ['none', 'client_secret_basic', 'client_secret_post']
  if (!supported.includes(clientAuthMethod)) {
    throw new Error(`MOO_CLIENT_AUTH_METHOD 必须是 ${supported.join('、')} 之一。`)
  }
  if (clientAuthMethod !== 'none' && !clientSecret) {
    throw new Error(`${clientAuthMethod} 认证方式必须配置 MOO_CLIENT_SECRET。`)
  }
  if (clientAuthMethod === 'none' && clientSecret) {
    throw new Error('公开客户端使用 none 认证方式时，不应配置 MOO_CLIENT_SECRET。')
  }
  if (configuredScopes.length === 0) {
    throw new Error('MOO_SCOPE 至少需要配置一个有效 Scope。')
  }
}

function parseScope(value) {
  if (typeof value !== 'string' || value.length > 1000) return []
  return [...new Set(value.trim().split(/\s+/).filter(Boolean))]
}

function sameScope(value, expected) {
  const actual = parseScope(value)
  return actual.length === expected.length && actual.every((name) => expected.includes(name))
}

function requiredString(value, name) {
  if (typeof value !== 'string' || !value) throw httpError(400, `缺少 ${name}。`, 'invalid_request')
  return value
}

function httpError(status, message, code) {
  const error = new Error(message)
  error.status = status
  error.code = code
  return error
}

function json(response, status, body) {
  response.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Cache-Control': 'no-store',
    'X-Content-Type-Options': 'nosniff',
  })
  response.end(JSON.stringify(body))
}

function trimUrl(value) {
  return value.replace(/\/+$/, '')
}

function positiveInteger(value, fallback) {
  const number = Number.parseInt(value || '', 10)
  return Number.isInteger(number) && number > 0 ? number : fallback
}

async function isFile(file) {
  try {
    return (await stat(file)).isFile()
  } catch {
    return false
  }
}
