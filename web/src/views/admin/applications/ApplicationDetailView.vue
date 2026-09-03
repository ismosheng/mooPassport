<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { NAlert, NButton, NCard, NCheckbox, NCheckboxGroup, NDescriptions, NDescriptionsItem, NInput, NModal, NScrollbar, NSpace, NSpin, NTabPane, NTabs, NTag, NTimeline, NTimelineItem, NUpload, useDialog, useMessage } from 'naive-ui'
import hljs from 'highlight.js/lib/core'
import javascript from 'highlight.js/lib/languages/javascript'
import php from 'highlight.js/lib/languages/php'
import xml from 'highlight.js/lib/languages/xml'
import { useRoute, useRouter } from 'vue-router'
import { getApplication, rotateOAuthClientSecret, updateApplication, updateOAuthClient, updateOAuthClientStatus, uploadApplicationLogo } from '../../../api/applications.js'
import { useAdminAccessStore } from '../../../stores/adminAccess.js'

hljs.registerLanguage('javascript', javascript)
hljs.registerLanguage('php', php)
hljs.registerLanguage('html', xml)

const scopeDefinitions = [
  { name: 'openid', title: '身份标识', description: '返回 OIDC ID Token，用于确认用户身份。' },
  { name: 'profile', title: '基础资料', description: '读取用户公开 ID、显示名称和头像。' },
  { name: 'email', title: '邮箱信息', description: '读取用户邮箱及邮箱验证状态。' },
  { name: 'offline_access', title: '离线访问', description: '允许签发 Refresh Token，在用户离开后续期。' },
]
const route = useRoute(), router = useRouter(), message = useMessage(), dialog = useDialog()
const access = useAdminAccessStore()
const loading = ref(true), application = ref(null), clients = ref([]), secret = ref(''), showSecret = ref(false)
const activeGuide = ref('guide-overview')
const guideSections = ['guide-overview', 'guide-sdk', 'guide-callback', 'guide-token', 'guide-user', 'guide-scope', 'guide-endpoints']
const editingBasic = ref(false), savingBasic = ref(false), uploadingLogo = ref(false)
const basicForm = reactive({ name: '', description: '', logo_url: '' })
const webOrigin = window.location.origin
const apiOrigin = webOrigin.replace(':3000', ':8787')
const loginClient = computed(() => clients.value.find((item) => item.application_type !== 'service'))
const callbackUri = computed(() => loginClient.value?.redirect_uris?.[0] || 'https://your-app.example/oauth/callback')
const sdkCode = computed(() => `<script src="${webOrigin}/sdk/moo-auth-sdk.js?v=1.1.0"><\/script>\n<script>\nconst auth = MooAuth.init({\n  clientId: '${loginClient.value?.client_id || 'YOUR_APP_ID'}',\n  redirectUri: '${callbackUri.value}',\n  scope: 'openid profile'\n})\n\n// 弹框登录\ndocument.querySelector('#popup-login').onclick = () => auth.login({ mode: 'popup' })\n\n// 跳转登录\ndocument.querySelector('#redirect-login').onclick = () => auth.login({ mode: 'redirect' })\n<\/script>`)
const callbackCode = `const result = MooAuth.handleCallback()\n\nif (result?.code) {\n  await fetch('/api/auth/moo/callback', {\n    method: 'POST',\n    headers: { 'Content-Type': 'application/json' },\n    credentials: 'include',\n    body: JSON.stringify({\n      code: result.code,\n      code_verifier: result.verifier\n    })\n  })\n}`
const tokenCode = computed(() => `<?php\n// 必须在业务后端执行，AppSecret 不得发送到浏览器。\n$response = Http::asForm()->post('${apiOrigin}/oauth/token', [\n    'grant_type' => 'authorization_code',\n    'client_id' => '${loginClient.value?.client_id || 'YOUR_APP_ID'}',\n    'client_secret' => getenv('MOO_APP_SECRET'),\n    'code' => $request->input('code'),\n    'code_verifier' => $request->input('code_verifier'),\n    'redirect_uri' => '${callbackUri.value}',\n]);\n\n$tokens = $response->throw()->json();`)
const userInfoCode = computed(() => `$response = Http::withToken($tokens['access_token'])\n    ->get('${apiOrigin}/oauth/userinfo');\n\n$user = $response->throw()->json();\n// 使用 $user['sub'] 关联本地账号，不要使用可变昵称作为唯一标识。`)

async function load() {
  loading.value = true
  try {
    const response = await getApplication(route.params.id)
    application.value = response.data.data
    clients.value = application.value.clients
    Object.assign(basicForm, { name: application.value.name, description: application.value.description || '', logo_url: application.value.logo_url || '' })
  } catch (error) { message.error(error.userMessage); router.replace('/admin/applications') }
  finally { loading.value = false }
}
async function saveClient(client) {
  try { await updateOAuthClient(client.client_id, { redirect_uris: client.redirect_uris, scopes: client.scopes }); message.success('客户端配置已保存'); await load() }
  catch (error) { message.error(error.userMessage) }
}
function rotateSecret(client) {
  dialog.warning({ title: '轮换 AppSecret', content: '旧 AppSecret 将立即失效，依赖它的服务必须同步更新。', positiveText: '确认轮换', negativeText: '取消', onPositiveClick: async () => {
    try { const response = await rotateOAuthClientSecret(client.client_id); secret.value = response.data.data.client_secret; showSecret.value = true } catch (error) { message.error(error.userMessage) }
  } })
}
async function toggleStatus(client) {
  try { await updateOAuthClientStatus(client.client_id, client.status === 'active' ? 'disabled' : 'active'); message.success('客户端状态已更新'); await load() }
  catch (error) { message.error(error.userMessage) }
}
async function copy(value) { await navigator.clipboard.writeText(value); message.success('已复制') }
function highlighted(code, language) { return hljs.highlight(code || '', { language }).value }
async function saveBasic() {
  savingBasic.value = true
  try { await updateApplication(application.value.id, { name: basicForm.name.trim(), description: basicForm.description.trim() || null, logo_url: basicForm.logo_url || null }); message.success('应用基础信息已保存'); editingBasic.value = false; await load() }
  catch (error) { message.error(error.userMessage) }
  finally { savingBasic.value = false }
}
async function uploadLogo({ file }) {
  if (!file?.file) return
  uploadingLogo.value = true
  try { const response = await uploadApplicationLogo(file.file); basicForm.logo_url = response.data.data.logo_url; message.success('图标上传成功') }
  catch (error) { message.error(error.userMessage) }
  finally { uploadingLogo.value = false }
}
function guideScroller() { return document.querySelector('.guide-content') }
function updateActiveGuide() {
  const scroller = guideScroller()
  if (!scroller) return
  const threshold = scroller.getBoundingClientRect().top + 36
  let current = guideSections[0]
  for (const id of guideSections) {
    const element = document.getElementById(id)
    if (element && element.getBoundingClientRect().top <= threshold) current = id
  }
  activeGuide.value = current
}
function goToGuide(id) {
  activeGuide.value = id
  const scroller = guideScroller()
  const target = document.getElementById(id)
  if (!scroller || !target) return
  const top = target.getBoundingClientRect().top - scroller.getBoundingClientRect().top + scroller.scrollTop - 8
  scroller.scrollTo({ top, behavior: 'smooth' })
}
onMounted(() => { load(); guideScroller()?.addEventListener('scroll', updateActiveGuide, { passive: true }) })
onUnmounted(() => guideScroller()?.removeEventListener('scroll', updateActiveGuide))
</script>

<template>
  <div class="detail-page"><n-spin :show="loading"><template v-if="application">
    <header class="detail-header"><template v-if="!editingBasic"><div class="app-brand"><span><img v-if="application.logo_url" :src="application.logo_url" alt="" /><b v-else>{{ application.name.slice(0,1) }}</b></span><div><n-space align="center"><h1>{{ application.name }}</h1><n-tag :type="application.status === 'active' ? 'success' : 'default'">{{ application.status === 'active' ? '正常' : '已禁用' }}</n-tag></n-space><p>{{ application.description || '暂无应用说明' }}</p><div class="top-appids"><span v-for="client in clients" :key="client.client_id"><em>{{ client.application_type === 'service' ? 'API AppID' : '登录 AppID' }}</em><code class="inline-code">{{ client.client_id }}</code><n-button text type="primary" size="tiny" @click="copy(client.client_id)">复制</n-button></span></div></div></div><n-space><n-button v-if="access.has('admin.applications.update')" @click="editingBasic=true">编辑基础信息</n-button><n-button @click="router.push('/admin/applications')">返回列表</n-button></n-space></template><template v-else><div class="basic-editor"><div class="basic-logo"><span><img v-if="basicForm.logo_url" :src="basicForm.logo_url" alt="" /><b v-else>{{ basicForm.name.slice(0,1) || 'A' }}</b></span><n-upload :show-file-list="false" accept="image/png,image/jpeg,image/webp" :custom-request="uploadLogo"><n-button size="small" :loading="uploadingLogo">更换图标</n-button></n-upload></div><div class="basic-fields"><n-input v-model:value="basicForm.name" maxlength="100" placeholder="应用名称" /><n-input v-model:value="basicForm.description" type="textarea" maxlength="500" :autosize="{ minRows: 2, maxRows: 3 }" placeholder="应用说明" /></div></div><n-space><n-button @click="editingBasic=false">取消</n-button><n-button type="primary" :loading="savingBasic" :disabled="!basicForm.name.trim()" @click="saveBasic">保存基础信息</n-button></n-space></template></header>
    <n-tabs type="line" animated>
      <n-tab-pane name="config" tab="客户端配置"><div class="client-grid">
        <n-card v-for="client in clients" :key="client.client_id" :title="client.application_type === 'service' ? '服务端 API' : '用户登录'"><template #header-extra><n-tag :type="client.status === 'active' ? 'success' : 'default'">{{ client.status === 'active' ? '已启用' : '已禁用' }}</n-tag></template>
          <n-descriptions label-placement="left" :column="1"><n-descriptions-item label="AppID"><code class="inline-code app-id">{{ client.client_id }}</code> <n-button text type="primary" @click="copy(client.client_id)">复制</n-button></n-descriptions-item><n-descriptions-item label="客户端类型">{{ client.client_type === 'confidential' ? '机密客户端' : '公开客户端' }}</n-descriptions-item><n-descriptions-item label="认证方式"><code class="inline-code">{{ client.token_endpoint_auth_method }}</code></n-descriptions-item><n-descriptions-item label="PKCE">{{ client.require_pkce ? '必须使用 S256' : '不适用' }}</n-descriptions-item></n-descriptions>
          <template v-if="client.application_type !== 'service'"><label>登录回调地址（每行一个）</label><n-input type="textarea" :value="client.redirect_uris.join('\n')" @update:value="client.redirect_uris=$event.split(/\r?\n/).map(v=>v.trim()).filter(Boolean)" /><label>用户授权范围</label><n-checkbox-group v-model:value="client.scopes" class="scope-grid"><n-checkbox v-for="scope in scopeDefinitions" :key="scope.name" :value="scope.name"><span class="scope-copy"><strong>{{ scope.title }}</strong><small>{{ scope.name }}</small><em>{{ scope.description }}</em></span></n-checkbox></n-checkbox-group></template>
          <div v-if="access.has('admin.applications.update') || access.has('admin.applications.status.update') || access.has('admin.applications.secret.rotate')" class="card-actions"><n-button v-if="access.has('admin.applications.status.update')" @click="toggleStatus(client)">{{ client.status === 'active' ? '禁用' : '启用' }}</n-button><n-button v-if="client.client_type === 'confidential' && access.has('admin.applications.secret.rotate')" @click="rotateSecret(client)">轮换 AppSecret</n-button><n-button v-if="client.application_type !== 'service' && access.has('admin.applications.update')" type="primary" @click="saveClient(client)">保存配置</n-button></div>
        </n-card></div></n-tab-pane>
      <n-tab-pane name="guide" tab="接入文档"><div class="guide-layout">
        <n-card size="small" class="guide-directory" title="接入目录"><n-timeline><n-timeline-item v-for="item in [{id:'guide-overview',label:'接入流程'},{id:'guide-sdk',label:'1. 引入 SDK'},{id:'guide-callback',label:'2. 处理回调'},{id:'guide-token',label:'3. 换取 Token'},{id:'guide-user',label:'4. 获取用户资料'},{id:'guide-scope',label:'5. Scope 权限'},{id:'guide-endpoints',label:'协议端点'}]" :key="item.id" :type="activeGuide === item.id ? 'info' : 'default'"><n-button text @click="goToGuide(item.id)">{{ item.label }}</n-button></n-timeline-item></n-timeline></n-card>
        <div class="guide-content">
          <n-card id="guide-overview" class="guide-section" title="接入流程"><p>SDK 发起授权 → 回调取得一次性 Code → 业务后端换 Token → 使用 Access Token 获取用户信息 → 建立业务站自己的登录状态。</p><div class="flow-steps"><n-tag type="info">前端发起授权</n-tag><i>→</i><n-tag type="info">通行证登录</n-tag><i>→</i><n-tag type="info">后端换 Token</n-tag><i>→</i><n-tag type="info">建立业务会话</n-tag></div></n-card>
          <n-card id="guide-sdk" class="guide-section" title="1. 引入 SDK 并发起登录"><p>弹框和页面跳转使用相同的 Authorization Code + PKCE S256，只需要切换 <code>mode</code>。</p><div class="code-block"><header><span>HTML / JavaScript</span><n-button size="tiny" @click="copy(sdkCode)">复制代码</n-button></header><n-scrollbar x-scrollable><pre><code v-html="highlighted(sdkCode, 'html')"></code></pre></n-scrollbar></div></n-card>
          <n-card id="guide-callback" class="guide-section" title="2. 处理前端回调"><p>回调路由属于接入应用，state 校验、PKCE verifier 和弹框通信由 SDK 统一处理。</p><div class="code-block"><header><span>JavaScript</span><n-button size="tiny" @click="copy(callbackCode)">复制代码</n-button></header><n-scrollbar x-scrollable><pre><code v-html="highlighted(callbackCode, 'javascript')"></code></pre></n-scrollbar></div></n-card>
          <n-card id="guide-token" class="guide-section" title="3. 业务后端用 Code 换 Token"><n-alert type="warning" :show-icon="false">AppSecret 只能保存在服务端环境变量中。SPA 和原生公开客户端不使用 AppSecret。</n-alert><div class="code-block"><header><span>PHP</span><n-button size="tiny" @click="copy(tokenCode)">复制代码</n-button></header><n-scrollbar x-scrollable><pre><code v-html="highlighted(tokenCode, 'php')"></code></pre></n-scrollbar></div></n-card>
          <n-card id="guide-user" class="guide-section" title="4. 获取当前用户资料"><p>使用 Access Token 请求 UserInfo，并通过稳定的 <code>sub</code> 字段关联本地账号。</p><div class="code-block"><header><span>PHP</span><n-button size="tiny" @click="copy(userInfoCode)">复制代码</n-button></header><n-scrollbar x-scrollable><pre><code v-html="highlighted(userInfoCode, 'php')"></code></pre></n-scrollbar></div></n-card>
          <n-card id="guide-scope" class="guide-section" title="5. Scope 权限说明"><div class="scope-reference"><n-card v-for="scope in scopeDefinitions" :key="scope.name" size="small"><strong>{{ scope.title }}</strong><code>{{ scope.name }}</code><p>{{ scope.description }}</p></n-card></div></n-card>
          <n-card id="guide-endpoints" class="guide-section" title="协议端点"><n-descriptions class="endpoint-list" :column="1" label-placement="left" bordered><n-descriptions-item label="授权端点"><div class="endpoint-value"><code>{{ webOrigin }}/connect/authorize</code><n-button text type="primary" @click="copy(`${webOrigin}/connect/authorize`)">复制</n-button></div></n-descriptions-item><n-descriptions-item label="Token 端点"><div class="endpoint-value"><code>{{ apiOrigin }}/oauth/token</code><n-button text type="primary" @click="copy(`${apiOrigin}/oauth/token`)">复制</n-button></div></n-descriptions-item><n-descriptions-item label="UserInfo 端点"><div class="endpoint-value"><code>{{ apiOrigin }}/oauth/userinfo</code><n-button text type="primary" @click="copy(`${apiOrigin}/oauth/userinfo`)">复制</n-button></div></n-descriptions-item><n-descriptions-item label="OIDC 配置"><div class="endpoint-value"><code>{{ apiOrigin }}/.well-known/openid-configuration</code><n-button text type="primary" @click="copy(`${apiOrigin}/.well-known/openid-configuration`)">复制</n-button></div></n-descriptions-item></n-descriptions></n-card>
        </div>
      </div></n-tab-pane>
    </n-tabs>
  </template></n-spin>
  <n-modal v-model:show="showSecret" preset="card" title="新的 AppSecret" style="width:520px;max-width:calc(100vw - 32px)"><n-alert type="warning">仅展示这一次，请立即安全保存。</n-alert><n-input :value="secret" readonly class="secret-input" /><template #footer><n-button type="primary" @click="copy(secret)">复制 AppSecret</n-button></template></n-modal></div>
</template>

<style scoped>
.detail-page{min-height:100%;padding:12px 0}.detail-header{display:flex;padding:18px 20px;align-items:center;justify-content:space-between;border:1px solid var(--admin-border);border-radius:var(--admin-radius);background:var(--color-bg-surface)}.app-brand{display:flex;align-items:center;gap:14px}.app-brand>span{display:grid;width:54px;height:54px;overflow:hidden;place-items:center;border-radius:var(--radius-lg);color:var(--color-primary);background:var(--color-primary-soft)}.app-brand img{width:100%;height:100%;object-fit:cover}.app-brand h1{margin:0;font-size:var(--admin-font-xl)}.app-brand p,.app-brand small{margin:3px 0 0;color:var(--admin-muted);font-size:var(--admin-font-xs)}.detail-page :deep(.n-tabs){margin-top:12px}.detail-page :deep(.n-tab-pane){display:grid;gap:12px}.client-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:12px}.client-grid label{display:block;margin:16px 0 7px;color:var(--admin-heading);font-size:var(--admin-font-sm);font-weight:600}.card-actions{display:flex;margin-top:18px;justify-content:flex-end;gap:8px}.scope-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px 16px}.scope-grid :deep(.n-checkbox){align-items:flex-start}.scope-copy{display:grid;margin-left:4px}.scope-copy strong{font-size:var(--admin-font-sm)}.scope-copy small{color:var(--color-primary);font-size:var(--admin-font-xs)}.scope-copy em{margin-top:2px;color:var(--admin-muted);font-size:var(--admin-font-xs);font-style:normal}.detail-page :deep(.n-code){margin-top:12px;padding:14px;overflow:auto;border:1px solid var(--admin-border);border-radius:var(--admin-radius);background:var(--color-bg-page);font-size:12px}.detail-page :deep(.hljs-keyword),.detail-page :deep(.hljs-selector-tag){color:#7c3aed}.detail-page :deep(.hljs-string),.detail-page :deep(.hljs-attribute){color:#087f5b}.detail-page :deep(.hljs-title),.detail-page :deep(.hljs-function){color:#1d4ed8}.detail-page :deep(.hljs-comment){color:#8491a5}.copy-code{margin-top:12px}.scope-reference{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}.scope-reference>div{padding:12px;border:1px solid var(--admin-border);border-radius:var(--admin-radius)}.scope-reference strong{display:block}.scope-reference code{display:inline-block;margin-top:4px;color:var(--color-primary)}.scope-reference p{margin:6px 0 0;color:var(--admin-muted);font-size:var(--admin-font-xs);line-height:1.6}.secret-input{margin-top:14px}@media(max-width:700px){.detail-header{align-items:flex-start;gap:12px;flex-direction:column}.client-grid{grid-template-columns:1fr}.scope-grid{grid-template-columns:1fr}}
.guide-layout{display:grid;grid-template-columns:184px minmax(0,1fr);align-items:start;gap:14px}.guide-directory{position:sticky;top:118px;display:grid;padding:14px;border:1px solid var(--admin-border);border-radius:var(--admin-radius);background:var(--color-bg-surface)}.guide-directory strong{padding:0 8px 10px;color:var(--admin-heading);font-size:var(--admin-font-md)}.guide-directory a{padding:8px;border-radius:var(--radius-sm);color:var(--admin-muted);font-size:var(--admin-font-sm);text-decoration:none}.guide-directory a:hover{color:var(--color-primary);background:var(--color-primary-soft)}.guide-content{display:grid;min-width:0;gap:12px}.guide-section{scroll-margin-top:118px;padding:20px;border:1px solid var(--admin-border);border-radius:var(--admin-radius);background:var(--color-bg-surface)}.guide-section h2{margin:0;color:var(--admin-heading);font-size:var(--admin-font-lg)}.guide-section>p{margin:8px 0 0;color:var(--admin-muted);font-size:var(--admin-font-sm);line-height:1.75}.flow-steps{display:flex;margin-top:15px;align-items:center;gap:8px;flex-wrap:wrap}.flow-steps span{padding:7px 10px;border-radius:var(--radius-sm);color:var(--color-primary);background:var(--color-primary-soft);font-size:var(--admin-font-xs)}.flow-steps i{color:var(--admin-muted);font-style:normal}.code-block{margin-top:14px;overflow:hidden;border:1px solid var(--admin-border);border-radius:var(--admin-radius);background:var(--color-bg-page)}.code-block header{display:flex;height:38px;padding:0 8px 0 13px;align-items:center;justify-content:space-between;border-bottom:1px solid var(--admin-border);background:var(--color-bg-subtle)}.code-block header span{color:var(--admin-muted);font-size:var(--admin-font-xs);font-weight:600}.code-block header button{height:27px;padding:0 9px;border:1px solid var(--admin-border);border-radius:var(--radius-sm);background:var(--color-bg-surface);color:var(--admin-heading);font-family:inherit;font-size:var(--admin-font-xs);cursor:pointer}.code-block header button:hover{border-color:var(--color-primary);color:var(--color-primary)}.code-block pre{max-height:460px;margin:0;padding:16px;overflow:auto;tab-size:2}.code-block code{display:block;color:var(--color-text-primary);font-family:"SFMono-Regular",Consolas,"Liberation Mono",monospace;font-size:12px;line-height:1.7;white-space:pre}.code-block :deep(.hljs-keyword),.code-block :deep(.hljs-selector-tag){color:var(--color-primary)}.code-block :deep(.hljs-string),.code-block :deep(.hljs-attribute){color:var(--color-success)}.code-block :deep(.hljs-title),.code-block :deep(.hljs-function){color:var(--color-info)}.code-block :deep(.hljs-comment){color:var(--admin-muted)}@media(max-width:900px){.guide-layout{grid-template-columns:1fr}.guide-directory{position:static;grid-template-columns:repeat(3,1fr)}.guide-directory strong{grid-column:1/-1}}@media(max-width:600px){.guide-directory{grid-template-columns:1fr 1fr}.guide-section{padding:15px}.code-block pre{padding:13px}}
.guide-directory{display:block;padding:0}.guide-directory :deep(.n-card__content){padding-top:4px}.guide-directory :deep(.n-anchor-link__title){font-size:var(--admin-font-sm)}.guide-section{padding:0}.guide-section :deep(.n-card-header){padding-bottom:10px}.guide-section :deep(.n-card-header__main){font-size:var(--admin-font-lg);font-weight:600}.guide-section :deep(.n-card__content)>p{margin:0;color:var(--admin-muted);font-size:var(--admin-font-sm);line-height:1.75}.code-block :deep(.n-scrollbar){width:100%}.code-block :deep(.n-scrollbar-content){min-width:max-content}.code-block header .n-button{height:26px;padding:0 8px;background:var(--color-bg-surface)}.scope-reference>.n-card{padding:0}.scope-reference :deep(.n-card__content){padding:12px}@media(max-width:900px){.guide-directory :deep(.n-anchor){display:grid;grid-template-columns:repeat(3,1fr);gap:2px}}@media(max-width:600px){.guide-directory :deep(.n-anchor){grid-template-columns:1fr 1fr}}
.guide-layout{grid-template-columns:minmax(0,1fr) 200px;grid-template-areas:"content directory";gap:16px}.guide-directory{grid-area:directory;top:12px}.guide-content{grid-area:content}.inline-code,.guide-section :deep(p code){display:inline-block;padding:1px 6px;border:1px solid var(--admin-border);border-radius:var(--radius-sm);color:var(--color-primary);background:var(--color-primary-soft);font-family:"SFMono-Regular",Consolas,"Liberation Mono",monospace;font-size:.92em;line-height:1.55}.app-id{max-width:330px;overflow:hidden;text-overflow:ellipsis;vertical-align:middle;white-space:nowrap}.code-block :deep(.hljs-comment),.code-block :deep(.hljs-quote),.code-block :deep(.hljs-meta){color:var(--color-text-tertiary)}.code-block :deep(.hljs-keyword),.code-block :deep(.hljs-selector-tag),.code-block :deep(.hljs-subst),.code-block :deep(.hljs-tag),.code-block :deep(.hljs-name){color:var(--color-primary)}.code-block :deep(.hljs-string),.code-block :deep(.hljs-doctag),.code-block :deep(.hljs-regexp),.code-block :deep(.hljs-addition){color:var(--color-success)}.code-block :deep(.hljs-title),.code-block :deep(.hljs-section),.code-block :deep(.hljs-selector-id),.code-block :deep(.hljs-selector-class),.code-block :deep(.hljs-function){color:var(--color-primary-active)}.code-block :deep(.hljs-number),.code-block :deep(.hljs-literal),.code-block :deep(.hljs-variable),.code-block :deep(.hljs-template-variable),.code-block :deep(.hljs-attr),.code-block :deep(.hljs-attribute),.code-block :deep(.hljs-built_in),.code-block :deep(.hljs-type){color:var(--color-error)}.code-block :deep(.hljs-symbol),.code-block :deep(.hljs-bullet),.code-block :deep(.hljs-link){color:var(--color-text-secondary)}.code-block :deep(.hljs-emphasis){font-style:italic}.code-block :deep(.hljs-strong){font-weight:700}@media(max-width:900px){.guide-layout{grid-template-columns:1fr;grid-template-areas:"directory" "content"}.guide-directory{position:static}}
.guide-layout{grid-template-columns:200px minmax(0,1fr);grid-template-areas:"directory content"}.top-appids{display:flex;margin-top:9px;align-items:center;gap:8px 14px;flex-wrap:wrap}.top-appids>span{display:flex;align-items:center;gap:6px}.top-appids em{color:var(--admin-muted);font-size:var(--admin-font-xs);font-style:normal}.top-appids .inline-code{max-width:270px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.basic-editor{display:flex;min-width:0;align-items:flex-start;gap:14px;flex:1}.basic-logo{display:grid;justify-items:center;gap:7px}.basic-logo>span{display:grid;width:64px;height:64px;overflow:hidden;place-items:center;border:1px solid var(--admin-border);border-radius:var(--radius-lg);color:var(--color-primary);background:var(--color-primary-soft)}.basic-logo img{width:100%;height:100%;object-fit:cover}.basic-fields{display:grid;width:min(560px,100%);gap:9px}@media(max-width:900px){.guide-layout{grid-template-columns:1fr;grid-template-areas:"directory" "content"}}@media(max-width:700px){.basic-editor{width:100%;flex-direction:column}.basic-fields{width:100%}.top-appids>span{width:100%}}
.guide-directory{align-self:start;max-height:calc(100dvh - 142px);overflow:auto}.guide-content{display:grid;min-width:0;gap:12px}
.detail-page :deep(.n-tabs-pane-wrapper),.detail-page :deep(.n-tab-pane){overflow:visible!important}.guide-layout{overflow:visible}.guide-directory{position:sticky!important;top:12px!important;z-index:5;height:max-content;max-height:calc(100dvh - 142px);box-shadow:none}@media(max-width:900px){.guide-directory{position:static!important;max-height:none}}
.guide-layout{height:max(480px,calc(100dvh - 238px));min-height:0;overflow:hidden}.guide-directory{position:static!important;max-height:none;overflow:hidden}.guide-content{height:100%;min-height:0;padding-right:5px;overflow-x:hidden;overflow-y:auto;scrollbar-gutter:stable}.guide-section{scroll-margin-top:8px}@media(max-width:900px){.guide-layout{height:auto;overflow:visible}.guide-content{height:auto;overflow:visible}}
.endpoint-list :deep(.n-descriptions-table-header){width:132px;white-space:nowrap}.endpoint-value{display:flex;min-width:0;align-items:center;justify-content:space-between;gap:12px}.endpoint-value code{display:block;min-width:0;overflow:hidden;color:var(--color-primary);font-family:"SFMono-Regular",Consolas,"Liberation Mono",monospace;font-size:12px;text-overflow:ellipsis;white-space:nowrap}.endpoint-value .n-button{flex:none}
</style>


