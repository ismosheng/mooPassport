<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { NButton, NIcon, NPagination, NSpin, useMessage } from 'naive-ui'
import { AppsOutline } from '@vicons/ionicons5'
import AccountActionConfirm from '../../components/account/AccountActionConfirm.vue'
import { listConsents, revokeConsent } from '../../api/oauth.js'

const router = useRouter()
const message = useMessage()
const loading = ref(true)
const revokingId = ref('')
const apps = ref([])
const total = ref(0)
const page = ref(1)
const perPage = 5
const confirmation = ref(null)

function formatTime(value) {
  if (!value) return '未知时间'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('zh-CN', { hour12: false })
}

async function loadApps() {
  loading.value = true
  try {
    let response = await listConsents({ page: page.value, per_page: perPage })
    let data = response.data.data
    total.value = data.total || 0
    const lastPage = Math.max(1, Math.ceil(total.value / perPage))
    if (page.value > lastPage) {
      page.value = lastPage
      response = await listConsents({ page: page.value, per_page: perPage })
      data = response.data.data
      total.value = data.total || 0
    }
    apps.value = data.items || []
  } finally {
    loading.value = false
  }
}

function changePage(value) {
  page.value = value
  loadApps().catch((error) => message.error(error.userMessage))
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
  confirmation.value = app
}

async function confirmRevoke() {
  if (!confirmation.value) return
  revokingId.value = confirmation.value.client_id
  try {
    const response = await revokeConsent(confirmation.value.client_id)
    message.success(response.data.data.message || '已撤销授权')
    confirmation.value = null
    await loadApps()
  } catch (error) {
    message.error(error.userMessage)
  } finally {
    revokingId.value = ''
  }
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

    <footer v-if="total" class="account-list-footer">
      <n-pagination :page="page" :page-size="perPage" :item-count="total" @update:page="changePage" />
    </footer>

    <AccountActionConfirm
      :show="Boolean(confirmation)"
      :loading="Boolean(revokingId)"
      title="撤销应用授权"
      description="撤销后，该应用将无法继续读取你的账号信息。"
      :subject="confirmation?.name || '未知应用'"
      :detail="confirmation?.scopes?.length ? `已授权 ${confirmation.scopes.length} 项权限 · 重新使用时需再次授权` : '重新使用时需要再次授权'"
      confirm-text="确认撤销"
      @update:show="!$event && (confirmation = null)"
      @confirm="confirmRevoke"
    >
      <template #icon><n-icon :component="AppsOutline" /></template>
    </AccountActionConfirm>
  </div>
</template>
