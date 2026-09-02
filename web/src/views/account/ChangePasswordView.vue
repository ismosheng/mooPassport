<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { NButton, NForm, NFormItem, NInput, useMessage } from 'naive-ui'
import { changePassword } from '../../api/auth.js'
import { useAuthStore } from '../../stores/auth.js'

const router = useRouter()
const message = useMessage()
const auth = useAuthStore()
const submitting = ref(false)
const formRef = ref()
const form = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})
const rules = {
  current_password: { required: true, message: '请输入当前密码', trigger: ['input', 'blur'] },
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

async function submit() {
  try {
    await formRef.value?.validate()
    submitting.value = true
    await changePassword(form)
    auth.user = null
    message.success('密码已修改，请重新登录')
    await router.replace({ name: 'login' })
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
      <n-form ref="formRef" :model="form" :rules="rules" label-placement="top">
        <n-form-item label="当前密码" path="current_password">
          <n-input
            v-model:value="form.current_password"
            type="password"
            show-password-on="click"
            placeholder="请输入当前密码"
            autocomplete="current-password"
          />
        </n-form-item>
        <n-form-item label="新密码" path="password">
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
        <n-form-item label="确认新密码" path="password_confirmation">
          <n-input
            v-model:value="form.password_confirmation"
            type="password"
            show-password-on="click"
            placeholder="再次输入新密码"
            autocomplete="new-password"
          />
        </n-form-item>
        <n-button type="primary" :loading="submitting" @click="submit">确认修改</n-button>
      </n-form>
    </section>
  </div>
</template>
