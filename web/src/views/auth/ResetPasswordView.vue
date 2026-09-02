<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { NButton, NForm, NFormItem, NIcon, NInput, NResult, NSpin, useMessage } from 'naive-ui'
import { CloseCircleOutline } from '@vicons/ionicons5'
import AuthLayout from '../../layouts/AuthLayout.vue'
import { resetPassword } from '../../api/auth.js'

const router = useRouter()
const message = useMessage()
const formRef = ref()
const loading = ref(false)
const ready = ref(false)
const token = ref('')
const tokenError = ref('')
const form = reactive({ password: '', password_confirmation: '' })
const rules = {
  password: [
    { required: true, message: '请输入新密码', trigger: ['input', 'blur'] },
    { min: 9, message: '密码至少需要 9 位', trigger: ['input', 'blur'] },
    {
      pattern: /^(?=.*[a-z])(?=.*[A-Z])(?=.*[\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E]).+$/,
      message: '必须包含大写字母、小写字母和特殊符号',
      trigger: ['input', 'blur'],
    },
  ],
  password_confirmation: {
    required: true,
    validator: (_, value) => value === form.password,
    message: '两次输入的密码不一致',
    trigger: ['input', 'blur'],
  },
}
const hasToken = computed(() => Boolean(token.value) && !tokenError.value)

onMounted(() => {
  const hash = new URLSearchParams(window.location.hash.slice(1))
  const rawToken = hash.get('token')

  // 令牌读取后立即清除地址栏，避免用户复制链接或截图时泄露一次性凭据。
  window.history.replaceState(null, '', window.location.pathname + window.location.search)

  if (!rawToken) {
    tokenError.value = '重置链接缺少令牌。'
  } else {
    token.value = rawToken
  }
  ready.value = true
})

async function submit() {
  try {
    await formRef.value?.validate()
    loading.value = true
    await resetPassword({
      token: token.value,
      password: form.password,
      password_confirmation: form.password_confirmation,
    })
    message.success('密码已重置，请重新登录')
    await router.replace({ name: 'login' })
  } catch (error) {
    if (error?.userMessage) message.error(error.userMessage)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout title="重置密码" subtitle="设置新的登录密码">
    <div v-if="!ready" class="verify-loading">
      <n-spin size="large" /><p>正在准备重置表单……</p>
    </div>

    <template v-else-if="hasToken">
      <n-form ref="formRef" :model="form" :rules="rules" :show-label="false" @keyup.enter="submit">
        <n-form-item path="password">
          <n-input
            v-model:value="form.password"
            type="password"
            show-password-on="click"
            placeholder="设置新密码"
            autocomplete="new-password"
          />
          <template #feedback>
            <span class="password-hint">至少 9 位，包含大写字母、小写字母和特殊符号</span>
          </template>
        </n-form-item>
        <n-form-item path="password_confirmation">
          <n-input
            v-model:value="form.password_confirmation"
            type="password"
            show-password-on="click"
            placeholder="再次输入新密码"
            autocomplete="new-password"
          />
        </n-form-item>
        <n-button type="primary" block :loading="loading" @click="submit">重置密码</n-button>
      </n-form>
      <p class="switch-entry"><router-link :to="{ name: 'login' }">返回登录</router-link></p>
    </template>

    <n-result v-else status="error" title="重置链接无效" :description="tokenError || '请重新申请密码重置邮件。'">
      <template #icon><n-icon :component="CloseCircleOutline" /></template>
      <template #footer>
        <router-link :to="{ name: 'forgot-password' }">
          <n-button size="large">重新申请重置</n-button>
        </router-link>
      </template>
    </n-result>
  </AuthLayout>
</template>
