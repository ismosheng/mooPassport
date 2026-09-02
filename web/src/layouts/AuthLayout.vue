<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import BrandLink from '../components/app/BrandLink.vue'

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, required: true },
})

const route = useRoute()
const action = computed(() => route.name === 'login'
  ? { label: '注册账号', to: { name: 'register', query: route.query } }
  : { label: '返回登录', to: { name: 'login', query: route.query } })
</script>

<template>
  <main class="auth-shell">
    <header class="auth-topbar">
      <BrandLink />
      <router-link :to="action.to">{{ action.label }}</router-link>
    </header>
    <section class="auth-panel">
      <div class="auth-card">
        <header><h2>{{ title }}</h2><p>{{ subtitle }}</p></header>
        <slot />
      </div>
    </section>
    <footer class="auth-footer">Copyright © {{ new Date().getFullYear() }} Moo Passport</footer>
  </main>
</template>
