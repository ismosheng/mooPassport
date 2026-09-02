<script setup>
import { NIcon } from 'naive-ui'
import { ChevronForwardOutline, KeyOutline, PhonePortraitOutline, AppsOutline } from '@vicons/ionicons5'
</script>

<template>
  <div class="account-page">
    <section class="security-actions">
      <router-link class="security-action" to="/account/change-password">
        <n-icon :component="KeyOutline" />
        <div>
          <h2>修改密码</h2>
          <p>更换登录密码，并撤销全部现有登录态</p>
        </div>
        <n-icon class="security-action-arrow" :component="ChevronForwardOutline" />
      </router-link>
      <router-link class="security-action" to="/account/sessions">
        <n-icon :component="PhonePortraitOutline" />
        <div>
          <h2>登录设备</h2>
          <p>查看活跃会话，撤销可疑或不再使用的设备</p>
        </div>
        <n-icon class="security-action-arrow" :component="ChevronForwardOutline" />
      </router-link>
      <router-link class="security-action" to="/account/authorized-apps">
        <n-icon :component="AppsOutline" />
        <div>
          <h2>已授权应用</h2>
          <p>查看并撤销已授权访问你账号的第三方应用</p>
        </div>
        <n-icon class="security-action-arrow" :component="ChevronForwardOutline" />
      </router-link>
    </section>
  </div>
</template>
