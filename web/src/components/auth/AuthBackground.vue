<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import backgroundVideo from '../../assets/bg-video.mp4'
import mobileImage from '../../assets/img-bg-mobile.png'

const showVideo = ref(false)
let desktopQuery
let reducedMotionQuery

function syncMedia() {
  showVideo.value = desktopQuery.matches && !reducedMotionQuery.matches
}

onMounted(() => {
  desktopQuery = window.matchMedia('(min-width: 541px)')
  reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
  syncMedia()
  desktopQuery.addEventListener('change', syncMedia)
  reducedMotionQuery.addEventListener('change', syncMedia)
})

onBeforeUnmount(() => {
  desktopQuery?.removeEventListener('change', syncMedia)
  reducedMotionQuery?.removeEventListener('change', syncMedia)
})
</script>

<template>
  <div class="auth-visual-background" :class="{ 'is-static': !showVideo }" :style="{ '--auth-mobile-image': `url(${mobileImage})` }" aria-hidden="true">
    <video v-if="showVideo" autoplay muted playsinline preload="metadata">
      <source :src="backgroundVideo" type="video/mp4" />
    </video>
  </div>
</template>
