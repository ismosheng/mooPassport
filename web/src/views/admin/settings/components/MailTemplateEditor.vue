<script setup>
import { computed, nextTick, ref } from 'vue'
import { NButton, NButtonGroup, NFormItem, NIcon, NInput, NTag } from 'naive-ui'
import { CodeSlashOutline, ReorderFourOutline } from '@vicons/ionicons5'
import hljs from 'highlight.js/lib/core'
import xml from 'highlight.js/lib/languages/xml'

hljs.registerLanguage('html', xml)

const props = defineProps({
  items: { type: Array, required: true },
  values: { type: Object, required: true },
})

const activeTemplate = ref('verification')
const activeFormat = ref('html')
const editorTextarea = ref(null)
const highlightLayer = ref(null)

const templates = [
  { key: 'verification', label: '邮箱验证', prefix: 'mail.verification' },
  { key: 'password_reset', label: '密码重置', prefix: 'mail.password_reset' },
]
const placeholders = [
  { value: '{{site_name}}', label: '站点名称' },
  { value: '{{display_name}}', label: '用户名称' },
  { value: '{{action_url}}', label: '操作链接' },
  { value: '{{expires_minutes}}', label: '有效分钟数' },
]
const previewValues = {
  '{{site_name}}': 'Moo Passport',
  '{{display_name}}': '示例用户',
  '{{action_url}}': 'https://id.example.com/action',
  '{{expires_minutes}}': '30',
}

const currentTemplate = computed(() => templates.find((item) => item.key === activeTemplate.value) || templates[0])
const subjectKey = computed(() => `${currentTemplate.value.prefix}.subject`)
const bodyKey = computed(() => `${currentTemplate.value.prefix}.${activeFormat.value}`)
const itemMap = computed(() => new Map(props.items.map((item) => [item.key, item])))
const subjectItem = computed(() => itemMap.value.get(subjectKey.value))
const bodyItem = computed(() => itemMap.value.get(bodyKey.value))
const previewContent = computed(() => replacePlaceholders(String(props.values[bodyKey.value] || '')))
const previewHtml = computed(() => activeFormat.value === 'html'
  ? previewContent.value
  : `<pre style="margin:0;white-space:pre-wrap;font:14px/1.7 system-ui,sans-serif;color:#1f2329">${escapeHtml(previewContent.value)}</pre>`)
const highlightedHtml = computed(() => hljs.highlight(String(props.values[bodyKey.value] || ''), { language: 'html' }).value)

function replacePlaceholders(content) {
  return Object.entries(previewValues).reduce((result, [placeholder, value]) => result.split(placeholder).join(value), content)
}

function escapeHtml(content) {
  return content.replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character])
}

async function insertPlaceholder(placeholder) {
  const key = bodyKey.value
  const textarea = editorTextarea.value?.tagName === 'TEXTAREA'
    ? editorTextarea.value
    : editorTextarea.value?.$el?.querySelector('textarea')
  const content = String(props.values[key] || '')
  const start = textarea?.selectionStart ?? content.length
  const end = textarea?.selectionEnd ?? start
  props.values[key] = `${content.slice(0, start)}${placeholder}${content.slice(end)}`
  await nextTick()
  textarea?.focus()
  textarea?.setSelectionRange(start + placeholder.length, start + placeholder.length)
}

function syncEditorScroll() {
  if (!editorTextarea.value || !highlightLayer.value) return
  highlightLayer.value.scrollTop = editorTextarea.value.scrollTop
  highlightLayer.value.scrollLeft = editorTextarea.value.scrollLeft
}

function formatHtml() {
  const source = String(props.values[bodyKey.value] || '').trim()
  if (!source) return

  const voidTags = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'])
  const tokens = source.match(/<!--[\s\S]*?-->|<![^>]*>|<[^>]+>|[^<]+/g) || []
  const lines = []
  let depth = 0

  tokens.forEach((rawToken) => {
    const token = rawToken.trim()
    if (!token) return
    if (/^<\//.test(token)) depth = Math.max(0, depth - 1)
    lines.push(`${'  '.repeat(depth)}${token}`)
    const tagName = token.match(/^<\s*([a-zA-Z][\w:-]*)/)?.[1]?.toLowerCase()
    const opensElement = tagName && !token.startsWith('</') && !token.startsWith('<!') && !token.endsWith('/>') && !voidTags.has(tagName)
    if (opensElement) depth += 1
  })

  props.values[bodyKey.value] = lines.join('\n')
  nextTick(syncEditorScroll)
}
</script>

<template>
  <div class="mail-editor">
    <div class="template-switcher">
      <span>模板类型</span>
      <n-button-group size="small">
        <n-button
          v-for="template in templates"
          :key="template.key"
          :type="activeTemplate === template.key ? 'primary' : 'default'"
          @click="activeTemplate = template.key"
        >{{ template.label }}</n-button>
      </n-button-group>
    </div>

    <n-form-item :label="subjectItem?.label || '邮件主题'">
      <n-input v-model:value="values[subjectKey]" :maxlength="subjectItem?.max_length" show-count />
      <template #feedback>{{ subjectItem?.description }}</template>
    </n-form-item>

    <div class="mail-editor-toolbar">
      <n-button-group size="small">
        <n-button :type="activeFormat === 'text' ? 'primary' : 'default'" @click="activeFormat = 'text'">纯文本</n-button>
        <n-button :type="activeFormat === 'html' ? 'primary' : 'default'" @click="activeFormat = 'html'">HTML</n-button>
      </n-button-group>
      <div class="editor-tools">
        <n-button v-if="activeFormat === 'html'" size="small" title="格式化 HTML" @click="formatHtml">
          <template #icon><n-icon :component="ReorderFourOutline" /></template>
          格式化
        </n-button>
        <div class="placeholder-list" aria-label="插入模板变量">
          <span>插入变量</span>
          <n-tag
            v-for="placeholder in placeholders"
            :key="placeholder.value"
            size="small"
            checkable
            :checked="false"
            :title="placeholder.value"
            @click="insertPlaceholder(placeholder.value)"
          >{{ placeholder.label }}</n-tag>
        </div>
      </div>
    </div>

    <div class="mail-editor-workspace">
      <section class="mail-editor-pane">
        <header><strong><n-icon :component="CodeSlashOutline" />{{ bodyItem?.label }}</strong><span>{{ bodyItem?.description }}</span></header>
        <div v-if="activeFormat === 'html'" class="code-editor">
          <pre ref="highlightLayer" aria-hidden="true"><code class="hljs" v-html="highlightedHtml" /></pre>
          <textarea
            ref="editorTextarea"
            v-model="values[bodyKey]"
            aria-label="HTML 模板内容"
            :maxlength="bodyItem?.max_length"
            spellcheck="false"
            @scroll="syncEditorScroll"
          />
        </div>
        <n-input
          v-else
          ref="editorTextarea"
          v-model:value="values[bodyKey]"
          class="template-input"
          type="textarea"
          :maxlength="bodyItem?.max_length"
          :autosize="false"
        />
      </section>
      <section class="mail-preview-pane">
        <header><strong>实时预览</strong><span>变量使用示例数据展示</span></header>
        <iframe title="邮件内容预览" sandbox="" :srcdoc="previewHtml" />
      </section>
    </div>
  </div>
</template>

<style scoped>
.mail-editor{display:grid;gap:10px}.mail-editor :deep(.n-form-item){max-width:none;margin-bottom:0}.template-switcher{display:flex;min-height:32px;align-items:center;gap:10px}.template-switcher>span{color:var(--admin-muted);font-size:var(--admin-font-xs)}.mail-editor-toolbar{display:flex;min-height:32px;align-items:center;justify-content:space-between;gap:12px}.editor-tools,.placeholder-list{display:flex;align-items:center;justify-content:flex-end;gap:6px;flex-wrap:wrap}.placeholder-list>span{color:var(--admin-muted);font-size:var(--admin-font-xs)}.placeholder-list :deep(.n-tag){cursor:pointer}.mail-editor-workspace{display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,.8fr);min-height:360px;border:1px solid var(--admin-border);border-radius:var(--radius-lg);overflow:hidden}.mail-editor-pane,.mail-preview-pane{display:grid;min-width:0;grid-template-rows:auto 1fr}.mail-editor-pane{border-right:1px solid var(--admin-border)}.mail-editor-pane header,.mail-preview-pane header{display:flex;min-height:48px;padding:7px 10px;justify-content:center;flex-direction:column;border-bottom:1px solid var(--admin-border);background:var(--color-bg-subtle)}.mail-editor-pane strong,.mail-preview-pane strong{display:flex;align-items:center;gap:5px;font-size:var(--admin-font-md)}.mail-editor-pane span,.mail-preview-pane span{color:var(--admin-muted);font-size:var(--admin-font-xs)}.template-input{height:100%}.template-input :deep(.n-input-wrapper){padding:0}.template-input :deep(.n-input__textarea-el){height:100%!important;min-height:310px;padding:12px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:var(--admin-font-sm);line-height:1.65;resize:none}.code-editor{position:relative;min-width:0;height:100%;min-height:310px;overflow:hidden;background:var(--color-bg-surface)}.code-editor pre,.code-editor textarea{box-sizing:border-box;position:absolute;inset:0;width:100%;height:100%;margin:0;padding:12px;overflow:auto;border:0;outline:0;tab-size:2;white-space:pre;word-wrap:normal;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:var(--admin-font-sm);line-height:1.65}.code-editor pre{pointer-events:none;color:var(--color-text-primary)}.code-editor textarea{z-index:1;resize:none;background:transparent;color:transparent;caret-color:var(--color-text-primary);-webkit-text-fill-color:transparent}.code-editor textarea::selection{background:color-mix(in srgb,var(--color-primary) 20%,transparent);color:transparent;-webkit-text-fill-color:transparent}.code-editor :deep(.hljs-tag),.code-editor :deep(.hljs-name){color:var(--color-primary)}.code-editor :deep(.hljs-attr){color:var(--color-error)}.code-editor :deep(.hljs-string){color:var(--color-success)}.code-editor :deep(.hljs-comment),.code-editor :deep(.hljs-meta){color:var(--admin-muted)}.mail-preview-pane iframe{box-sizing:border-box;width:100%;height:100%;min-height:310px;padding:14px;border:0;background:var(--color-bg-surface)}
@media(max-width:900px){.mail-editor-workspace{grid-template-columns:1fr}.mail-editor-pane{border-right:0;border-bottom:1px solid var(--admin-border)}}
@media(max-width:600px){.mail-editor-toolbar{align-items:flex-start;flex-direction:column}.editor-tools,.placeholder-list{justify-content:flex-start}.mail-editor-workspace{min-height:0}}
</style>
