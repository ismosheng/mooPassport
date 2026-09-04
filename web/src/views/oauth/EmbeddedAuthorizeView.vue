<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { NAlert, NButton, NIcon, NInput, NSpin } from 'naive-ui'
import { CheckmarkCircleOutline, WarningOutline } from '@vicons/ionicons5'
import { inspectAuthorization } from '../../api/oauth.js'
import { useAuthStore } from '../../stores/auth.js'
import logo from '../../assets/logo.png'

const route = useRoute()
const auth = useAuthStore()
const popupDisplay = computed(() => route.query.display === 'popup')
const clientDisplayName = computed(() => (authorization.value?.client?.name || '该应用').replace(/\s+-\s+用户登录$/, ''))
const accountDisplayName = computed(() => auth.user?.display_name || auth.user?.username || auth.user?.email || '哞哞用户')
const accountInitials = computed(() => accountDisplayName.value.slice(0, 1).toUpperCase())
const avatarUrl = computed(() => avatarLoadFailed.value ? '' : auth.user?.avatar_url || '')
const loading = ref(true)
const signingIn = ref(false)
const deciding = ref(false)
const avatarLoadFailed = ref(false)
const errorMessage = ref('')
const authorization = ref()
const signedIn = ref(false)
const form = reactive({ identifier: '', password: '' })
const parameters = (decision) => Object.fromEntries([
  ...Object.entries(route.query).filter(([, value]) => typeof value === 'string'),
  ...(decision ? [['decision', decision]] : []),
])

const builtInScopeLabels = {
  openid: '使用你的哞哞账号身份',
  profile: '读取你的基础资料',
  email: '读取你的邮箱地址',
  realname: '读取脱敏实名信息',
  realname_full: '读取完整实名和证件号码',
  offline_access: '在你离开后继续访问已授权信息',
  service: '访问服务端 API',
}

function scopeLabel(scope) {
  return builtInScopeLabels[scope.name] || scope.display_name || scope.name
}

function rememberContext(data) {
  if (data?.client) authorization.value = { client: data.client, scopes: data.scopes || [], consent_required: true }
}

function notifyPopupError(error, description) {
  if (!popupDisplay.value || window.parent === window || !document.referrer) return
  try {
    const parentOrigin = new URL(document.referrer).origin
    window.parent.postMessage({ type: 'moo-oauth-error', result: { error, description } }, parentOrigin)
  } catch {
    // 没有可信父页面来源时只在当前页展示错误，绝不向任意 origin 广播。
  }
}

async function inspect() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await inspectAuthorization(parameters())
    authorization.value = response.data
    signedIn.value = true
    if (!auth.user) await auth.loadCurrentUser()
    if (!authorization.value.consent_required) decide('approve')
  } catch (error) {
    const data = error.response?.data
    if (error.response?.status === 401) {
      signedIn.value = false
      rememberContext(data)
    } else {
      errorMessage.value = data?.error_description || error.userMessage || '授权请求无效。'
      notifyPopupError(data?.error || 'invalid_request', errorMessage.value)
    }
  } finally { loading.value = false }
}

async function signIn() {
  if (!form.identifier.trim() || !form.password) {
    errorMessage.value = '请输入用户名或邮箱以及密码。'
    return
  }
  signingIn.value = true
  errorMessage.value = ''
  try {
    await auth.signIn(form)
    await inspect()
  } catch (error) {
    errorMessage.value = error.userMessage || '账号或密码错误。'
  } finally { signingIn.value = false }
}

function decide(decision) {
  deciding.value = true
  const post = document.createElement('form')
  post.method = 'POST'
  post.action = '/oauth/authorize'
  for (const [name, value] of Object.entries(parameters(decision))) {
    const input = document.createElement('input')
    input.type = 'hidden'
    input.name = name
    input.value = value
    post.appendChild(input)
  }
  document.body.appendChild(post)
  post.submit()
}

watch(() => auth.user?.avatar_url, () => { avatarLoadFailed.value = false })

onMounted(inspect)
</script>

<template>
  <main class="embedded-authorize" :class="{ 'is-popup': popupDisplay }">
    <header v-if="!popupDisplay" class="embedded-brand"><img :src="logo" alt="" /><strong>哞哞通行证</strong></header>
    <n-spin :show="loading">
      <section class="authorize-content">
        <div class="login-panel">
          <template v-if="!signedIn">
            <div class="flow-heading"><div><h1>账号登录</h1></div></div>
            <div class="login-fields" @keyup.enter="signIn">
              <n-input v-model:value="form.identifier" placeholder="用户名或邮箱" autocomplete="username" />
              <n-input v-model:value="form.password" type="password" show-password-on="click" placeholder="密码" autocomplete="current-password" />
            </div>
            <p class="authorize-error" :class="{ 'is-empty': !errorMessage }" aria-live="polite">{{ errorMessage || '暂无错误' }}</p>
            <n-button type="primary" block :loading="signingIn" @click="signIn">登录</n-button>
            <nav class="login-links"><a href="/forgot-password" target="_blank">找回密码</a><a href="/register" target="_blank">注册账号</a><a href="/privacy" target="_blank">隐私政策</a></nav>
          </template>
          <template v-else>
            <div class="flow-heading"><div><h1>确认授权</h1></div></div>
            <div class="signed-account">
              <span><img v-if="avatarUrl" :src="avatarUrl" alt="" @error="avatarLoadFailed = true" /><b v-else>{{ accountInitials }}</b></span>
              <div><strong>{{ accountDisplayName }}</strong><small>{{ auth.user?.email || `@${auth.user?.username}` }}</small></div>
            </div>
            <p class="authorize-error" :class="{ 'is-empty': !errorMessage }" aria-live="polite">{{ errorMessage || '暂无错误' }}</p>
            <div class="decision-actions"><n-button size="large" :disabled="deciding" @click="decide('deny')">拒绝</n-button><n-button type="primary" size="large" :loading="deciding" @click="decide('approve')">同意授权</n-button></div>
          </template>
        </div>
        <aside class="permission-panel">
          <div class="permission-intro"><div><b>{{ clientDisplayName }}</b><span>将获取以下权限</span></div></div>
          <div class="permission-list">
            <div v-for="scope in authorization?.scopes || []" :key="scope.name" class="permission-item" :class="{ 'is-sensitive': scope.name === 'realname_full' }">
              <n-icon class="permission-icon" :component="scope.name === 'realname_full' ? WarningOutline : CheckmarkCircleOutline" />
              <strong>{{ scopeLabel(scope) }}</strong>
            </div>
          </div>
          <n-alert v-if="authorization?.scopes?.some((scope) => scope.name === 'realname_full')" type="warning" :show-icon="false" class="sensitive-warning">这是高敏感权限，请确认应用可信。</n-alert>
        </aside>
      </section>
    </n-spin>
  </main>
</template>

<style scoped>
.embedded-authorize{min-height:100vh;color:var(--color-text-primary);background:var(--color-bg-surface)}
.embedded-authorize.is-popup{height:100vh;min-height:0;overflow:hidden}
.embedded-brand{display:flex;height:58px;padding:0 28px 0 max(24px,calc((100vw - 720px)/2));box-sizing:border-box;align-items:center;gap:9px;border-bottom:1px solid var(--color-border)}
.embedded-brand img{width:30px;height:30px;object-fit:contain}
.embedded-brand strong{font-size:var(--font-size-md)}
.authorize-content{display:grid;width:720px;max-width:calc(100vw - 32px);min-height:362px;margin:18px auto 0;box-sizing:border-box;grid-template-columns:minmax(0,1fr) 275px}
.is-popup .authorize-content{height:100%;min-height:0;margin-top:0;padding-top:18px}
.login-panel{padding:2px 42px 18px 10px}
.login-panel>*{width:280px;max-width:100%;margin-right:auto;margin-left:auto}
.login-panel h1{margin:0;font-size:var(--font-size-lg);font-weight:600}
.flow-heading{display:flex;min-height:34px;margin-bottom:12px;align-items:center;justify-content:flex-start}
.login-fields{display:grid;gap:9px}
.login-fields :deep(.n-input){height:38px;border-radius:var(--radius-sm)}
.login-fields :deep(.n-input-wrapper){padding:0 12px}
.login-panel>.authorize-error{min-height:18px;margin:8px auto 0!important;color:var(--color-error)!important;text-align:left;font-size:var(--font-size-xs);line-height:18px}
.login-panel>.authorize-error.is-empty{visibility:hidden}
.login-panel>.n-button{height:40px;margin-top:8px;border-radius:var(--radius-sm);font-size:var(--font-size-base);font-weight:500}
.login-links{display:flex;margin-top:21px;justify-content:center}
.login-links a{padding:0 16px;color:var(--color-text-secondary);font-size:var(--font-size-xs);text-decoration:none}
.login-links a+a{border-left:1px solid var(--color-border)}
.login-links a:hover{color:var(--color-primary)}
.signed-account{display:flex;margin:20px auto;padding:14px;box-sizing:border-box;align-items:center;gap:12px;border-radius:var(--radius-lg);background:var(--color-bg-subtle);font-size:var(--font-size-sm)}
.signed-account>span{display:grid;width:42px;height:42px;place-items:center;border-radius:50%;color:var(--color-text-inverse);background:var(--color-primary);font-weight:600;flex:none}
.signed-account>span{overflow:hidden}
.signed-account>span img{display:block;width:100%;height:100%;object-fit:cover}
.signed-account>span b{font:inherit}
.signed-account>div{display:grid;min-width:0}
.signed-account strong,.signed-account small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.signed-account strong{font-size:var(--font-size-sm)}
.signed-account small{color:var(--color-text-tertiary);font-size:var(--font-size-xs)}
.decision-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.permission-panel{padding:21px 8px 18px 28px}
.permission-intro>div{display:grid;gap:3px}
.permission-intro b{color:var(--color-text-primary);font-size:var(--font-size-sm);font-weight:600}
.permission-intro span{color:var(--color-text-tertiary);font-size:var(--font-size-xs)}
.permission-list{display:grid;margin:17px 0 14px;gap:11px}
.permission-item{display:flex;align-items:center;gap:8px}
.permission-icon{color:var(--color-primary);font-size:18px;flex:none}
.permission-item strong{color:var(--color-text-secondary);font-size:var(--font-size-sm);font-weight:400}
.permission-item.is-sensitive .permission-icon{color:var(--color-error)}
.permission-item.is-sensitive strong{color:var(--color-text-primary);font-weight:600}
.sensitive-warning{margin-top:12px;font-size:var(--font-size-xs)}
@media(max-width:680px){.embedded-brand{justify-content:flex-start}.authorize-content{display:block;width:auto;margin-top:14px}.is-popup .authorize-content{overflow:auto}.login-panel{padding:8px 8px 24px}.permission-panel{padding:22px 8px;border-top:1px solid var(--color-border)}}
</style>
