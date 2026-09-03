<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { NButton, NIcon, NPagination, NSpin, useMessage } from 'naive-ui'
import { DesktopOutline, PhonePortraitOutline, TabletPortraitOutline } from '@vicons/ionicons5'
import AccountActionConfirm from '../../components/account/AccountActionConfirm.vue'
import { listSessions, revokeOtherSessions, revokeSession } from '../../api/auth.js'
import { useAuthStore } from '../../stores/auth.js'

const router = useRouter()
const message = useMessage()
const auth = useAuthStore()
const loading = ref(true)
const revokingOthers = ref(false)
const revokingId = ref('')
const sessions = ref([])
const total = ref(0)
const page = ref(1)
const perPage = 5
const confirmation = ref(null)

const hasOtherSessions = computed(() => total.value > 1 || sessions.value.some((item) => !item.is_current))

function describeDevice(userAgent) {
  const ua = userAgent || ''
  if (/iPad|Tablet/i.test(ua)) return { label: '平板设备', icon: TabletPortraitOutline }
  if (/Mobile|Android|iPhone/i.test(ua)) return { label: '手机设备', icon: PhonePortraitOutline }
  if (ua) return { label: '电脑设备', icon: DesktopOutline }
  return { label: '未知设备', icon: DesktopOutline }
}

function describeBrowser(userAgent) {
  const ua = userAgent || ''
  if (/Edg\//i.test(ua)) return 'Microsoft Edge'
  if (/Chrome\//i.test(ua) && !/Edg\//i.test(ua)) return 'Chrome'
  if (/Firefox\//i.test(ua)) return 'Firefox'
  if (/Safari\//i.test(ua) && !/Chrome\//i.test(ua)) return 'Safari'
  return ua ? ua.slice(0, 48) : '未知浏览器'
}

function formatTime(value) {
  if (!value) return '未知时间'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString('zh-CN', { hour12: false })
}

async function loadSessions() {
  loading.value = true
  try {
    let response = await listSessions({ page: page.value, per_page: perPage })
    let data = response.data.data
    total.value = data.total || 0
    const lastPage = Math.max(1, Math.ceil(total.value / perPage))
    if (page.value > lastPage) {
      page.value = lastPage
      response = await listSessions({ page: page.value, per_page: perPage })
      data = response.data.data
      total.value = data.total || 0
    }
    sessions.value = data.items || []
  } finally {
    loading.value = false
  }
}

function changePage(value) {
  page.value = value
  loadSessions().catch((error) => message.error(error.userMessage))
}

onMounted(async () => {
  try {
    await loadSessions()
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

function onRevoke(session) {
  confirmation.value = { type: 'session', session }
}

function onRevokeOthers() {
  confirmation.value = { type: 'others' }
}

async function confirmAction() {
  if (confirmation.value?.type === 'others') {
    revokingOthers.value = true
    try {
      const response = await revokeOtherSessions()
      message.success(response.data.data.message || '已退出其他设备')
      confirmation.value = null
      await loadSessions()
    } catch (error) {
      message.error(error.userMessage)
    } finally {
      revokingOthers.value = false
    }
    return
  }

  const session = confirmation.value?.session
  if (!session) return
  revokingId.value = session.id
  try {
    const response = await revokeSession(session.id)
    if (response.data.data.cleared_current) {
      auth.user = null
      confirmation.value = null
      message.success(response.data.data.message || '当前设备已退出登录')
      await router.replace('/login')
      return
    }
    message.success(response.data.data.message || '已撤销该登录会话')
    confirmation.value = null
    await loadSessions()
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
      <section v-if="sessions.length" class="session-list">
        <article v-for="session in sessions" :key="session.id" class="session-item">
          <n-icon class="session-icon" :component="describeDevice(session.user_agent).icon" />
          <div class="session-body">
            <div class="session-title">
              <h2>{{ describeDevice(session.user_agent).label }}</h2>
              <span v-if="session.is_current" class="session-current">当前设备</span>
            </div>
            <p>{{ describeBrowser(session.user_agent) }}</p>
            <dl>
              <div><dt>IP</dt><dd>{{ session.ip_address || '未知' }}</dd></div>
              <div><dt>最近活跃</dt><dd>{{ formatTime(session.last_seen_at) }}</dd></div>
              <div><dt>登录时间</dt><dd>{{ formatTime(session.created_at) }}</dd></div>
            </dl>
          </div>
          <div class="session-actions">
            <n-button
              v-if="session.is_current"
              :disabled="!hasOtherSessions"
              :loading="revokingOthers"
              @click="onRevokeOthers"
            >
              退出其他设备
            </n-button>
            <n-button
              quaternary
              type="error"
              :loading="revokingId === session.id"
              @click="onRevoke(session)"
            >
              {{ session.is_current ? '退出' : '撤销' }}
            </n-button>
          </div>
        </article>
      </section>

      <section v-else-if="!loading" class="session-empty">
        <p>当前没有有效的登录会话。</p>
      </section>
    </n-spin>

    <footer v-if="total" class="account-list-footer">
      <n-pagination :page="page" :page-size="perPage" :item-count="total" @update:page="changePage" />
    </footer>

    <AccountActionConfirm
      :show="Boolean(confirmation)"
      :loading="revokingOthers || Boolean(revokingId)"
      :title="confirmation?.type === 'others' ? '退出其他设备' : confirmation?.session?.is_current ? '退出当前设备' : '撤销登录设备'"
      :description="confirmation?.type === 'others' ? '这些设备上的登录状态将立即失效。' : confirmation?.session?.is_current ? '退出后需要重新登录才能继续使用账号中心。' : '撤销后，该设备需要重新登录才能访问账号。'"
      :subject="confirmation?.type === 'others' ? `其他 ${Math.max(total - 1, 0)} 台登录设备` : describeDevice(confirmation?.session?.user_agent).label"
      :detail="confirmation?.type === 'others' ? '当前设备将保持登录' : `${describeBrowser(confirmation?.session?.user_agent)} · IP ${confirmation?.session?.ip_address || '未知'}`"
      :confirm-text="confirmation?.type === 'session' && confirmation?.session?.is_current ? '退出登录' : confirmation?.type === 'others' ? '确认退出' : '确认撤销'"
      @update:show="!$event && (confirmation = null)"
      @confirm="confirmAction"
    >
      <template #icon>
        <n-icon :component="confirmation?.type === 'others' ? DesktopOutline : describeDevice(confirmation?.session?.user_agent).icon" />
      </template>
    </AccountActionConfirm>
  </div>
</template>
