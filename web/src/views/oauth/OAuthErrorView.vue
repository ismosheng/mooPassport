<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { NButton, NIcon, NResult } from 'naive-ui'
import { AlertCircleOutline } from '@vicons/ionicons5'
import BrandLink from '../../components/BrandLink.vue'
import { resolveOAuthError } from './oauthError.js'

const route = useRoute()
const { error, description } = resolveOAuthError(route.query)
const clientId = computed(() => (typeof route.query.client_id === 'string' ? route.query.client_id : ''))
const isRedirectUriError = computed(() => (
  error === 'invalid_request' && description.includes('redirect_uri')
))
</script>

<template>
  <main class="oauth-error-shell">
    <header class="oauth-error-topbar">
      <BrandLink to="/account" />
    </header>

    <section class="oauth-error-card">
      <n-result status="error" :title="isRedirectUriError ? '回调地址无效' : '授权失败'" :description="description">
        <template #icon><n-icon :component="AlertCircleOutline" color="var(--color-text-tertiary)" /></template>
        <template #footer>
          <div class="oauth-error-meta">
            <p v-if="clientId">应用 ID：<code>{{ clientId }}</code></p>
            <p v-if="isRedirectUriError">
              请确认第三方应用配置的 redirect_uri 已在哞哞通行证登记，且与发起授权时使用的地址完全一致。
            </p>
            <router-link to="/login"><n-button size="large">返回登录</n-button></router-link>
          </div>
        </template>
      </n-result>
    </section>
  </main>
</template>
