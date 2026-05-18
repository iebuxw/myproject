<template>
  <div>
    <el-card>
      <h2>欢迎使用后台管理系统</h2>
      <p style="margin-top:10px;color:#666">管理员: {{ admin ? admin.username : '' }}</p>
    </el-card>
  </div>
</template>

<script>
import { mapState } from 'vuex'
export default {
  name: 'Dashboard',
  computed: { ...mapState(['admin']) }
}
</script>
