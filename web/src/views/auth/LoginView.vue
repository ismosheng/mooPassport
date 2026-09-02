<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NButton, NForm, NFormItem, NInput, useMessage } from 'naive-ui'
import AuthLayout from '../../layouts/AuthLayout.vue'
import { useAuthStore } from '../../stores/auth.js'

const route = useRoute()
const router = useRouter()
const message = useMessage()
const auth = useAuthStore()
const formRef = ref()
const loading = ref(false)
const form = reactive({ identifier: '', password: '' })
const rules = {
  identifier: { required: true, message: '请输入用户名或邮箱', trigger: ['input', 'blur'] },
  password: { required: true, message: '请输入密码', trigger: ['input', 'blur'] },
}

async function submit() {
  try {
    await formRef.value?.validate()
    loading.value = true
    await auth.signIn(form)
    message.success('登录成功')
    const target = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/')
      ? route.query.redirect : '/account'
    await router.replace(target)
  } catch (error) {
    if (error?.userMessage) message.error(error.userMessage)
  } finally { loading.value = false }
}
</script>

<template>
  <AuthLayout title="登录" subtitle="登录哞哞通行证，继续访问你的账号">
    <n-form ref="formRef" :model="form" :rules="rules" :show-label="false" @keyup.enter="submit">
      <n-form-item path="identifier">
        <n-input v-model:value="form.identifier" placeholder="用户名或邮箱" autocomplete="username">
        </n-input>
      </n-form-item>
      <n-form-item path="password">
        <n-input v-model:value="form.password" type="password" show-password-on="click" placeholder="密码" autocomplete="current-password">
        </n-input>
      </n-form-item>
      <div class="form-assist">
        <router-link :to="{ name: 'forgot-password', query: route.query }">忘记密码？</router-link>
      </div>
      <n-button type="primary" block :loading="loading" @click="submit">登录</n-button>
    </n-form>
    <p class="switch-entry">还没有账号？ <router-link :to="{ name: 'register', query: route.query }">立即注册</router-link></p>
  </AuthLayout>
</template>
