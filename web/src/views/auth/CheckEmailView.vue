<script setup>
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { NAlert, NButton, NIcon, NInput, useMessage } from 'naive-ui'
import { MailUnreadOutline } from '@vicons/ionicons5'
import AuthLayout from '../../layouts/AuthLayout.vue'
import { resendVerification } from '../../api/auth.js'

const route = useRoute()
const message = useMessage()
const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const sending = ref(false)
const cooldown = ref(0)
const loginQuery = computed(() => route.query.redirect ? { redirect: route.query.redirect } : {})

async function resend() {
  if (!email.value || cooldown.value > 0) return
  sending.value = true
  try {
    await resendVerification(email.value)
    message.success('如果该邮箱可以验证，我们已重新发送激活邮件')
    cooldown.value = 60
    const timer = window.setInterval(() => {
      cooldown.value -= 1
      if (cooldown.value <= 0) window.clearInterval(timer)
    }, 1000)
  } catch (error) {
    message.error(error.userMessage)
  } finally { sending.value = false }
}
</script>

<template>
  <AuthLayout title="检查你的邮箱" subtitle="账号已创建，还差最后一步">
    <div class="status-illustration"><n-icon :component="MailUnreadOutline" size="42" /></div>
    <p class="status-copy">激活邮件已发送到</p>
    <p class="status-email">{{ email || '你填写的邮箱' }}</p>
    <n-alert type="info" :show-icon="false">
      请在 30 分钟内点击邮件里的“验证邮箱”。如果没有收到，请先检查垃圾邮件目录。
    </n-alert>
    <div class="resend-box">
      <n-input v-model:value="email" placeholder="输入注册邮箱" />
      <n-button :loading="sending" :disabled="cooldown > 0" @click="resend">
        {{ cooldown > 0 ? `${cooldown} 秒后重发` : '重新发送' }}
      </n-button>
    </div>
    <p class="switch-entry"><router-link :to="{ name: 'login', query: loginQuery }">返回登录</router-link></p>
  </AuthLayout>
</template>
