<script setup>
import logo from '../assets/logo.png'

defineProps({
  to: { type: String, default: '/' },
})
</script>

<template>
  <router-link class="brand-link" :to="to">
    <img :src="logo" alt="哞哞通行证" />
    <strong>哞哞通行证</strong>
  </router-link>
</template>
