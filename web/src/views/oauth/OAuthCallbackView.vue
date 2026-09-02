<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { NAlert, NButton, NIcon, NResult } from 'naive-ui'
import { CheckmarkCircleOutline, CloseCircleOutline } from '@vicons/ionicons5'
import BrandLink from '../../components/BrandLink.vue'

const route = useRoute()
const params = ref({})

const hasError = computed(() => Boolean(params.value.error))
const hasCode = computed(() => Boolean(params.value.code))

onMounted(() => {
  params.value = Object.fromEntries(
    Object.entries(route.query).filter(([, value]) => typeof value === 'string'),
  )
})
</script>

<template>
  <main class="oauth-error-shell">
    <header class="oauth-error-topbar">
      <BrandLink to="/account" />
    </header>

    <section class="oauth-error-card oauth-callback-card">
      <n-result
        v-if="hasError"
        status="error"
        title="授权回调收到错误"
        :description="params.error_description || params.error"
      >
        <template #icon><n-icon :component="CloseCircleOutline" /></template>
      </n-result>

      <n-result
        v-else-if="hasCode"
        status="success"
        title="授权回调成功"
        description="浏览器已成功收到授权码，可用于换取 Access Token。"
      >
        <template #icon><n-icon :component="CheckmarkCircleOutline" color="var(--color-success)" /></template>
      </n-result>

      <n-result v-else status="info" title="OAuth 回调调试页" description="将本页配置为测试应用的 redirect_uri，完成授权后会在这里显示回调参数。" />

      <n-alert v-if="Object.keys(params).length" type="info" :show-icon="false" class="oauth-callback-params">
        <dl>
          <div v-for="(value, key) in params" :key="key">
            <dt>{{ key }}</dt>
            <dd><code>{{ value }}</code></dd>
          </div>
        </dl>
      </n-alert>

      <div class="oauth-callback-actions">
        <router-link to="/account/authorized-apps"><n-button>查看已授权应用</n-button></router-link>
      </div>
    </section>
  </main>
</template>
