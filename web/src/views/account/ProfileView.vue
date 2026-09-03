<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { NButton, NForm, NFormItem, NIcon, NInput, NSelect, NTag, NUpload, useMessage } from 'naive-ui'
import { CameraOutline, PersonOutline, PhonePortraitOutline } from '@vicons/ionicons5'
import { updateProfile, uploadProfileAvatar } from '../../api/auth.js'
import { useAuthStore } from '../../stores/auth.js'

const message = useMessage()
const auth = useAuthStore()
const submitting = ref(false)
const uploadingAvatar = ref(false)
const formRef = ref()
const form = reactive({
  display_name: auth.user?.display_name || '',
  phone_country_code: auth.user?.phone_country_code || '+86',
  phone_number: auth.user?.phone_number || '',
})
const avatarLoadFailed = ref(false)

watch(
  () => auth.user?.display_name,
  (displayName) => {
    if (displayName !== undefined) form.display_name = displayName || ''
  },
)
watch(() => auth.user?.avatar_url, () => { avatarLoadFailed.value = false })
watch(
  () => auth.user,
  (user) => {
    if (!user) return
    form.phone_country_code = user.phone_country_code || '+86'
    form.phone_number = user.phone_number || ''
  },
)

const avatarPreview = computed(() => avatarLoadFailed.value ? '' : auth.user?.avatar_url || '')
const phoneVerified = computed(() => Boolean(
  auth.user?.phone_verified_at
  && auth.user?.phone_country_code === form.phone_country_code
  && auth.user?.phone_number === form.phone_number.trim(),
))
const phoneStatus = computed(() => {
  if (!form.phone_number.trim()) return { label: '未绑定', type: 'default' }
  return phoneVerified.value
    ? { label: '已验证', type: 'success' }
    : { label: '未验证', type: 'warning' }
})
const countryCodeOptions = [
  { label: '中国大陆 +86', value: '+86' },
  { label: '中国香港 +852', value: '+852' },
  { label: '中国澳门 +853', value: '+853' },
  { label: '中国台湾 +886', value: '+886' },
  { label: '美国/加拿大 +1', value: '+1' },
]

const createdAt = computed(() => {
  if (!auth.user?.created_at) return '未知'
  const date = new Date(auth.user.created_at)
  return Number.isNaN(date.getTime()) ? auth.user.created_at : date.toLocaleString('zh-CN', { hour12: false })
})

const rules = {
  display_name: [
    { required: true, message: '请输入显示名称', trigger: ['input', 'blur'] },
    { max: 100, message: '显示名称不能超过 100 个字符', trigger: ['input', 'blur'] },
  ],
  phone_number: [{
    validator: (_rule, value) => !value || /^[0-9]{6,15}$/.test(value.trim()),
    message: '请输入 6 至 15 位数字的手机号',
    trigger: ['input', 'blur'],
  }],
}

async function uploadAvatar({ file }) {
  if (!file?.file) return
  if (file.file.size > 2 * 1024 * 1024) {
    message.error('头像不能超过 2MB')
    return
  }
  uploadingAvatar.value = true
  try {
    const response = await uploadProfileAvatar(file.file)
    auth.user = response.data.data.user
    avatarLoadFailed.value = false
    message.success(response.data.data.message || '头像已更新')
  } catch (error) {
    message.error(error.userMessage || '头像上传失败，请稍后重试')
  } finally {
    uploadingAvatar.value = false
  }
}

async function submit() {
  try {
    await formRef.value?.validate()
    submitting.value = true
    const response = await updateProfile({
      display_name: form.display_name.trim(),
      phone_country_code: form.phone_number.trim() ? form.phone_country_code : null,
      phone_number: form.phone_number.trim() || null,
    })
    auth.user = response.data.data.user
    message.success(response.data.data.message || '个人资料已更新')
  } catch (error) {
    if (error?.userMessage) message.error(error.userMessage)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="account-page">
    <section class="account-form-card">
      <header class="profile-editor-header">
        <div class="profile-avatar-preview">
          <img v-if="avatarPreview && !avatarLoadFailed" :src="avatarPreview" alt="头像预览" @error="avatarLoadFailed = true" />
        </div>
        <div class="profile-avatar-copy">
          <h2>公开资料</h2>
          <p>这些信息会展示在账号中心，并可能提供给你授权的应用。</p>
          <n-upload :show-file-list="false" accept="image/png,image/jpeg,image/webp" :custom-request="uploadAvatar">
            <n-button size="small" :loading="uploadingAvatar">
              <template #icon><n-icon :component="CameraOutline" /></template>
              更换头像
            </n-button>
          </n-upload>
          <small>支持 PNG、JPEG、WebP，最大 2MB。</small>
        </div>
      </header>
      <n-form ref="formRef" :model="form" :rules="rules" label-placement="top">
        <n-form-item label="显示名称" path="display_name">
          <n-input v-model:value="form.display_name" placeholder="输入显示名称" maxlength="100" />
        </n-form-item>
        <div class="profile-section-label"><n-icon :component="PersonOutline" /><span>账号信息</span></div>
        <n-form-item label="用户名">
          <n-input :value="auth.user.username" disabled />
        </n-form-item>
        <n-form-item label="邮箱">
          <n-input :value="auth.user.email" disabled />
        </n-form-item>
        <div class="profile-section-label"><n-icon :component="PhonePortraitOutline" /><span>联系方式</span></div>
        <n-form-item label="手机号" path="phone_number">
          <div class="profile-phone-control">
            <div class="profile-phone-field">
              <n-select v-model:value="form.phone_country_code" :options="countryCodeOptions" class="profile-country-code" />
              <n-input v-model:value="form.phone_number" placeholder="输入手机号" maxlength="15" clearable :input-props="{ inputmode: 'numeric', autocomplete: 'tel' }" />
              <n-tag size="small" :type="phoneStatus.type" :bordered="false">
                {{ phoneStatus.label }}
              </n-tag>
            </div>
            <small class="profile-field-hint">修改手机号后需要重新验证。短信验证功能接入前，号码将保持未验证状态。</small>
          </div>
        </n-form-item>
        <n-form-item label="账号 ID">
          <n-input :value="auth.user.id" disabled />
        </n-form-item>
        <n-form-item label="注册时间">
          <n-input :value="createdAt" disabled />
        </n-form-item>
        <n-button type="primary" :loading="submitting" @click="submit">保存修改</n-button>
      </n-form>
    </section>
  </div>
</template>

<style scoped>
.profile-editor-header{display:flex;margin-bottom:22px;padding-bottom:20px;align-items:flex-start;gap:14px;border-bottom:1px solid var(--color-border)}.profile-avatar-preview{width:58px;height:58px;display:grid;place-items:center;overflow:hidden;flex:none;border-radius:50%;background:var(--color-primary);color:#fff;font-size:var(--font-size-xl);font-weight:600}.profile-avatar-preview img{width:100%;height:100%;display:block;object-fit:cover}.profile-avatar-copy{min-width:0}.profile-editor-header h2{margin:0;color:var(--color-text-primary);font-size:var(--font-size-md);font-weight:600}.profile-editor-header p{margin:4px 0 10px;color:var(--color-text-tertiary);font-size:var(--font-size-sm)}.profile-avatar-copy .n-upload{display:inline-block}.profile-avatar-copy small{display:block;margin-top:6px;color:var(--color-text-tertiary);font-size:var(--font-size-xs)}.profile-section-label{display:flex;margin:8px 0 14px;padding-top:18px;align-items:center;gap:7px;border-top:1px solid var(--color-border);color:var(--color-text-secondary);font-size:var(--font-size-sm);font-weight:600}.profile-section-label .n-icon{color:var(--color-primary)}
.profile-phone-control{width:100%;min-width:0}.profile-phone-field{width:100%;display:grid;grid-template-columns:150px minmax(120px,1fr) auto;align-items:center;gap:8px}.profile-phone-field :deep(.n-input),.profile-phone-field :deep(.n-select){min-width:0}.profile-field-hint{display:block;margin-top:7px;color:var(--color-text-tertiary);font-size:var(--font-size-xs);line-height:1.6}@media (max-width:560px){.profile-phone-field{grid-template-columns:128px minmax(0,1fr)}.profile-phone-field .n-tag{grid-column:1/-1;justify-self:start}}
</style>
