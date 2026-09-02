<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { NButton, NForm, NFormItem, NInput, useMessage } from 'naive-ui'
import { updateProfile } from '../../api/auth.js'
import { useAuthStore } from '../../stores/auth.js'

const message = useMessage()
const auth = useAuthStore()
const submitting = ref(false)
const formRef = ref()
const form = reactive({ display_name: auth.user?.display_name || '' })

watch(
  () => auth.user?.display_name,
  (value) => {
    if (value !== undefined) form.display_name = value || ''
  },
)

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
}

async function submit() {
  try {
    await formRef.value?.validate()
    submitting.value = true
    const response = await updateProfile({ display_name: form.display_name.trim() })
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
      <n-form ref="formRef" :model="form" :rules="rules" label-placement="top">
        <n-form-item label="显示名称" path="display_name">
          <n-input v-model:value="form.display_name" placeholder="输入显示名称" maxlength="100" />
        </n-form-item>
        <n-form-item label="用户名">
          <n-input :value="auth.user.username" disabled />
        </n-form-item>
        <n-form-item label="邮箱">
          <n-input :value="auth.user.email" disabled />
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
