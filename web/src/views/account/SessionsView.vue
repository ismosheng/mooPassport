<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { NButton, NIcon, NSpin, useDialog, useMessage } from 'naive-ui'
import { DesktopOutline, PhonePortraitOutline, TabletPortraitOutline } from '@vicons/ionicons5'
import { listSessions, revokeOtherSessions, revokeSession } from '../../api/auth.js'
import { useAuthStore } from '../../stores/auth.js'

const router = useRouter()
const message = useMessage()
const dialog = useDialog()
const auth = useAuthStore()
const loading = ref(true)
const revokingOthers = ref(false)
const revokingId = ref('')
const sessions = ref([])

const hasOtherSessions = computed(() => sessions.value.some((item) => !item.is_current))

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
  const response = await listSessions()
  sessions.value = response.data.data.items || []
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

async function onRevoke(session) {
  const isCurrent = session.is_current
  dialog.warning({
    title: isCurrent ? '退出当前设备？' : '撤销该登录会话？',
    content: isCurrent
      ? '退出后需要重新登录才能继续使用账号中心。'
      : '撤销后，该设备上的登录状态将立即失效。',
    positiveText: isCurrent ? '退出登录' : '确认撤销',
    negativeText: '取消',
    onPositiveClick: async () => {
      revokingId.value = session.id
      try {
        const response = await revokeSession(session.id)
        if (response.data.data.cleared_current) {
          auth.user = null
          message.success(response.data.data.message || '当前设备已退出登录')
          await router.replace('/login')
          return
        }
        message.success(response.data.data.message || '已撤销该登录会话')
        await loadSessions()
      } catch (error) {
        message.error(error.userMessage)
      } finally {
        revokingId.value = ''
      }
    },
  })
}

function onRevokeOthers() {
  dialog.warning({
    title: '退出其他设备？',
    content: '将撤销除当前设备外的全部登录会话，这些设备需要重新登录。',
    positiveText: '确认退出',
    negativeText: '取消',
    onPositiveClick: async () => {
      revokingOthers.value = true
      try {
        const response = await revokeOtherSessions()
        message.success(response.data.data.message || '已退出其他设备')
        await loadSessions()
      } catch (error) {
        message.error(error.userMessage)
      } finally {
        revokingOthers.value = false
      }
    },
  })
}
</script>

<template>
  <div class="account-page">
    <div class="account-toolbar">
      <n-button :disabled="!hasOtherSessions" :loading="revokingOthers" @click="onRevokeOthers">
        退出其他设备
      </n-button>
    </div>

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
          <n-button
            quaternary
            type="error"
            :loading="revokingId === session.id"
            @click="onRevoke(session)"
          >
            {{ session.is_current ? '退出' : '撤销' }}
          </n-button>
        </article>
      </section>

      <section v-else-if="!loading" class="session-empty">
        <p>当前没有有效的登录会话。</p>
      </section>
    </n-spin>
  </div>
</template>
