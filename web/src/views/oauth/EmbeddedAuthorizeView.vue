<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { NButton, NInput, NSpin } from 'naive-ui'
import { inspectAuthorization } from '../../api/oauth.js'
import { useAuthStore } from '../../stores/auth.js'
import logo from '../../assets/logo.png'

const route = useRoute()
const popupDisplay = computed(() => route.query.display === 'popup')
const clientDisplayName = computed(() => (authorization.value?.client?.name || '该应用').replace(/\s+-\s+用户登录$/, ''))
const accountDisplayName = computed(() => auth.user?.display_name || auth.user?.username || auth.user?.email || '哞哞用户')
const accountInitials = computed(() => accountDisplayName.value.slice(0, 1).toUpperCase())
const auth = useAuthStore()
const loading = ref(true)
const signingIn = ref(false)
const deciding = ref(false)
const errorMessage = ref('')
const authorization = ref()
const signedIn = ref(false)
const form = reactive({ identifier: '', password: '' })
const parameters = (decision) => Object.fromEntries([
  ...Object.entries(route.query).filter(([, value]) => typeof value === 'string'),
  ...(decision ? [['decision', decision]] : []),
])

function rememberContext(data) {
  if (data?.client) authorization.value = { client: data.client, scopes: data.scopes || [], consent_required: true }
}

async function inspect() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await inspectAuthorization(parameters())
    authorization.value = response.data
    signedIn.value = true
    if (!authorization.value.consent_required) decide('approve')
  } catch (error) {
    const data = error.response?.data
    if (error.response?.status === 401) {
      signedIn.value = false
      rememberContext(data)
    } else {
      errorMessage.value = data?.error_description || error.userMessage || '授权请求无效。'
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
            <div class="signed-account"><span>{{ accountInitials }}</span><div><strong>{{ accountDisplayName }}</strong><small>{{ auth.user?.email }}</small></div></div>
            <p class="authorize-error" :class="{ 'is-empty': !errorMessage }" aria-live="polite">{{ errorMessage || '暂无错误' }}</p>
            <div class="decision-actions"><n-button size="large" :disabled="deciding" @click="decide('deny')">拒绝</n-button><n-button type="primary" size="large" :loading="deciding" @click="decide('approve')">同意授权</n-button></div>
          </template>
        </div>
        <aside class="permission-panel">
          <div class="permission-intro"><div><b>{{ clientDisplayName }}</b><span>将获取以下权限</span></div></div>
          <div class="permission-list"><div v-for="scope in authorization?.scopes || []" :key="scope.name" class="permission-item"><span>✓</span><strong>{{ scope.name === 'openid' ? '使用你的哞哞账号身份' : scope.name === 'profile' ? '读取你的基础资料' : scope.display_name }}</strong></div></div>
        </aside>
      </section>
    </n-spin>
  </main>
</template>

<style scoped>
.embedded-authorize{min-height:100vh;color:var(--color-text-primary);background:var(--color-bg-surface)}.embedded-brand{display:flex;height:58px;padding:0 28px;align-items:center;justify-content:center;gap:9px;border-bottom:1px solid var(--color-border)}.embedded-brand img{width:30px;height:30px;object-fit:contain}.embedded-brand strong{font-size:var(--font-size-md)}.authorize-content{display:grid;width:min(760px,calc(100vw - 40px));min-height:430px;margin:20px auto 0;grid-template-columns:minmax(0,1fr) 290px}.login-panel{padding:8px 46px 30px 14px;border-right:1px solid var(--color-border)}.login-panel h1{margin:0;text-align:center;font-size:var(--font-size-lg);font-weight:600}.login-panel>p{margin:7px 0 24px;text-align:center;color:var(--color-text-tertiary);font-size:var(--font-size-xs)}.login-fields{display:grid;gap:10px}.login-panel>.n-button{margin-top:22px}.login-links{display:flex;margin-top:28px;justify-content:center}.login-links a{padding:0 16px;color:var(--color-text-secondary);font-size:var(--font-size-xs);text-decoration:none}.login-links a+a{border-left:1px solid var(--color-border)}.login-links a:hover{color:var(--color-primary)}.authorize-error{margin:10px 0 0!important;color:#d03050!important;text-align:left!important}.signed-account{margin:30px 0;padding:14px;border-radius:var(--radius-lg);background:var(--color-bg-subtle);font-size:var(--font-size-sm)}.decision-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px}.permission-panel{padding:66px 12px 30px 32px}.application-name{display:flex;align-items:center;gap:11px}.application-name>span{display:grid;width:38px;height:38px;place-items:center;border-radius:var(--radius-lg);color:var(--color-primary);background:var(--color-primary-soft);font-weight:600}.application-name>div{display:grid}.application-name strong{font-size:var(--font-size-sm)}.application-name small,.permission-panel>p{color:var(--color-text-tertiary);font-size:var(--font-size-xs)}.permission-list{display:grid;margin:22px 0 18px;gap:13px}.permission-list :deep(.n-checkbox__label){display:grid}.permission-list strong{font-size:var(--font-size-sm)}.permission-list small{color:var(--color-text-tertiary);font-size:var(--font-size-xs);font-weight:400}.permission-panel>p{margin:7px 0;line-height:1.6}@media(max-width:680px){.embedded-brand{justify-content:flex-start}.authorize-content{display:block;margin-top:14px}.login-panel{padding:8px 8px 24px;border-right:0}.permission-panel{padding:22px 8px;border-top:1px solid var(--color-border)}}
.authorize-error{color:var(--color-error)!important}
.flow-heading{display:flex;min-height:64px;margin-bottom:18px;align-items:center;justify-content:center;gap:12px}.flow-heading>img{width:42px;height:42px;object-fit:contain}.flow-heading>div{display:grid}.flow-heading h1{margin:0;text-align:left}.flow-heading p{margin:4px 0 0;color:var(--color-text-tertiary);font-size:var(--font-size-xs)}
.embedded-brand{justify-content:flex-start;padding-left:max(24px,calc((100vw - 760px)/2));box-sizing:border-box}.permission-intro{display:flex;align-items:flex-start;gap:7px;font-size:var(--font-size-xs)}.permission-intro>strong{color:var(--color-primary);white-space:nowrap}.permission-intro p{margin:0;color:var(--color-text-secondary);line-height:1.7}.permission-intro b{color:var(--color-primary);font-weight:500}.application-name{display:none}
.embedded-authorize.is-popup{height:100vh;min-height:0;overflow:hidden}.is-popup .authorize-content{width:720px;max-width:calc(100vw - 32px);height:100%;min-height:0;margin:0 auto;padding-top:16px;box-sizing:border-box;grid-template-columns:minmax(0,1fr) 275px}.is-popup .login-panel{padding:2px 42px 18px 10px}.is-popup .permission-panel{padding:51px 8px 18px 28px}.is-popup .flow-heading{min-height:54px;margin-bottom:12px}.is-popup .login-fields{gap:9px}.is-popup .login-panel>.n-button{margin-top:18px}.is-popup .login-links{margin-top:21px}.is-popup .permission-list{margin:17px 0 14px;gap:11px}
.is-popup .login-panel>*{width:320px;max-width:100%;margin-right:auto;margin-left:auto}.is-popup .login-panel>.authorize-error{margin-top:10px!important}.is-popup .decision-actions{width:320px;max-width:100%;margin-right:auto;margin-left:auto}
.is-popup .login-panel{border-right:0}.is-popup .flow-heading{justify-content:flex-start}.is-popup .permission-panel{padding-top:45px}.signed-account{display:flex;align-items:center;gap:12px}.signed-account>span{display:grid;width:42px;height:42px;place-items:center;border-radius:50%;color:var(--color-text-inverse);background:var(--color-primary);font-weight:600}.signed-account>div{display:grid;min-width:0}.signed-account strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:var(--font-size-sm)}.signed-account small{overflow:hidden;color:var(--color-text-tertiary);text-overflow:ellipsis;white-space:nowrap;font-size:var(--font-size-xs)}
.login-panel .flow-heading{justify-content:flex-start}
.is-popup .authorize-content{padding-top:18px}.is-popup .login-panel>*{width:280px}.is-popup .decision-actions{width:280px}.is-popup .flow-heading{min-height:34px;margin-bottom:12px}.is-popup .permission-panel{padding-top:21px}.is-popup .login-fields :deep(.n-input){height:38px;border-radius:var(--radius-sm)}.is-popup .login-fields :deep(.n-input-wrapper){padding:0 12px}.is-popup .login-panel>.n-button{height:40px;border-radius:var(--radius-sm);font-size:var(--font-size-base);font-weight:500}.permission-intro{display:block}.permission-intro>div{display:grid;gap:3px}.permission-intro b{color:var(--color-text-primary);font-size:var(--font-size-sm);font-weight:600}.permission-intro span{color:var(--color-text-tertiary);font-size:var(--font-size-xs)}.permission-list{gap:14px}.permission-item{display:flex;align-items:center;gap:8px}.permission-item>span{display:grid;width:17px;height:17px;place-items:center;border-radius:50%;color:var(--color-text-inverse);background:var(--color-primary);font-size:10px;font-weight:700;flex:none}.permission-item strong{color:var(--color-text-secondary);font-size:var(--font-size-sm);font-weight:400}.login-panel>.authorize-error{width:280px;max-width:100%;min-height:18px;margin:8px auto 0!important;color:var(--color-error)!important;text-align:left!important;font-size:var(--font-size-xs);line-height:18px}.login-panel>.authorize-error.is-empty{visibility:hidden}.login-panel>.authorize-error+.n-button{margin-top:8px}
</style>
