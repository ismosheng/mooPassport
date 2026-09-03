<script setup>
import { NButton, NIcon, NModal } from 'naive-ui'
import { CloseOutline, WarningOutline } from '@vicons/ionicons5'

defineProps({
  show: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  title: { type: String, required: true },
  description: { type: String, required: true },
  subject: { type: String, required: true },
  detail: { type: String, default: '' },
  confirmText: { type: String, default: '确认撤销' },
})

const emit = defineEmits(['update:show', 'confirm'])

function close() {
  emit('update:show', false)
}
</script>

<template>
  <n-modal :show="show" :mask-closable="!loading" :close-on-esc="!loading" @update:show="emit('update:show', $event)">
    <section class="action-confirm" role="dialog" aria-modal="true" :aria-labelledby="`${$attrs.id || 'action'}-title`">
      <button class="action-confirm-close" type="button" aria-label="关闭" :disabled="loading" @click="close">
        <n-icon :component="CloseOutline" />
      </button>

      <header>
        <span class="action-confirm-warning"><n-icon :component="WarningOutline" /></span>
        <div>
          <h2 :id="`${$attrs.id || 'action'}-title`">{{ title }}</h2>
          <p>{{ description }}</p>
        </div>
      </header>

      <div class="action-confirm-target">
        <span class="action-confirm-target-icon"><slot name="icon" /></span>
        <div>
          <strong>{{ subject }}</strong>
          <small v-if="detail">{{ detail }}</small>
        </div>
      </div>

      <footer>
        <n-button :disabled="loading" @click="close">取消</n-button>
        <n-button type="error" :loading="loading" @click="emit('confirm')">{{ confirmText }}</n-button>
      </footer>
    </section>
  </n-modal>
</template>

<style scoped>
.action-confirm{box-sizing:border-box;position:relative;width:430px;max-width:calc(100vw - 32px);padding:24px;border-radius:var(--radius-xl);background:var(--color-bg-surface);box-shadow:var(--shadow-panel)}
.action-confirm-close{position:absolute;right:12px;top:12px;width:30px;height:30px;display:grid;padding:0;place-items:center;border:0;border-radius:var(--radius-md);background:transparent;color:var(--color-text-tertiary);font-size:var(--font-size-lg);cursor:pointer}
.action-confirm-close:hover{background:var(--color-bg-subtle);color:var(--color-text-primary)}
.action-confirm-close:disabled{cursor:not-allowed;opacity:.5}
.action-confirm>header{display:flex;padding-right:28px;align-items:center;gap:12px}
.action-confirm-warning{width:40px;height:40px;display:grid;place-items:center;flex:none;border-radius:50%;background:color-mix(in srgb,var(--color-error) 8%,var(--color-bg-surface));color:var(--color-error);font-size:var(--font-size-lg)}
.action-confirm h2{margin:0;color:var(--color-text-primary);font-size:var(--font-size-md);font-weight:600}
.action-confirm header p{margin:3px 0 0;color:var(--color-text-tertiary);font-size:var(--font-size-sm);line-height:1.6}
.action-confirm-target{display:flex;margin-top:20px;padding:14px;align-items:center;gap:11px;border:1px solid var(--color-border);border-radius:var(--radius-lg);background:var(--color-bg-subtle)}
.action-confirm-target-icon{width:36px;height:36px;display:grid;place-items:center;flex:none;border-radius:var(--radius-md);background:var(--color-bg-surface);color:var(--color-primary);font-size:var(--font-size-lg)}
.action-confirm-target>div{min-width:0;display:grid;gap:2px}
.action-confirm-target strong,.action-confirm-target small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.action-confirm-target strong{color:var(--color-text-primary);font-size:var(--font-size-sm);font-weight:600}
.action-confirm-target small{color:var(--color-text-tertiary);font-size:var(--font-size-xs)}
.action-confirm footer{display:flex;margin-top:20px;justify-content:flex-end;gap:10px}
.action-confirm footer :deep(.n-button){height:36px;min-width:88px}
</style>
