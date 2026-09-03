(function (global) {
  'use strict'

  const VERSION = '1.2.0'
  const SDK_ORIGIN = new URL(document.currentScript.src).origin
  let configuration = null

  function encode(bytes) {
    let binary = ''
    bytes.forEach((byte) => { binary += String.fromCharCode(byte) })
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
  }

  function randomValue(size) {
    const bytes = new Uint8Array(size)
    global.crypto.getRandomValues(bytes)
    return encode(bytes)
  }

  async function challengeFor(verifier) {
    const digest = await global.crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier))
    return encode(new Uint8Array(digest))
  }

  function requireConfiguration() {
    if (!configuration) throw new Error('请先调用 MooAuth.init()。')
    return configuration
  }

  async function authorizationUrl(options) {
    const config = requireConfiguration()
    const state = randomValue(32)
    const verifier = randomValue(64)
    const challenge = await challengeFor(verifier)
    const mode = options.mode || 'popup'
    sessionStorage.setItem('moo_auth:' + state, JSON.stringify({
      verifier,
      mode,
      createdAt: Date.now(),
    }))
    const query = new URLSearchParams({
      client_id: config.clientId,
      redirect_uri: config.redirectUri,
      response_type: 'code',
      scope: options.scope || config.scope,
      state,
      code_challenge: challenge,
      code_challenge_method: 'S256',
      display: mode === 'popup' ? 'popup' : 'page',
    })
    if (config.pushedAuthorizationEndpoint) {
      const response = await fetch(config.pushedAuthorizationEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(Object.fromEntries(query)),
      })
      const body = await response.json().catch(() => ({}))
      if (!response.ok || typeof body.request_uri !== 'string' || !body.request_uri) {
        throw new Error(body.error_description || body.message || `无法创建授权请求 (${response.status})`)
      }
      return config.authorizeUrl + '?' + new URLSearchParams({
        request_uri: body.request_uri,
        display: mode === 'popup' ? 'popup' : 'page',
      }).toString()
    }
    return config.authorizeUrl + '?' + query.toString()
  }

  function openModal(url, config) {
    const existing = document.getElementById('moo-auth-modal')
    if (existing) existing.remove()

    const root = document.createElement('div')
    root.id = 'moo-auth-modal'
    root.setAttribute('role', 'dialog')
    root.setAttribute('aria-modal', 'true')
    root.setAttribute('aria-label', '哞哞通行证登录')
    root.innerHTML = '<div class="moo-auth-mask"></div><section class="moo-auth-panel"><header><span><img src="' + SDK_ORIGIN + '/logo.png" alt=""><strong>哞哞通行证登录</strong></span><button type="button" aria-label="关闭">×</button></header><iframe title="哞哞通行证登录" referrerpolicy="strict-origin-when-cross-origin"></iframe></section>'

    const style = document.createElement('style')
    style.textContent = '#moo-auth-modal{position:fixed;inset:0;z-index:2147483647;display:grid;padding:20px;place-items:center;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif}.moo-auth-mask{position:absolute;inset:0;background:rgba(15,23,42,.44)}.moo-auth-panel{position:relative;display:flex;width:min(760px,calc(100vw - 32px));height:min(420px,calc(100vh - 32px));overflow:hidden;border:1px solid #d9dee7;border-radius:4px;background:#fff;box-shadow:0 18px 55px rgba(15,23,42,.22);flex-direction:column}.moo-auth-panel header{display:flex;height:42px;padding:0 4px 0 15px;align-items:center;justify-content:space-between;border-bottom:1px solid #e8ebf0;color:#111827;background:#fff;flex:none}.moo-auth-panel header>span{display:flex;align-items:center;gap:8px}.moo-auth-panel header img{width:24px;height:24px;object-fit:contain}.moo-auth-panel header strong{font-size:14px;font-weight:600}.moo-auth-panel header button{display:grid;width:34px;height:34px;padding:0;place-items:center;border:0;border-radius:4px;color:#667085;font-size:22px;line-height:1;background:transparent;cursor:pointer}.moo-auth-panel header button:hover{color:#111827;background:#f1f3f6}.moo-auth-panel header button:focus-visible{outline:2px solid #2c82ff;outline-offset:-2px}.moo-auth-panel iframe{display:block;width:100%;min-height:0;border:0;background:#fff;flex:1}@media(max-width:640px){#moo-auth-modal{padding:0}.moo-auth-panel{width:100vw;height:100vh;border:0;border-radius:0}}'
    root.appendChild(style)
    document.body.appendChild(root)
    document.documentElement.style.overflow = 'hidden'

    const close = () => {
      global.removeEventListener('message', receive)
      root.remove()
      document.documentElement.style.overflow = ''
    }
    const receive = (event) => {
      if (event.origin !== new URL(config.redirectUri).origin || event.data?.type !== 'moo-oauth-callback') return
      close()
      global.dispatchEvent(new CustomEvent('moo-auth:callback', { detail: event.data.result }))
    }
    global.addEventListener('message', receive)
    root.querySelector('button').addEventListener('click', close)
    root.querySelector('.moo-auth-mask').addEventListener('click', close)
    root.querySelector('iframe').src = url
    return { close }
  }

  const api = {
    version: VERSION,
    init(options) {
      if (!options || !options.clientId || !options.redirectUri) {
        throw new Error('clientId 和 redirectUri 不能为空。')
      }
      configuration = {
        clientId: String(options.clientId),
        redirectUri: String(options.redirectUri),
        // 默认使用 SDK 所在的 origin，避免 localhost 与 127.0.0.1 的登录 Cookie 不共享。
        authorizeUrl: String(options.authorizeUrl || SDK_ORIGIN + '/connect/authorize'),
        pushedAuthorizationEndpoint: options.pushedAuthorizationEndpoint
          ? String(options.pushedAuthorizationEndpoint)
          : '',
        scope: String(options.scope || 'openid profile'),
      }
      return api
    },
    async login(options) {
      const settings = options || {}
      const mode = settings.mode || 'popup'
      const url = await authorizationUrl({ ...settings, mode })
      if (mode === 'redirect') {
        global.location.assign(url)
        return null
      }
      if (mode !== 'popup') throw new Error('mode 仅支持 popup 或 redirect。')
      return openModal(url, requireConfiguration())
    },
    consumeCallback() {
      const query = new URLSearchParams(global.location.search)
      const state = query.get('state')
      if (!state) return null
      const key = 'moo_auth:' + state
      const raw = sessionStorage.getItem(key)
      if (!raw) return { error: 'state_mismatch', description: '登录状态已过期或不匹配。' }
      sessionStorage.removeItem(key)
      let stored
      try {
        stored = JSON.parse(raw)
      } catch {
        return { error: 'invalid_state', description: '登录状态数据无效，请重新登录。' }
      }
      if (!stored.createdAt || Date.now() - stored.createdAt > 10 * 60 * 1000) {
        return { error: 'state_expired', description: '登录状态已过期，请重新登录。' }
      }
      return {
        code: query.get('code'),
        error: query.get('error'),
        description: query.get('error_description'),
        state,
        verifier: stored.verifier,
        mode: stored.mode,
      }
    },
    handleCallback() {
      const result = api.consumeCallback()
      if (!result) return null

      // 弹框回调与宿主页面同源，限定 targetOrigin 可防止授权码和 PKCE verifier 泄漏给其他窗口。
      if (result.mode === 'popup' && global.parent !== global) {
        global.parent.postMessage({ type: 'moo-oauth-callback', result }, new URL(requireConfiguration().redirectUri).origin)
      }
      return result
    },
  }

  Object.defineProperty(global, 'MooAuth', { value: api, writable: false, configurable: false })
})(window)
