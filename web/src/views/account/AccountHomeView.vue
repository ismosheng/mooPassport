<script setup>
import { computed } from 'vue'
import { NButton, NIcon, useMessage } from 'naive-ui'
import { CheckmarkCircle, CopyOutline } from '@vicons/ionicons5'
import { useAuthStore } from '../../stores/auth.js'

const message = useMessage()
const auth = useAuthStore()

const displayName = computed(() => auth.user?.display_name || auth.user?.username || '用户')
const emailVerified = computed(() => Boolean(auth.user?.email_verified_at))
const statusLabel = computed(() => {
  const status = auth.user?.status
  if (status === 'active') return '正常'
  if (status === 'locked') return '已锁定'
  if (status === 'disabled') return '已禁用'
  return status || '未知'
})
const createdAt = computed(() => {
  if (!auth.user?.created_at) return '未知'
  const date = new Date(auth.user.created_at)
  return Number.isNaN(date.getTime()) ? auth.user.created_at : date.toLocaleString('zh-CN', { hour12: false })
})

async function copyAccountId() {
  const id = auth.user?.id
  if (!id) return
  try {
    await navigator.clipboard.writeText(id)
    message.success('账号 ID 已复制')
  } catch {
    message.error('复制失败，请手动选择复制')
  }
}
</script>

<template>
  <div class="account-page">
    <section class="account-panel account-overview-card">
      <div class="account-hero-main">
        <div class="account-avatar account-avatar-lg" aria-hidden="true">
          <img :src="auth.user.avatar_url" alt="" />
        </div>
        <div class="account-hero-copy">
          <h2>{{ displayName }}</h2>
          <p class="account-hero-username">@{{ auth.user.username }}</p>
          <div class="account-hero-tags">
            <span v-if="emailVerified" class="account-tag account-tag-success">
              <n-icon :component="CheckmarkCircle" /> 邮箱已验证
            </span>
            <span v-else class="account-tag">邮箱未验证</span>
            <span class="account-tag">账号{{ statusLabel }}</span>
          </div>
        </div>
      </div>

      <dl class="account-overview">
        <div>
          <dt>显示名称</dt>
          <dd>{{ displayName }}</dd>
        </div>
        <div>
          <dt>用户名</dt>
          <dd>@{{ auth.user.username }}</dd>
        </div>
        <div>
          <dt>邮箱</dt>
          <dd>{{ auth.user.email }}</dd>
        </div>
        <div>
          <dt>注册时间</dt>
          <dd>{{ createdAt }}</dd>
        </div>
        <div>
          <dt>账号 ID</dt>
          <dd class="account-overview-id">
            <code>{{ auth.user.id }}</code>
            <n-button size="tiny" quaternary @click="copyAccountId">
              <template #icon><n-icon :component="CopyOutline" /></template>
              复制
            </n-button>
          </dd>
        </div>
      </dl>
    </section>
  </div>
</template>
