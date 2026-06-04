<template>
  <el-card>
    <div slot="header"><span>日志设置</span></div>
    <el-form ref="form" :model="form" label-width="140px" style="max-width:600px">
      <el-form-item label="日志保留天数">
        <el-input-number v-model="form.log_retention_days" :min="1" :max="3650" />
        <span style="margin-left:8px;color:#909399;font-size:12px;white-space:nowrap">每天凌晨 3 点清理，保留最近 N 天</span>
      </el-form-item>
      <el-form-item label="清理操作日志">
        <el-switch v-model="form.clean_operation_log" active-value="1" inactive-value="0" />
      </el-form-item>
      <el-form-item label="清理登录日志">
        <el-switch v-model="form.clean_login_log" active-value="1" inactive-value="0" />
      </el-form-item>
      <el-form-item>
        <el-button v-auth="'log_config:update'" type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </el-form-item>
    </el-form>
  </el-card>
</template>

<script>
import request from '@/api'

export default {
  name: 'LogSettings',
  data() {
    return {
      saving: false,
      form: {
        log_retention_days: 360,
        clean_operation_log: '1',
        clean_login_log: '1'
      }
    }
  },
  created() {
    this.fetchConfig()
  },
  methods: {
    async fetchConfig() {
      try {
        const res = await request.get('/log_config/read')
        if (res.code === 0) {
          this.form.log_retention_days = parseInt(res.data.log_retention_days) || 360
          this.form.clean_operation_log = res.data.clean_operation_log || '1'
          this.form.clean_login_log = res.data.clean_login_log || '1'
        }
      } catch (e) {
        this.$message.error('获取配置失败')
      }
    },
    async handleSave() {
      this.saving = true
      try {
        const res = await request.put('/log_config/update', this.form)
        if (res.code === 0) {
          this.$message.success('保存成功')
        } else {
          this.$message.error(res.msg || '保存失败')
        }
      } catch (e) {
        this.$message.error('保存失败')
      } finally {
        this.saving = false
      }
    }
  }
}
</script>
