<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { NButton, NIcon, NResult, NSpin } from 'naive-ui'
import { CheckmarkCircleOutline, CloseCircleOutline } from '@vicons/ionicons5'
import AuthLayout from '../../layouts/AuthLayout.vue'
import { verifyEmail } from '../../api/auth.js'

const route = useRoute()
const status = ref('loading')
const errorMessage = ref('')
const loginQuery = computed(() => route.query.redirect ? { redirect: route.query.redirect } : {})

onMounted(async () => {
  const hash = new URLSearchParams(window.location.hash.slice(1))
  const token = hash.get('token')

  // 令牌读取后立即清除地址栏，避免用户复制链接或截图时泄露一次性凭据。
  window.history.replaceState(null, '', window.location.pathname + window.location.search)
  if (!token) {
    status.value = 'error'
    errorMessage.value = '激活链接缺少验证令牌。'
    return
  }

  try {
    await verifyEmail(token)
    status.value = 'success'
  } catch (error) {
    status.value = 'error'
    errorMessage.value = error.userMessage || '激活链接无效或已过期。'
  }
})
</script>

<template>
  <AuthLayout title="验证邮箱" subtitle="正在确认你的哞哞通行证账号">
    <div v-if="status === 'loading'" class="verify-loading">
      <n-spin size="large" /><p>正在验证，请稍候……</p>
    </div>
    <n-result v-else-if="status === 'success'" status="success" title="邮箱验证成功" description="账号已经激活，现在可以登录了。">
      <template #icon><n-icon :component="CheckmarkCircleOutline" color="var(--color-success)" /></template>
      <template #footer><router-link :to="{ name: 'login', query: loginQuery }"><n-button type="primary" size="large">前往登录</n-button></router-link></template>
    </n-result>
    <n-result v-else status="error" title="邮箱验证失败" :description="errorMessage">
      <template #icon><n-icon :component="CloseCircleOutline" /></template>
      <template #footer><router-link to="/check-email"><n-button size="large">重新发送激活邮件</n-button></router-link></template>
    </n-result>
  </AuthLayout>
</template>
