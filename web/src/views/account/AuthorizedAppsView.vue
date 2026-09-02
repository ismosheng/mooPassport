<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { NButton, NIcon, NSpin, useDialog, useMessage } from 'naive-ui'
import { AppsOutline } from '@vicons/ionicons5'
import { listConsents, revokeConsent } from '../../api/oauth.js'

const router = useRouter()
const message = useMessage()
const dialog = useDialog()
const loading = ref(true)
const revokingId = ref('')
const apps = ref([])

function formatTime(value) {
  if (!value) return '未知时间'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('zh-CN', { hour12: false })
}

async function loadApps() {
  const response = await listConsents()
  apps.value = response.data.data.items || []
}

onMounted(async () => {
  try {
    await loadApps()
  } catch (error) {
    if (error.response?.status === 401) {
      await router.replace('/login')
      return
    }
    message.error(error.userMessage)
  } finally {
    loading.value = false
  }
})

function onRevoke(app) {
  dialog.warning({
    title: '撤销应用授权？',
    content: `撤销后，${app.name} 将无法继续访问你的账号，需要重新授权。`,
    positiveText: '确认撤销',
    negativeText: '取消',
    onPositiveClick: async () => {
      revokingId.value = app.client_id
      try {
        const response = await revokeConsent(app.client_id)
        message.success(response.data.data.message || '已撤销授权')
        await loadApps()
      } catch (error) {
        message.error(error.userMessage)
      } finally {
        revokingId.value = ''
      }
    },
  })
}
</script>

<template>
  <div class="account-page">
    <n-spin :show="loading">
      <section v-if="apps.length" class="authorized-app-list">
        <article v-for="app in apps" :key="app.client_id" class="authorized-app-item">
          <div class="authorized-app-logo">
            <img v-if="app.logo_url" :src="app.logo_url" alt="" />
            <n-icon v-else :component="AppsOutline" />
          </div>
          <div class="authorized-app-body">
            <h2>{{ app.name }}</h2>
            <p>{{ app.description || '未提供应用简介' }}</p>
            <div class="authorized-app-scopes">
              <span v-for="scope in app.scopes" :key="scope">{{ scope }}</span>
            </div>
            <p class="authorized-app-time">授权于 {{ formatTime(app.granted_at) }}</p>
          </div>
          <n-button quaternary type="error" :loading="revokingId === app.client_id" @click="onRevoke(app)">
            撤销授权
          </n-button>
        </article>
      </section>

      <section v-else-if="!loading" class="session-empty">
        <p>当前没有已授权的第三方应用。</p>
      </section>
    </n-spin>
  </div>
</template>
