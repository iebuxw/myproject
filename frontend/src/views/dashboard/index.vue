<template>
  <div>
    <el-card>
      <h2>欢迎使用{{ siteConfig.site_name || '后台管理系统' }}</h2>
      <p style="margin-top:10px;color:#666">管理员: {{ admin ? admin.username : '' }}</p>
    </el-card>
    <el-card style="margin-top:20px">
      <div slot="header">
        <span>通知公告</span>
        <router-link v-if="hasPerm('notice:list')" to="/system/notice" style="float:right;font-size:13px">管理公告</router-link>
      </div>
      <el-table v-if="notices.length" :data="notices" size="small" :show-header="false" style="width:100%">
        <el-table-column width="80">
          <template slot-scope="{row}">
            <el-tag size="mini" type="warning">公告</el-tag>
          </template>
        </el-table-column>
        <el-table-column>
          <template slot-scope="{row}">
            <span style="cursor:pointer;color:#409EFF" @click="showDetail(row)">{{ row.title }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="admin_name" width="120" />
        <el-table-column prop="created_at" width="180" />
      </el-table>
      <p v-else style="color:#909399;text-align:center;padding:10px 0">暂无公告</p>
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

    <el-dialog :title="currentNotice.title" :visible.sync="detailVisible" width="600px">
      <p style="color:#909399;font-size:13px;margin-bottom:12px">{{ currentNotice.admin_name }} · {{ currentNotice.created_at }}</p>
      <div style="line-height:1.8;white-space:pre-wrap">{{ currentNotice.content }}</div>
    </el-dialog>

    <el-dialog title="系统公告" :visible.sync="popupVisible" width="600px" :close-on-click-modal="false">
      <div v-for="(item, idx) in popupNotices" :key="item.id" style="margin-bottom:20px">
        <h3 style="margin:0 0 8px">{{ item.title }}</h3>
        <p style="color:#909399;font-size:13px;margin:0 0 8px">{{ item.admin_name }} · {{ item.created_at }}</p>
        <div style="line-height:1.8;white-space:pre-wrap">{{ item.content }}</div>
        <el-divider v-if="idx < popupNotices.length - 1" />
      </div>
      <span slot="footer">
        <el-button type="primary" @click="handlePopupSeen">我知道了</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import request from '@/api'

export default {
  name: 'Dashboard',
  data() {
    return {
      notices: [],
      serverInfo: {},
      serverError: '',
      detailVisible: false,
      currentNotice: {},
      popupVisible: false,
      popupNotices: []
    }
  },
  computed: { ...mapState(['admin', 'siteConfig']), ...mapGetters(['hasPerm']) },
  created() {
    this.fetchNotices()
    this.fetchPopupNotices()
    this.fetchServerInfo()
  },
  methods: {
    async fetchNotices() {
      try {
        const res = await request.get('/notice/published')
        if (res.code === 0) this.notices = res.data
      } catch (e) {}
    },
    showDetail(row) {
      this.currentNotice = row
      this.detailVisible = true
    },
    async fetchPopupNotices() {
      try {
        const res = await request.get('/notice/popup')
        if (res.code === 0 && res.data.length) {
          this.popupNotices = res.data
          this.popupVisible = true
        }
      } catch (e) {}
    },
    async handlePopupSeen() {
      try {
        const maxId = this.popupNotices.reduce((max, n) => Math.max(max, n.id), 0)
        await request.post('/notice/seen', { max_id: maxId })
      } catch (e) {}
      this.popupVisible = false
    },
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
