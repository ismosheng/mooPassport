<script setup>
import { computed, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { NAlert, NButton, NForm, NFormItem, NIcon, NInput, useMessage } from 'naive-ui'
import { MailUnreadOutline } from '@vicons/ionicons5'
import AuthLayout from '../../layouts/AuthLayout.vue'
import { forgotPassword } from '../../api/auth.js'

const route = useRoute()
const message = useMessage()
const formRef = ref()
const loading = ref(false)
const submitted = ref(false)
const form = reactive({ email: '' })
const rules = {
  email: { required: true, type: 'email', message: '请输入有效邮箱', trigger: ['input', 'blur'] },
}
const loginQuery = computed(() => (route.query.redirect ? { redirect: route.query.redirect } : {}))

async function submit() {
  try {
    await formRef.value?.validate()
    loading.value = true
    await forgotPassword(form.email)
    submitted.value = true
  } catch (error) {
    if (error?.userMessage) message.error(error.userMessage)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout title="忘记密码" subtitle="输入注册邮箱，我们会发送重置链接">
    <template v-if="!submitted">
      <n-form ref="formRef" :model="form" :rules="rules" :show-label="false" @keyup.enter="submit">
        <n-form-item path="email">
          <n-input v-model:value="form.email" placeholder="注册邮箱" autocomplete="email" />
        </n-form-item>
        <n-button type="primary" block :loading="loading" @click="submit">发送重置邮件</n-button>
      </n-form>
      <p class="switch-entry">
        <router-link :to="{ name: 'login', query: loginQuery }">返回登录</router-link>
      </p>
    </template>

    <template v-else>
      <div class="status-illustration"><n-icon :component="MailUnreadOutline" size="42" /></div>
      <p class="status-copy">如果该邮箱可以重置密码，我们已发送邮件到</p>
      <p class="status-email">{{ form.email }}</p>
      <n-alert type="info" :show-icon="false">
        请在 30 分钟内打开邮件中的链接重置密码。如果没有收到，请先检查垃圾邮件目录。
      </n-alert>
      <p class="switch-entry">
        <router-link :to="{ name: 'login', query: loginQuery }">返回登录</router-link>
      </p>
    </template>
  </AuthLayout>
</template>
