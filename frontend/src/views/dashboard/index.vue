<template>
  <div>
    <el-card>
      <h2>欢迎使用后台管理系统</h2>
      <p style="margin-top:10px;color:#666">管理员: {{ admin ? admin.username : '' }}</p>
    </el-card>
    <el-row :gutter="20" style="margin-top:20px">
      <el-col :span="8">
        <el-card shadow="hover">
          <div slot="header">CPU 使用率</div>
          <div class="monitor-value">{{ serverInfo.cpu ? serverInfo.cpu.usage + '%' : '--' }}</div>
          <el-progress
            :percentage="serverInfo.cpu ? serverInfo.cpu.usage : 0"
            :color="progressColor(serverInfo.cpu ? serverInfo.cpu.usage : 0)"
            :stroke-width="12"
          />
          <div class="monitor-sub" v-if="serverInfo.cpu">
            {{ serverInfo.cpu.cores }} 核 / 负载 {{ serverInfo.cpu.load_avg || '--' }}
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <div slot="header">内存使用率</div>
          <div class="monitor-value">
            {{ serverInfo.memory ? formatMemory(serverInfo.memory.used) + ' / ' + formatMemory(serverInfo.memory.total) : '--' }}
          </div>
          <el-progress
            :percentage="serverInfo.memory ? serverInfo.memory.usage : 0"
            :color="progressColor(serverInfo.memory ? serverInfo.memory.usage : 0)"
            :stroke-width="12"
          />
          <div class="monitor-sub" v-if="serverInfo.memory">
            已用 {{ formatMemory(serverInfo.memory.used) }} / 总量 {{ formatMemory(serverInfo.memory.total) }}
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <div slot="header">磁盘使用率</div>
          <div class="monitor-value">
            {{ serverInfo.disk ? serverInfo.disk.used + ' / ' + serverInfo.disk.total + ' GB' : '--' }}
          </div>
          <el-progress
            :percentage="serverInfo.disk ? serverInfo.disk.usage : 0"
            :color="progressColor(serverInfo.disk ? serverInfo.disk.usage : 0)"
            :stroke-width="12"
          />
          <div class="monitor-sub" v-if="serverInfo.disk">
            已用 {{ serverInfo.disk.used }} GB / 总量 {{ serverInfo.disk.total }} GB
          </div>
        </el-card>
      </el-col>
    </el-row>
    <el-card v-if="serverError" style="margin-top:20px">
      <p style="color:#909399;text-align:center">{{ serverError }}</p>
    </el-card>
  </div>
</template>

<script>
import { mapState } from 'vuex'
import request from '@/api'

export default {
  name: 'Dashboard',
  data() {
    return {
      serverInfo: {},
      serverError: ''
    }
  },
  computed: { ...mapState(['admin']) },
  created() {
    this.fetchServerInfo()
  },
  methods: {
    async fetchServerInfo() {
      try {
        const res = await request.get('/server/info')
        if (res.code === 0) {
          this.serverInfo = res.data
        } else {
          this.serverError = '暂无数据'
        }
      } catch (e) {
        this.serverError = '暂无数据'
      }
    },
    formatMemory(mb) {
      if (mb === null || mb === undefined) return '--'
      return mb >= 1024 ? (mb / 1024).toFixed(1) + ' GB' : mb + ' MB'
    },
    progressColor(percentage) {
      if (percentage >= 80) return '#F56C6C'
      if (percentage >= 60) return '#E6A23C'
      return '#67C23A'
    }
  }
}
</script>

<style scoped>
.monitor-value {
  font-size: 24px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 12px;
}
.monitor-sub {
  margin-top: 8px;
  font-size: 13px;
  color: #909399;
}
</style>
