<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NButton, NIcon, NSpin, useMessage } from 'naive-ui'
import { CheckmarkCircleOutline, ShieldCheckmarkOutline } from '@vicons/ionicons5'
import { inspectAuthorization } from '../../api/oauth.js'
import { redirectToOAuthError } from './oauthError.js'

const route = useRoute()
const router = useRouter()
const message = useMessage()
const loading = ref(true)
const deciding = ref(false)
const authorization = ref()
const parameters = (decision) => Object.fromEntries([
  ...Object.entries(route.query).filter(([, value]) => typeof value === 'string'),
  ...(decision ? [['decision', decision]] : []),
])

async function decide(decision) {
  deciding.value = true
  const form = document.createElement('form')
  form.method = 'POST'
  form.action = '/oauth/authorize'
  for (const [name, value] of Object.entries(parameters(decision))) {
    const input = document.createElement('input')
    input.type = 'hidden'
    input.name = name
    input.value = value
    form.appendChild(input)
  }
  document.body.appendChild(form)
  form.submit()
}

onMounted(async () => {
  try {
    const response = await inspectAuthorization(parameters())
    authorization.value = response.data
    if (!authorization.value.consent_required) await decide('approve')
  } catch (error) {
    if (error.response?.status === 401) {
      await router.replace({ name: 'login', query: { redirect: route.fullPath } })
      return
    }

    const data = error.response?.data
    if (data?.error) {
      await redirectToOAuthError(router, route, data)
      return
    }

    message.error(error.userMessage || '授权请求无效')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="consent-shell">
    <n-spin :show="loading">
      <section v-if="authorization" class="consent-card">
        <div class="consent-logo">
          <img v-if="authorization.client.logo_url" :src="authorization.client.logo_url" alt="" />
          <span v-else>{{ authorization.client.name.slice(0, 1) }}</span>
        </div>
        <p class="eyebrow">应用授权</p><h1>{{ authorization.client.name }}</h1>
        <p class="consent-intro">希望访问你的哞哞通行证账号</p>
        <div class="scope-list">
          <div v-for="scope in authorization.scopes" :key="scope.name" class="scope-item">
            <n-icon :component="CheckmarkCircleOutline" size="22" />
            <div><strong>{{ scope.display_name }}</strong><p>{{ scope.description }}</p></div>
          </div>
        </div>
        <div class="security-note"><n-icon :component="ShieldCheckmarkOutline" /> 授权不会向应用透露你的密码</div>
        <div class="consent-actions">
          <n-button size="large" :disabled="deciding" @click="decide('deny')">拒绝</n-button>
          <n-button type="primary" size="large" :loading="deciding" @click="decide('approve')">允许访问</n-button>
        </div>
      </section>
    </n-spin>
  </main>
</template>
