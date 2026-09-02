<script setup>
defineProps({
  title: { type: String, required: true },
  updatedAt: { type: String, default: '2026-09-01' },
})
</script>

<template>
  <main class="legal-shell">
    <header class="legal-topbar">
      <router-link class="brand-link" to="/">
        <img src="../assets/logo.png" alt="哞哞通行证" />
        <strong>哞哞通行证</strong>
      </router-link>
      <router-link to="/login">返回登录</router-link>
    </header>

    <article class="legal-document">
      <header class="legal-document-header">
        <h1>{{ title }}</h1>
        <p>最后更新：{{ updatedAt }}</p>
      </header>
      <div class="legal-document-body">
        <slot />
      </div>
    </article>

    <footer class="legal-footer">Copyright © {{ new Date().getFullYear() }} Moo Passport</footer>
  </main>
</template>
