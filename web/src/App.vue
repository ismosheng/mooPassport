<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { NConfigProvider, NDialogProvider, NMessageProvider, dateZhCN, zhCN } from 'naive-ui'
import { themeOverrides } from './theme/index.js'
import AuthBackground from './components/auth/AuthBackground.vue'

const route = useRoute()
const showAuthBackground = computed(() => route.meta.authBackground === true)
</script>

<template>
  <n-config-provider :locale="zhCN" :date-locale="dateZhCN" :theme-overrides="themeOverrides">
    <AuthBackground v-if="showAuthBackground" />
    <n-message-provider>
      <n-dialog-provider>
        <router-view />
      </n-dialog-provider>
    </n-message-provider>
  </n-config-provider>
</template>
