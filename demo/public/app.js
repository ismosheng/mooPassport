const elements = {
  connection: document.querySelector('#connection-state'),
  connectionLabel: document.querySelector('#connection-label'),
  notice: document.querySelector('#notice'),
  login: document.querySelector('#login'),
  result: document.querySelector('#result'),
  popup: document.querySelector('#popup-login'),
  redirect: document.querySelector('#redirect-login'),
  logout: document.querySelector('#logout-button'),
  loginAgain: document.querySelector('#login-again'),
  scope: document.querySelector('#scope-value'),
  logo: document.querySelector('#brand-logo'),
  avatar: document.querySelector('#user-avatar'),
  name: document.querySelector('#user-name'),
  identifier: document.querySelector('#user-identifier'),
  verified: document.querySelector('#verified-badge'),
  claims: document.querySelector('#claim-list'),
  raw: document.querySelector('#raw-userinfo'),
}

let config
let auth

boot().catch((error) => setError(error.message || 'Demo 初始化失败。'))

async function boot() {
  config = await request('/api/config')
  elements.scope.textContent = config.scope
  elements.logo.src = `${config.passportWebUrl}/logo.png`
  if (!config.configured) {
    setConnection('error', '等待配置')
    setError('尚未配置客户端。请复制 demo/.env.example 为 demo/.env，填写 AppID 和 AppSecret 后重启 Demo。')
    elements.popup.disabled = true
    elements.redirect.disabled = true
    return
  }

  await loadScript(config.sdkUrl)
  auth = window.MooAuth.init({
    clientId: config.clientId,
    redirectUri: config.callbackUrl,
    authorizeUrl: config.authorizeUrl,
    pushedAuthorizationEndpoint: config.pushedAuthorizationEndpoint,
    scope: config.scope,
  })
  bindActions()

  const callback = auth.handleCallback()
  if (callback?.mode === 'popup' && window.parent !== window) return
  if (callback) {
    if (callback.error) throw new Error(callback.description || callback.error)
    await exchange(callback)
    window.history.replaceState({}, '', '/')
  } else {
    await refreshSession()
  }
  setConnection('ready', '已连接')
}

function bindActions() {
  elements.popup.addEventListener('click', () => beginLogin('popup'))
  elements.redirect.addEventListener('click', () => beginLogin('redirect'))
  elements.loginAgain.addEventListener('click', () => beginLogin('popup'))
  elements.logout.addEventListener('click', logout)
  window.addEventListener('moo-auth:callback', async (event) => {
    try {
      if (event.detail?.error) throw new Error(event.detail.description || event.detail.error)
      await exchange(event.detail)
    } catch (error) {
      setError(error.message)
    } finally {
      setBusy(false)
    }
  })
}

async function beginLogin(mode) {
  clearNotice()
  setBusy(true)
  try {
    await auth.login({ mode })
    if (mode === 'popup') setBusy(false)
  } catch (error) {
    setBusy(false)
    setError(error.message || '无法发起授权。')
  }
}

async function exchange(callback) {
  if (!callback?.code || !callback?.verifier) throw new Error('授权回调缺少 code 或 PKCE verifier。')
  setBusy(true)
  const session = await request('/api/oauth/exchange', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ code: callback.code, code_verifier: callback.verifier }),
  })
  renderSession(session)
  setBusy(false)
}

async function refreshSession() {
  renderSession(await request('/api/session'))
}

async function logout() {
  setBusy(true)
  try {
    await request('/api/logout', { method: 'POST' })
    renderSession({ authenticated: false })
  } catch (error) {
    setError(error.message)
  } finally {
    setBusy(false)
  }
}

function renderSession(session) {
  const authenticated = Boolean(session.authenticated && session.user)
  elements.login.classList.toggle('hidden', authenticated)
  elements.result.classList.toggle('hidden', !authenticated)
  elements.logout.classList.toggle('hidden', !authenticated)
  if (!authenticated) return

  const user = session.user
  elements.name.textContent = user.name || user.preferred_username || '已登录用户'
  elements.identifier.textContent = user.email || user.sub
  elements.avatar.replaceChildren()
  if (user.picture) {
    const image = document.createElement('img')
    image.src = new URL(user.picture, config.passportWebUrl).href
    image.alt = ''
    elements.avatar.appendChild(image)
  } else {
    elements.avatar.textContent = (user.name || user.preferred_username || 'M').slice(0, 1)
  }
  if ('realname_verified' in user) {
    elements.verified.textContent = user.realname_verified ? '实名已核验' : '实名未核验'
    elements.verified.classList.remove('hidden')
  } else {
    elements.verified.classList.add('hidden')
  }
  elements.claims.replaceChildren(...Object.entries(user).map(([name, value]) => claimRow(name, value)))
  elements.raw.textContent = JSON.stringify(user, null, 2)
  clearNotice()
  document.querySelector('#result').scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function claimRow(name, value) {
  const row = document.createElement('div')
  const term = document.createElement('dt')
  const description = document.createElement('dd')
  term.textContent = name
  description.textContent = typeof value === 'object' ? JSON.stringify(value) : String(value)
  row.append(term, description)
  return row
}

async function request(url, options) {
  const response = await fetch(url, { credentials: 'same-origin', ...options })
  const body = await response.json().catch(() => ({}))
  if (!response.ok) {
    const error = new Error(body.message || `请求失败 (${response.status})`)
    error.code = body.error || 'request_failed'
    throw error
  }
  return body
}

function loadScript(url) {
  return new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = url
    script.onload = resolve
    script.onerror = () => reject(new Error(`无法加载 MooAuth SDK：${url}`))
    document.head.appendChild(script)
  })
}

function setBusy(busy) {
  elements.popup.disabled = busy
  elements.redirect.disabled = busy
  elements.loginAgain.disabled = busy
  elements.logout.disabled = busy
  elements.connectionLabel.textContent = busy ? '处理中' : '已连接'
}

function setConnection(type, label) {
  elements.connection.className = `connection-state ${type}`
  elements.connectionLabel.textContent = label
}

function setError(message) {
  elements.notice.textContent = message
  elements.notice.className = 'notice error'
  setConnection('error', '需要处理')
}

function clearNotice() {
  elements.notice.textContent = ''
  elements.notice.className = 'notice hidden'
}
