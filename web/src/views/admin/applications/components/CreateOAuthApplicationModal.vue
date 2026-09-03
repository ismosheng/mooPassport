<script setup>
import { reactive, ref, watch } from 'vue'
import { NAlert, NButton, NCheckbox, NCheckboxGroup, NForm, NFormItem, NInput, NModal, NRadioButton, NRadioGroup, NUpload, useMessage } from 'naive-ui'
import { createApplication, uploadApplicationLogo } from '../../../../api/applications.js'

const props = defineProps({ show: Boolean })
const emit = defineEmits(['update:show', 'created'])
const message = useMessage()
const loading = ref(false), result = ref(null)
const uploadingLogo = ref(false)
const form = reactive({ name: '', description: '', logo_url: '', capabilities: ['login'], login_application_type: 'web', redirect_uris: 'https://example.com/oauth/callback', login_scopes: ['openid', 'profile'] })
watch(() => props.show, (show) => { if (show) result.value = null })
const hasLogin = () => form.capabilities.includes('login')
function setCapability(capability, checked) {
  if (checked && !form.capabilities.includes(capability)) form.capabilities.push(capability)
  if (!checked) form.capabilities = form.capabilities.filter((item) => item !== capability)
}
async function submit() {
  loading.value = true
  try {
    const payload = { name: form.name.trim(), description: form.description.trim() || null, logo_url: form.logo_url.trim() || null, capabilities: form.capabilities, login_application_type: form.login_application_type, redirect_uris: hasLogin() ? form.redirect_uris.split(/\r?\n/).map((item) => item.trim()).filter(Boolean) : [], login_scopes: hasLogin() ? form.login_scopes : [] }
    const response = await createApplication(payload)
    result.value = response.data.data
    emit('created', result.value)
    message.success('应用创建成功')
  } catch (error) { message.error(error.userMessage) }
  finally { loading.value = false }
}
async function copy(value, label) { try { await navigator.clipboard.writeText(value); message.success(`${label}已复制`) } catch { message.error('复制失败，请手动复制') } }
function close() { emit('update:show', false) }
async function uploadLogo({ file }) {
  if (!file?.file) return
  uploadingLogo.value = true
  try { const response = await uploadApplicationLogo(file.file); form.logo_url = response.data.data.logo_url; message.success('图标上传成功') }
  catch (error) { message.error(error.userMessage) }
  finally { uploadingLogo.value = false }
}
</script>
<template>
  <n-modal :show="show" preset="card" :style="{ width: '520px', maxWidth: 'calc(100vw - 32px)' }" :mask-closable="!result" :close-on-esc="!result" title="创建 OAuth 应用" class="create-app-modal" @close="close">
    <template v-if="!result">
      <n-form label-placement="top" class="admin-form">
        <n-form-item label="应用名称" required><n-input v-model:value="form.name" maxlength="100" show-count placeholder="例如：哞哞下载 Web 端" /></n-form-item>
        <n-form-item label="应用图标"><div class="logo-field"><div class="logo-preview"><img v-if="form.logo_url" :src="form.logo_url" alt="应用图标预览" @error="$event.currentTarget.style.visibility='hidden'" @load="$event.currentTarget.style.visibility='visible'" /><span v-else>{{ form.name.trim().slice(0, 1) || 'A' }}</span></div><div><n-upload :show-file-list="false" accept="image/png,image/jpeg,image/webp" :custom-request="uploadLogo"><n-button :loading="uploadingLogo">上传图标</n-button></n-upload><small class="field-help">建议正方形 PNG、JPEG 或 WebP，最大 2MB。</small></div></div></n-form-item>
        <n-form-item label="应用说明"><n-input v-model:value="form.description" type="textarea" maxlength="500" show-count :autosize="{ minRows: 2, maxRows: 4 }" /></n-form-item>
        <n-form-item label="接入能力" required>
          <div class="form-field">
            <div class="capability-options">
              <div class="capability-card" :class="{ selected: form.capabilities.includes('login') }">
                <n-checkbox :checked="form.capabilities.includes('login')" @update:checked="setCapability('login', $event)">
                  <span class="capability-copy"><strong>用户登录</strong><small>让用户跳转到哞哞通行证完成登录</small><em>Authorization Code + PKCE</em></span>
                </n-checkbox>
              </div>
              <div class="capability-card" :class="{ selected: form.capabilities.includes('service') }">
                <n-checkbox :checked="form.capabilities.includes('service')" @update:checked="setCapability('service', $event)">
                  <span class="capability-copy"><strong>服务端 API</strong><small>用于服务器之间调用受保护接口</small><em>Client Credentials</em></span>
                </n-checkbox>
              </div>
            </div>
            <small class="field-help capability-help">两项可以同时选择，系统将分别生成独立的 AppID。</small>
          </div>
        </n-form-item>
        <template v-if="hasLogin()">
          <n-form-item label="登录客户端平台" required><n-radio-group v-model:value="form.login_application_type"><n-radio-button value="web">服务端 Web</n-radio-button><n-radio-button value="spa">单页应用 SPA</n-radio-button><n-radio-button value="native">原生应用</n-radio-button></n-radio-group></n-form-item>
          <n-form-item label="登录回调地址" required><div class="form-field"><n-input v-model:value="form.redirect_uris" type="textarea" :autosize="{ minRows: 2, maxRows: 5 }" placeholder="每行一个完整 URI" /><small class="field-help">每行一个地址；正式 Web 应用必须使用 HTTPS。</small></div></n-form-item>
          <n-form-item label="用户授权范围" required><div class="form-field"><n-checkbox-group v-model:value="form.login_scopes" class="scope-options"><n-checkbox value="openid" label="OpenID" /><n-checkbox value="profile" label="基础资料" /><n-checkbox value="email" label="邮箱" /><n-checkbox value="realname" label="脱敏实名" /><n-checkbox value="realname_full" label="完整实名（高敏感）" /><n-checkbox value="offline_access" label="离线访问" /></n-checkbox-group><small class="field-help">完整实名会返回姓名和证件号码原文，仅应授予确有必要且可信的应用。</small></div></n-form-item>
        </template>
        <n-alert v-if="form.capabilities.includes('service')" type="info" :show-icon="false">服务端 API 客户端固定使用 <code>service</code> Scope，不设置回调地址。</n-alert>
      </n-form>
    </template>
    <template v-else>
      <n-alert type="success" title="应用创建成功">AppSecret 只展示这一次，关闭窗口后无法再次查看，请立即安全保存。</n-alert>
      <div v-for="client in result.clients" :key="client.client_id" class="credential-group"><h3>{{ client.purpose === 'login' ? '用户登录客户端' : '服务端 API 客户端' }}</h3><dl class="credential-list"><div><dt>AppID</dt><dd><code>{{ client.client_id }}</code><n-button size="small" @click="copy(client.client_id, 'AppID')">复制</n-button></dd></div><div v-if="client.client_secret"><dt>AppSecret</dt><dd><code>{{ client.client_secret }}</code><n-button size="small" type="primary" @click="copy(client.client_secret, 'AppSecret')">复制</n-button></dd></div><div><dt>客户端类型</dt><dd>{{ client.client_type }}</dd></div></dl></div>
    </template>
    <template #footer><div class="modal-actions"><template v-if="!result"><n-button @click="close">取消</n-button><n-button type="primary" :loading="loading" :disabled="!form.name.trim() || !form.capabilities.length || (hasLogin() && (!form.redirect_uris.trim() || !form.login_scopes.length))" @click="submit">创建应用</n-button></template><n-button v-else type="primary" @click="close">我已安全保存</n-button></div></template>
  </n-modal>
</template>
<style scoped>
.create-app-modal{width:min(620px,calc(100vw - 32px))}.admin-form{max-height:65vh;overflow-y:auto;padding-right:4px}.form-field{display:block;width:100%;min-width:0}.field-help{display:block;margin-top:6px;color:var(--admin-muted);font-size:var(--admin-font-xs);line-height:18px}.scope-options{display:flex;flex-wrap:wrap;gap:12px 20px}.capability-options{display:grid;width:100%;grid-template-columns:1fr 1fr;gap:10px}.capability-card{min-width:0;padding:13px 14px;border:1px solid var(--admin-border);border-radius:var(--admin-radius);background:var(--color-bg-surface);transition:border-color .16s ease,background-color .16s ease,box-shadow .16s ease}.capability-card:hover{border-color:var(--color-primary)}.capability-card.selected{border-color:var(--color-primary);background:var(--color-primary-soft);box-shadow:0 0 0 1px var(--color-primary)}.capability-card :deep(.n-checkbox){width:100%;align-items:flex-start}.capability-card :deep(.n-checkbox__box-wrapper){margin-top:2px}.capability-card :deep(.n-checkbox__label){display:block;min-width:0;width:100%;padding-left:10px}.capability-copy{display:block}.capability-copy strong,.capability-copy small,.capability-copy em{display:block}.capability-copy strong{color:var(--admin-heading);font-size:var(--admin-font-sm);font-style:normal;font-weight:600;line-height:20px}.capability-copy small{min-height:36px;margin-top:4px;color:var(--admin-muted);font-size:var(--admin-font-xs);line-height:18px}.capability-copy em{margin-top:8px;color:var(--color-primary);font-size:var(--admin-font-xs);font-style:normal;white-space:nowrap}.capability-help{margin-top:8px}.modal-actions{display:flex;justify-content:flex-end;gap:8px}.credential-group{margin-top:18px}.credential-group h3{margin:0;font-size:var(--admin-font-md)}.credential-list{display:grid;margin:10px 0 0;gap:12px}.credential-list>div{display:grid;gap:6px}.credential-list dt{color:var(--admin-muted);font-size:var(--admin-font-xs)}.credential-list dd{display:flex;margin:0;align-items:center;gap:8px}.credential-list code{min-width:0;padding:9px 11px;overflow:auto;border:1px solid var(--admin-border);border-radius:var(--admin-radius);background:var(--color-bg-page);font-size:var(--admin-font-sm);flex:1;white-space:nowrap}@media(max-width:600px){.capability-options{grid-template-columns:1fr}.capability-copy small{min-height:0}}
.logo-field{display:grid;width:100%;grid-template-columns:48px minmax(0,1fr);align-items:start;gap:12px}.logo-preview{display:grid;width:48px;height:48px;overflow:hidden;place-items:center;border:1px solid var(--admin-border);border-radius:var(--radius-lg);color:var(--color-primary);background:var(--color-primary-soft);font-weight:600}.logo-preview img{width:100%;height:100%;object-fit:cover}
</style>
