<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NButton, NCheckbox, NForm, NFormItem, NInput, useMessage } from 'naive-ui'
import { register } from '../../api/auth.js'
import BrandLink from '../../components/BrandLink.vue'

const route = useRoute()
const router = useRouter()
const message = useMessage()
const formRef = ref()
const loading = ref(false)
const agreementAccepted = ref(false)
const form = reactive({ username: '', email: '', password: '', password_confirmation: '' })
const rules = {
  username: [
    { required: true, message: '请输入用户名', trigger: ['input', 'blur'] },
    { pattern: /^[A-Za-z0-9_]{3,32}$/, message: '使用 3–32 位字母、数字或下划线', trigger: ['input', 'blur'] },
  ],
  email: { required: true, type: 'email', message: '请输入有效邮箱', trigger: ['input', 'blur'] },
  password: [
    { required: true, message: '请输入密码', trigger: ['input', 'blur'] },
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
    if (!agreementAccepted.value) {
      message.warning('请先阅读并同意服务协议和隐私政策')
      return
    }
    loading.value = true
    await register({ ...form, display_name: form.username })
    await router.replace({
      name: 'check-email',
      query: { email: form.email, ...(route.query.redirect ? { redirect: route.query.redirect } : {}) },
    })
  } catch (error) {
    if (error?.userMessage) message.error(error.userMessage)
  } finally { loading.value = false }
}
</script>

<template>
  <main class="register-page">
    <header class="register-header">
      <BrandLink />
      <nav><router-link :to="{ name: 'login', query: route.query }">已有账号？登录</router-link></nav>
    </header>

    <section class="register-main">
      <div class="register-panel">
        <header class="register-title">
          <h1>注册账号</h1>
          <p>使用邮箱创建哞哞通行证</p>
        </header>

        <n-form ref="formRef" :model="form" :rules="rules" :show-label="false">
          <n-form-item path="username">
            <n-input v-model:value="form.username" placeholder="用户名（3–32 位字母、数字或下划线）" autocomplete="username" />
          </n-form-item>
          <n-form-item path="email">
            <n-input v-model:value="form.email" placeholder="邮箱地址" autocomplete="email" />
          </n-form-item>
          <n-form-item path="password">
            <n-input v-model:value="form.password" type="password" show-password-on="click" placeholder="设置密码" autocomplete="new-password" />
            <template #feedback><span class="password-hint">至少 9 位，包含大写字母、小写字母和特殊符号</span></template>
          </n-form-item>
          <n-form-item path="password_confirmation">
            <n-input v-model:value="form.password_confirmation" type="password" show-password-on="click" placeholder="再次输入密码" autocomplete="new-password" />
          </n-form-item>

          <n-checkbox v-model:checked="agreementAccepted" class="register-agreement">
            我已阅读并同意
            <router-link :to="{ name: 'terms' }" target="_blank">用户服务协议</router-link>
            和
            <router-link :to="{ name: 'privacy' }" target="_blank">隐私政策</router-link>
          </n-checkbox>
          <n-button type="primary" block :loading="loading" class="register-submit" @click="submit">
            注册
          </n-button>
        </n-form>
      </div>
    </section>

    <footer class="register-footer">Copyright © {{ new Date().getFullYear() }} Moo Passport</footer>
  </main>
</template>
