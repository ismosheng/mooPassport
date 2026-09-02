<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { NButton, NEmpty, NForm, NFormItem, NInput, NInputNumber, NSkeleton, NSpace, NSwitch, useMessage } from 'naive-ui'
import { getSettings, updateSettings } from '../../api/settings.js'

const message = useMessage()
const loading = ref(true)
const saving = ref(false)
const items = ref([])
const activeGroup = ref('site')
const values = reactive({})
const versions = reactive({})

const groupMeta = {
  site: { title: '基础设置', description: '站点名称与公开访问地址' },
  auth: { title: '账号安全', description: '注册开关与登录会话策略' },
  oauth: { title: 'OAuth / OIDC', description: '令牌有效期与协议策略' },
  audit: { title: '安全审计', description: '在线审计数据保留策略' },
}

const groups = computed(() => {
  const grouped = Object.keys(groupMeta).map((key) => ({ key, ...groupMeta[key], items: [] }))
  const groupMap = new Map(grouped.map((group) => [group.key, group]))
  items.value.forEach((item) => {
    const group = groupMap.get(item.key.split('.')[0])
    if (group) group.items.push(item)
  })
  return grouped.filter((group) => group.items.length)
})

const activeItems = computed(() => groups.value.find((group) => group.key === activeGroup.value)?.items || [])

function selectInitialGroup() {
  if (!groups.value.some((group) => group.key === activeGroup.value)) activeGroup.value = groups.value[0]?.key || ''
}

async function load() {
  loading.value = true
  try {
    const data = (await getSettings()).data.data
    items.value = Array.isArray(data.items) ? data.items : []
    items.value.forEach((item) => {
      values[item.key] = item.value
      versions[item.key] = item.version
    })
    selectInitialGroup()
  } catch (error) {
    message.error(error.userMessage)
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    await updateSettings(values, versions)
    message.success('系统设置已保存')
    await load()
  } catch (error) {
    message.error(error.userMessage)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="settings-page">
    <header class="settings-header">
      <div>
        <p class="settings-eyebrow">系统配置</p>
        <h1>系统设置</h1>
        <span>可在线修改的配置由服务端白名单控制，敏感凭据不会出现在这里。</span>
      </div>
      <n-space>
        <n-button :loading="loading" @click="load">刷新</n-button>
        <n-button type="primary" :loading="saving" :disabled="loading || !items.length" @click="save">保存设置</n-button>
      </n-space>
    </header>

    <div v-if="loading" class="settings-loading">
      <n-skeleton text :repeat="5" />
      <n-skeleton text :repeat="8" />
    </div>
    <n-empty v-else-if="!groups.length" description="暂无可管理的系统设置" />
    <div v-else class="settings-layout">
      <nav class="settings-nav" aria-label="系统设置分组">
        <button
          v-for="group in groups"
          :key="group.key"
          class="settings-nav-item"
          :class="{ active: activeGroup === group.key }"
          type="button"
          @click="activeGroup = group.key"
        >
          <strong>{{ group.title }}</strong>
          <span>{{ group.description }}</span>
        </button>
      </nav>

      <section class="settings-form-panel">
        <header>
          <h2>{{ groups.find((group) => group.key === activeGroup)?.title }}</h2>
          <p>{{ groups.find((group) => group.key === activeGroup)?.description }}</p>
        </header>
        <n-form label-placement="top">
          <n-form-item v-for="item in activeItems" :key="item.key" :label="item.description">
            <n-switch v-if="item.type === 'boolean'" v-model:value="values[item.key]" />
            <n-input-number v-else-if="item.type === 'integer'" v-model:value="values[item.key]" :min="item.min" :max="item.max" :show-button="false" />
            <n-input v-else v-model:value="values[item.key]" />
          </n-form-item>
        </n-form>
        <div class="settings-form-footer">
          <span>修改将立即写入配置并记录安全审计。</span>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.settings-page{display:grid;gap:12px;width:100%;padding:12px 0 24px;align-content:start}
.settings-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
.settings-header h1{margin:2px 0 4px;color:var(--admin-heading);font-size:var(--admin-font-xl);font-weight:600}
.settings-header span,.settings-header p,.settings-form-panel p{margin:0;color:var(--admin-muted);font-size:var(--admin-font-sm)}
.settings-eyebrow{color:var(--color-primary)!important;font-size:var(--admin-font-xs)!important;font-weight:600}
.settings-layout{display:grid;grid-template-columns:210px minmax(0,1fr);gap:12px;align-items:start}
.settings-nav{display:grid;gap:4px}
.settings-nav-item{display:grid;padding:10px 12px;gap:2px;text-align:left;border:1px solid var(--admin-border);border-radius:var(--radius-lg);background:var(--color-bg-surface);color:var(--color-text-primary);cursor:pointer}
.settings-nav-item:hover,.settings-nav-item.active{border-color:var(--color-primary);background:var(--color-primary-soft)}
.settings-nav-item strong{font-size:var(--admin-font-md);font-weight:600}
.settings-nav-item span{color:var(--admin-muted);font-size:var(--admin-font-xs)}
.settings-form-panel{min-width:0;padding:14px 16px;border:1px solid var(--admin-border);border-radius:var(--radius-lg);background:var(--color-bg-surface)}
.settings-form-panel header{padding-bottom:10px;margin-bottom:12px;border-bottom:1px solid var(--admin-border)}
.settings-form-panel h2{margin:0 0 4px;color:var(--admin-heading);font-size:var(--admin-font-lg)}
.settings-form-panel :deep(.n-form-item){max-width:560px;margin-bottom:8px}
.settings-form-panel :deep(.n-form-item-label){padding-bottom:4px}
.settings-form-panel :deep(.n-input-number){width:100%}
.settings-form-footer{margin-top:2px;padding-top:12px;border-top:1px solid var(--admin-border);color:var(--admin-muted);font-size:var(--admin-font-xs)}
.settings-loading{display:grid;grid-template-columns:210px minmax(0,1fr);gap:12px}
.settings-loading :deep(.n-skeleton){margin-bottom:8px}
@media(max-width:700px){.settings-header{display:grid}.settings-layout,.settings-loading{grid-template-columns:1fr}.settings-nav{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:480px){.settings-nav{grid-template-columns:1fr}.settings-page{padding-top:12px}.settings-form-panel{padding:12px}}
</style>
