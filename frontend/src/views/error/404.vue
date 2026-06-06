<template>
  <div class="not-found">
    <h1 class="not-found__code">404</h1>
    <p class="not-found__text">抱歉，您访问的页面不存在</p>
    <el-button type="primary" @click="$router.push('/dashboard')">返回首页</el-button>
  </div>
</template>

<script>
export default {
  name: 'NotFound'
}
</script>

<style scoped>
.not-found {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
}
.not-found__code {
  font-size: 72px;
  font-weight: 700;
  color: #909399;
  margin: 0 0 12px;
}
.not-found__text {
  font-size: 16px;
  color: #909399;
  margin: 0 0 24px;
}
</style>
