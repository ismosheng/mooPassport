<script setup>
import LegalDocumentLayout from './components/LegalDocumentLayout.vue'
</script>

<template>
  <LegalDocumentLayout title="隐私政策">
    <section>
      <h2>1. 我们收集的信息</h2>
      <p>为提供账号服务，我们会处理你主动提交的邮箱、用户名、显示名称，以及登录设备、IP 地址、浏览器标识等安全相关日志。</p>
    </section>
    <section>
      <h2>2. 信息使用目的</h2>
      <p>上述信息用于账号注册与验证、登录态维护、密码重置、OAuth 授权、安全审计与异常检测，不会用于与本服务无关的营销目的。</p>
    </section>
    <section>
      <h2>3. 信息共享</h2>
      <p>除法律法规要求，或经你明确授权第三方应用通过 OAuth 获取的信息外，我们不会向无关第三方出售或出租你的个人信息。</p>
    </section>
    <section>
      <h2>4. 数据安全</h2>
      <p>密码以不可逆哈希形式存储，敏感凭据不会明文记录。我们采用访问控制、审计日志等措施保护账号安全。</p>
    </section>
    <section>
      <h2>5. 你的权利</h2>
      <p>你可以在账号中心查看基础资料、管理登录设备，并撤销第三方应用授权。如需进一步处理个人信息，可通过平台提供的联系方式与我们沟通。</p>
    </section>
  </LegalDocumentLayout>
</template>
