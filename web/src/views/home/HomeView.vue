<script setup>
import { computed, h, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowForwardOutline, DiamondOutline, HappyOutline, HeartOutline, HomeOutline, LockClosedOutline, LogOutOutline, PersonCircleOutline, PlanetOutline, RibbonOutline, ShieldCheckmarkOutline, SparklesOutline, StarOutline, TrophyOutline } from '@vicons/ionicons5'
import { NButton, NDropdown, NIcon, NLayout, NLayoutContent, NLayoutFooter, NLayoutHeader, NSpin, useMessage } from 'naive-ui'
import { getPublicSite } from '../../api/publicSite.js'
import { useAuthStore } from '../../stores/auth.js'
import homepageVideo from '../../assets/index.mp4'
import logo from '../../assets/logo.png'

const router = useRouter()
const message = useMessage()
const auth = useAuthStore()
const loading = ref(true)
const siteName = ref('哞哞通行证')
const avatarLoadFailed = ref(false)
const loggingOut = ref(false)

const easterEggs = [
  { key: 'title-left-outer', component: SparklesOutline, className: 'egg-title-left-outer', style: { '--egg-size': '92px', '--egg-rotate': '-12deg' } },
  { key: 'title-left-inner', component: StarOutline, className: 'egg-title-left-inner', style: { '--egg-size': '88px', '--egg-rotate': '9deg' } },
  { key: 'title-right-inner', component: DiamondOutline, className: 'egg-title-right-inner', style: { '--egg-size': '96px', '--egg-rotate': '-8deg' } },
  { key: 'title-right-outer', component: TrophyOutline, className: 'egg-title-right-outer', style: { '--egg-size': '98px', '--egg-rotate': '12deg' } },
  { key: 'action-left', component: HeartOutline, className: 'egg-action-left', style: { '--egg-size': '94px', '--egg-rotate': '-9deg' } },
  { key: 'action-right', component: HappyOutline, className: 'egg-action-right', style: { '--egg-size': '92px', '--egg-rotate': '7deg' } },
  { key: 'identity', component: RibbonOutline, className: 'egg-identity', style: { '--egg-size': '94px', '--egg-rotate': '-10deg' } },
  { key: 'session', component: PlanetOutline, className: 'egg-session', style: { '--egg-size': '100px', '--egg-rotate': '10deg' } },
]

const displayName = computed(() => auth.user?.display_name || auth.user?.username || '用户')
const avatarUrl = computed(() => avatarLoadFailed.value ? '' : auth.user?.avatar_url || '')
const userOptions = computed(() => [
  { label: '账号中心', key: 'account', icon: () => h(NIcon, null, { default: () => h(HomeOutline) }) },
  { label: '个人资料', key: 'profile', icon: () => h(NIcon, null, { default: () => h(PersonCircleOutline) }) },
  { type: 'divider', key: 'divider' },
  { label: loggingOut.value ? '正在退出' : '退出登录', key: 'logout', disabled: loggingOut.value, icon: () => h(NIcon, null, { default: () => h(LogOutOutline) }) },
])

watch(() => auth.user?.avatar_url, () => { avatarLoadFailed.value = false })

async function resolveHomepage() {
  const currentUserRequest = auth.user
    ? Promise.resolve(auth.user)
    : auth.loadCurrentUser().catch((error) => {
        if (error.response?.status !== 401) message.error(error.userMessage)
        return null
      })

  try {
    const [siteResponse] = await Promise.all([getPublicSite(), currentUserRequest])
    const configuration = siteResponse.data.data || {}
    siteName.value = configuration.site_name || siteName.value
    if (configuration.homepage_enabled === false) {
      await router.replace('/account')
      return
    }
  } catch {
    // 配置接口短暂不可用时仍保留公开入口，避免根页面变成空白。
  }
  loading.value = false
}

function navigate(path) {
  router.push(path)
}

async function handleUserSelect(key) {
  if (key === 'account') {
    await router.push('/account')
    return
  }
  if (key === 'profile') {
    await router.push('/account/profile')
    return
  }
  if (key !== 'logout' || loggingOut.value) return

  loggingOut.value = true
  try {
    await auth.signOut()
    message.success('已退出登录')
  } catch (error) {
    message.error(error.userMessage || '退出失败，请稍后重试')
  } finally {
    loggingOut.value = false
  }
}

onMounted(resolveHomepage)
</script>

<template>
  <div v-if="loading" class="home-resolving" aria-label="正在加载站点">
    <n-spin size="small" />
  </div>
  <n-layout v-else class="home-page">
    <n-layout-header class="home-header">
      <n-button class="home-brand" text :aria-label="`${siteName}首页`" @click="navigate('/')">
        <img :src="logo" alt="" />
        <span>{{ siteName }}</span>
      </n-button>
      <nav class="home-nav" aria-label="主导航">
        <n-button text @click="navigate('/')">首页</n-button>
        <n-button text @click="navigate('/account')">账号中心</n-button>
        <n-button text @click="navigate('/account/security')">安全与隐私</n-button>
        <n-button text @click="navigate('/account/authorized-apps')">授权应用</n-button>
      </nav>
      <n-dropdown v-if="auth.user" trigger="click" placement="bottom-end" :options="userOptions" @select="handleUserSelect">
        <n-button class="home-user" quaternary :title="displayName">
          <span class="home-user-avatar" aria-hidden="true">
            <img v-if="avatarUrl" :src="avatarUrl" alt="" @error="avatarLoadFailed = true" />
            <n-icon v-else :component="PersonCircleOutline" />
          </span>
          <span class="home-user-name">{{ displayName }}</span>
        </n-button>
      </n-dropdown>
      <n-button v-else class="home-login" type="primary" @click="navigate('/login')">
        <template #icon><n-icon :component="PersonCircleOutline" /></template>
        登录
      </n-button>
    </n-layout-header>

    <n-layout-content
      class="home-hero"
      content-class="home-hero-inner"
    >
      <video class="home-video" autoplay muted loop playsinline preload="auto" aria-hidden="true">
        <source :src="homepageVideo" type="video/mp4" />
      </video>
      <div class="home-raster" aria-hidden="true" />
      <div class="home-shade" aria-hidden="true" />
      <div class="home-easter-eggs" aria-hidden="true">
        <div
          v-for="egg in easterEggs"
          :key="egg.key"
          :class="['home-easter-egg', egg.className]"
          :style="egg.style"
        >
          <span class="home-easter-glow" />
          <n-icon class="home-easter-icon" :component="egg.component" />
        </div>
      </div>

      <main class="home-hero-content">
        <div class="home-title-area">
          <img :src="logo" alt="" />
          <h1>{{ siteName }}</h1>
        </div>
        <div class="home-actions">
          <n-button class="home-primary-action" size="large" round @click="navigate('/account')">
            进入账号中心
            <template #icon><n-icon :component="ArrowForwardOutline" /></template>
          </n-button>
        </div>
        <p class="home-lead">一个账号，安全连接你的应用与服务。授权范围清晰可见，登录状态始终由你掌控。</p>
        <n-button class="home-privacy-action" text @click="navigate('/privacy')">了解隐私保护</n-button>
      </main>

      <div class="home-capabilities" aria-label="产品能力">
        <div>
          <n-icon :component="PersonCircleOutline" />
          <span><strong>统一身份</strong><small>一个账号访问已连接服务</small></span>
        </div>
        <div>
          <n-icon :component="ShieldCheckmarkOutline" />
          <span><strong>授权可控</strong><small>清楚了解应用获得的信息</small></span>
        </div>
        <div>
          <n-icon :component="LockClosedOutline" />
          <span><strong>会话可见</strong><small>随时管理设备与登录状态</small></span>
        </div>
      </div>

      <n-layout-footer class="home-footer">
        <span>© {{ new Date().getFullYear() }} {{ siteName }}</span>
        <nav aria-label="页脚导航">
          <n-button text @click="navigate('/terms')">服务条款</n-button>
          <n-button text @click="navigate('/privacy')">隐私政策</n-button>
          <n-button text @click="navigate(auth.user ? '/account' : '/login')">{{ auth.user ? '账号中心' : '登录' }}</n-button>
        </nav>
      </n-layout-footer>
    </n-layout-content>
  </n-layout>
</template>

<style scoped>
.home-resolving{min-height:100vh;display:grid;place-items:center;background:var(--color-bg-page)}
.home-page{height:100svh;overflow:hidden;background:var(--color-bg-surface)!important;-webkit-user-select:none;user-select:none}
.home-header{position:relative;z-index:5;height:64px;padding:0 48px;display:flex;align-items:center;gap:44px;background:var(--color-bg-surface)}
.home-brand{flex:0 0 auto;--n-text-color:var(--color-text-primary)!important;--n-text-color-hover:var(--color-text-primary)!important;font-size:var(--font-size-md);font-weight:600}
.home-brand :deep(.n-button__content){display:inline-flex;align-items:center;gap:9px}
.home-brand img{width:38px;height:38px;display:block;object-fit:contain}
.home-nav{display:flex;align-items:center;gap:4px}
.home-nav .n-button{padding:8px 16px;border-radius:var(--radius-md);--n-text-color:var(--color-text-secondary)!important;--n-text-color-hover:var(--color-primary)!important;font-size:var(--font-size-md);font-weight:500}
.home-nav .n-button:hover{background:var(--color-bg-subtle)}
.home-login{margin-left:auto;min-width:92px;border-radius:var(--radius-md)}
.home-user{margin-left:auto;max-width:220px;border-radius:var(--radius-md);--n-text-color:var(--color-text-primary)!important;--n-text-color-hover:var(--color-text-primary)!important}
.home-user :deep(.n-button__content){min-width:0;display:flex;align-items:center;gap:8px}
.home-user-avatar{width:30px;height:30px;display:grid;place-items:center;overflow:hidden;flex:0 0 auto;border-radius:50%;color:var(--color-text-inverse);background:var(--color-primary);font-size:var(--font-size-lg)}
.home-user-avatar img{width:100%;height:100%;display:block;object-fit:cover}
.home-user-name{min-width:0;max-width:142px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:var(--font-size-sm);font-weight:600}
.home-hero{position:relative;height:calc(100svh - 64px);overflow:hidden;color:var(--color-text-inverse);background:var(--color-text-primary)!important}
.home-hero :deep(.home-hero-inner){position:relative;height:100%;min-height:0;display:grid;grid-template-rows:minmax(0,1fr) auto auto;overflow:hidden}
.home-video,.home-raster,.home-shade{position:absolute;inset:0;width:100%;height:100%}
.home-video{z-index:0;display:block;object-fit:cover}
.home-raster{z-index:1;backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);background:repeating-linear-gradient(90deg,rgba(255,255,255,.1) 0,rgba(255,255,255,.035) 18px,rgba(0,0,0,.09) 35px,rgba(255,255,255,.1) 36px)}
.home-shade{z-index:2;background:rgba(7,12,18,.16)}
.home-easter-eggs{position:absolute;z-index:5;inset:0;overflow:hidden;pointer-events:none}
.home-easter-egg{position:absolute;width:230px;height:230px;display:grid;place-items:center;transform:translate(-50%,-50%);pointer-events:auto}
.egg-title-left-outer{left:26%;top:15%}
.egg-title-left-inner{left:38%;top:24%}
.egg-title-right-inner{left:62%;top:24%}
.egg-title-right-outer{left:74%;top:15%}
.egg-action-left{left:30%;top:49%}
.egg-action-right{left:70%;top:49%}
.egg-identity{left:8%;top:75%}
.egg-session{left:92%;top:75%}
.home-easter-egg::before,.home-easter-egg::after{content:"";position:absolute;width:9px;height:9px;border-radius:50%;opacity:0;background:rgba(255,255,255,.98);box-shadow:0 0 8px rgba(255,255,255,.98),0 0 20px rgba(255,255,255,.8);transition:opacity .2s ease,transform .5s cubic-bezier(.2,.8,.2,1)}
.home-easter-egg::before{transform:translate(-46px,34px) scale(0)}
.home-easter-egg::after{width:6px;height:6px;transform:translate(54px,-42px) scale(0)}
.home-easter-glow{position:absolute;width:230px;height:230px;border-radius:50%;opacity:0;filter:blur(58px);background:rgba(255,255,255,.76);transform:scale(.38);transition:opacity .34s ease,transform .48s cubic-bezier(.2,.8,.2,1)}
.home-easter-icon{width:var(--egg-size);height:var(--egg-size);color:rgba(255,255,255,.98);font-size:var(--egg-size);opacity:0;filter:blur(.2px) drop-shadow(0 0 7px rgba(255,255,255,.98)) drop-shadow(0 0 22px rgba(255,255,255,.82));transform:scale(.62) rotate(calc(var(--egg-rotate) - 8deg));transition:opacity .22s ease,transform .48s cubic-bezier(.2,.8,.2,1);will-change:opacity,transform}
.home-easter-egg:hover::before{opacity:.9;transform:translate(-72px,55px) scale(1)}
.home-easter-egg:hover::after{opacity:.78;transform:translate(78px,-62px) scale(1)}
.home-easter-egg:hover .home-easter-glow{opacity:.76;transform:scale(1)}
.home-easter-egg:hover .home-easter-icon{opacity:.96;transform:scale(1) rotate(var(--egg-rotate))}
.home-hero-content,.home-capabilities,.home-footer{position:relative;z-index:6;pointer-events:none}
.home-actions,.home-privacy-action,.home-footer nav{pointer-events:auto}
.home-hero-content{width:min(calc(100% - 64px),var(--content-width));margin:0 auto;padding:42px 0 36px;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center}
.home-title-area{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px}
.home-title-area img{width:104px;height:104px;display:block;object-fit:contain;filter:drop-shadow(0 2px 18px rgba(0,0,0,.14))}
.home-title-area h1{margin:0;font-size:calc(var(--font-size-2xl) * 2);font-weight:600;line-height:1.12;text-shadow:0 0 5px rgba(255,255,255,.92),0 0 16px rgba(255,255,255,.7),0 0 34px rgba(255,255,255,.5),0 2px 20px rgba(0,0,0,.2)}
.home-actions{margin-top:30px;display:flex;align-items:center;justify-content:center}
.home-actions :deep(.n-button){min-width:294px;height:76px;padding-inline:38px;font-size:var(--font-size-xl);font-weight:600}
.home-actions :deep(.n-button__icon){margin-right:12px;font-size:var(--font-size-xl)}
.home-primary-action{--n-color:var(--color-bg-surface)!important;--n-color-hover:var(--color-bg-subtle)!important;--n-color-pressed:var(--color-border)!important;--n-text-color:var(--color-text-primary)!important;--n-text-color-hover:var(--color-text-primary)!important;--n-text-color-pressed:var(--color-text-primary)!important;--n-border:none!important;--n-border-hover:none!important;box-shadow:0 4px 24px rgba(0,0,0,.14);transition:transform .2s ease,box-shadow .2s ease}
.home-primary-action:hover{transform:translateY(-2px);box-shadow:0 8px 32px rgba(0,0,0,.18)}
.home-lead{max-width:720px;margin:22px 0 0;color:rgba(0,0,0,.78);font-size:var(--font-size-md);font-weight:500;line-height:1.65;text-shadow:0 1px 8px rgba(255,255,255,.12)}
.home-privacy-action{margin-top:12px;--n-text-color:var(--color-text-inverse)!important;--n-text-color-hover:var(--color-text-inverse)!important;font-size:var(--font-size-sm);border-bottom:1px solid rgba(255,255,255,.55);border-radius:0}
.home-capabilities{width:min(calc(100% - 64px),var(--content-width));margin:0 auto;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));border-top:1px solid rgba(255,255,255,.2)}
.home-capabilities>div{min-height:108px;padding:22px 30px;display:flex;align-items:center;justify-content:center;gap:14px;border-left:1px solid rgba(255,255,255,.2)}
.home-capabilities>div:first-child{border-left:0}
.home-capabilities .n-icon{flex:0 0 auto;color:rgba(0,0,0,.42);font-size:var(--font-size-xl)}
.home-capabilities span{display:grid;gap:2px}
.home-capabilities strong{color:rgba(0,0,0,.5);font-size:var(--font-size-sm);font-weight:600}
.home-capabilities small{color:rgba(0,0,0,.4);font-size:var(--font-size-xs)}
.home-footer{width:min(calc(100% - 64px),var(--content-width));min-height:58px;margin:0 auto;padding:0;display:flex;align-items:center;justify-content:space-between;gap:24px;color:rgba(0,0,0,.56);background:transparent!important;font-size:var(--font-size-xs)}
.home-footer nav{display:flex;gap:24px}
.home-footer .n-button{--n-text-color:rgba(0,0,0,.72)!important;--n-text-color-hover:rgba(0,0,0,.92)!important;font-size:var(--font-size-xs)}
@media(max-width:720px){.home-header{height:60px;padding:0 18px;gap:12px}.home-nav{display:none}.home-brand{min-width:0}.home-brand :deep(.n-button__content){min-width:0}.home-brand img{width:34px;height:34px;flex:none}.home-brand span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.home-login{min-width:80px}.home-user{min-width:max-content;max-width:none;flex:0 0 auto;padding-inline:8px}.home-user-name{max-width:120px}.home-hero{height:calc(100svh - 60px)}.home-raster{backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);background:repeating-linear-gradient(90deg,rgba(255,255,255,.09) 0,rgba(255,255,255,.025) 18px,rgba(0,0,0,.08) 35px,rgba(255,255,255,.09) 36px)}.home-easter-eggs{display:none}.home-hero-content{width:calc(100% - 40px);padding:24px 0 18px}.home-title-area{gap:6px}.home-title-area img{width:64px;height:64px}.home-title-area h1{font-size:var(--font-size-2xl)}.home-actions{margin-top:18px}.home-actions :deep(.n-button){min-width:230px;height:54px;padding-inline:28px;font-size:var(--font-size-md)}.home-lead{max-width:420px;margin-top:14px;font-size:var(--font-size-base);line-height:1.5}.home-privacy-action{margin-top:8px}.home-capabilities{width:calc(100% - 32px);grid-template-columns:1fr}.home-capabilities>div{min-height:52px;padding:8px 6px;justify-content:flex-start;border-left:0;border-top:1px solid rgba(255,255,255,.16)}.home-capabilities>div:first-child{border-top:0}.home-footer{width:calc(100% - 32px);min-height:54px;flex-direction:row;justify-content:space-between;gap:10px}.home-footer nav{gap:10px}}
@media(max-width:380px){.home-brand span{max-width:92px}.home-user-name{max-width:76px}.home-capabilities small{display:none}.home-capabilities>div{min-height:54px}.home-hero-content{padding-block:30px 22px}.home-title-area img{width:48px;height:48px}}
@media(prefers-reduced-motion:reduce){.home-video,.home-easter-eggs{display:none}}
</style>
