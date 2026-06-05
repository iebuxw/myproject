<template>
  <div>
    <el-card>
      <div slot="header">
        <span>数据库备份</span>
        <div style="float:right">
          <el-button size="small" @click="showConfigDialog">备份设置</el-button>
          <el-button v-auth="'db_backup:add'" type="primary" size="small" @click="handleAdd">新增备份</el-button>
        </div>
      </div>

      <el-form :model="searchForm" inline size="small" style="margin-bottom:15px">
        <el-form-item label="日期范围">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"
            @change="handleSearch"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="list" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="filename" label="文件名" show-overflow-tooltip />
        <el-table-column prop="file_size" label="文件大小" width="110">
          <template slot-scope="{row}">{{ formatSize(row.file_size) }}</template>
        </el-table-column>
        <el-table-column prop="trigger_type" label="触发方式" width="100">
          <template slot-scope="{row}">
            <el-tag :type="row.trigger_type === 1 ? '' : 'info'" size="small">
              {{ row.trigger_type === 1 ? '手动' : '定时' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="90">
          <template slot-scope="{row}">
            <el-tag v-if="row.status === 1" type="success" size="small">成功</el-tag>
            <el-tag v-else-if="row.status === 0 && row.remark === '备份中...'" type="warning" size="small">进行中</el-tag>
            <el-tag v-else type="danger" size="small">失败</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="is_snapshot" label="类型" width="110">
          <template slot-scope="{row}">
            <el-tag :type="row.is_snapshot === 1 ? 'warning' : ''" size="small">
              {{ row.is_snapshot === 1 ? '恢复快照' : '常规备份' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" show-overflow-tooltip>
          <template slot-scope="{row}">{{ row.remark || '-' }}</template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="170" />
        <el-table-column label="操作" width="200">
          <template slot-scope="{row}">
            <el-button v-if="row.status === 1" v-auth="'db_backup:restore'" type="text" style="color:#e6a23c" @click="handleRestore(row)">恢复</el-button>
            <el-button v-if="row.status === 1" type="text" @click="handleDownload(row)">下载</el-button>
            <el-button v-auth="'db_backup:delete'" type="text" style="color:#f56c6c" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        style="margin-top:15px;text-align:right"
        background
        layout="total, prev, pager, next"
        :current-page="page"
        :page-size="limit"
        :total="total"
        @current-change="handlePageChange"
      />
    </el-card>

    <!-- 恢复确认弹窗 -->
    <el-dialog title="确认恢复" :visible.sync="restoreVisible" width="450px">
      <p style="color:#e6a23c;margin-bottom:15px">
        恢复操作将用备份文件覆盖当前数据库，系统将进入维护模式，所有用户无法访问。恢复前会自动创建快照。
      </p>
      <p>请输入 <b>确认恢复</b> 以继续：</p>
      <el-input v-model="confirmText" placeholder="请输入" />
      <span slot="footer">
        <el-button @click="restoreVisible = false">取消</el-button>
        <el-button type="danger" :disabled="confirmText !== '确认恢复'" :loading="restoreLoading" @click="doRestore">确认恢复</el-button>
      </span>
    </el-dialog>

    <!-- 保留天数配置弹窗 -->
    <el-dialog title="备份保留配置" :visible.sync="configVisible" width="400px">
      <el-form label-width="100px">
        <el-form-item label="保留天数">
          <el-input-number v-model="keepDays" :min="1" :max="365" style="width:150px" />
          <span style="margin-left:8px;color:#999;font-size:12px">天</span>
        </el-form-item>
      </el-form>
      <p style="color:#999;font-size:12px;padding-left:100px">超过保留天数的备份将在定时清理任务中自动删除</p>
      <span slot="footer">
        <el-button @click="configVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSaveConfig">保存</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import request from '@/api'

export default {
  name: 'SystemDbBackup',
  data() {
    return {
      list: [],
      page: 1,
      limit: 10,
      total: 0,
      dateRange: null,
      searchForm: { start_date: '', end_date: '' },
      restoreVisible: false,
      restoreId: 0,
      confirmText: '',
      restoreLoading: false,
      keepDays: 30,
      configVisible: false
    }
  },
  created() {
    this.fetchList()
    this.fetchConfig()
  },
  methods: {
    async fetchList() {
      try {
        const params = { page: this.page, limit: this.limit }
        if (this.searchForm.start_date) params.start_date = this.searchForm.start_date
        if (this.searchForm.end_date) params.end_date = this.searchForm.end_date
        const res = await request.get('/db_backup/list', { params })
        if (res.code === 0) {
          this.list = res.data.list
          this.total = res.data.total
        }
      } catch (e) {}
    },
    async fetchConfig() {
      try {
        const res = await request.get('/db_backup/config')
        if (res.code === 0 && res.data.keep_days) {
          this.keepDays = res.data.keep_days
        }
      } catch (e) {}
    },
    async handleSaveConfig() {
      try {
        const res = await request.put('/db_backup/config', { keep_days: this.keepDays })
        if (res.code === 0) {
          this.$message.success('保存成功')
          this.configVisible = false
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {}
    },
    showConfigDialog() {
      this.configVisible = true
    },
    handleSearch() {
      if (this.dateRange && this.dateRange.length === 2) {
        this.searchForm.start_date = this.dateRange[0]
        this.searchForm.end_date = this.dateRange[1]
      } else {
        this.searchForm.start_date = ''
        this.searchForm.end_date = ''
      }
      this.page = 1
      this.fetchList()
    },
    handleReset() {
      this.dateRange = null
      this.searchForm = { start_date: '', end_date: '' }
      this.page = 1
      this.fetchList()
    },
    handlePageChange(page) {
      this.page = page
      this.fetchList()
    },
    async handleAdd() {
      try {
        const res = await request.post('/db_backup/add')
        if (res.code === 0) {
          this.$message.success(res.msg)
          this.fetchList()
          this.pollBackupStatus()
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {}
    },
    pollBackupStatus() {
      let count = 0
      const timer = setInterval(async () => {
        count++
        if (count > 30) {
          clearInterval(timer)
          return
        }
        await this.fetchList()
        const running = this.list.some(r => r.status === 0 && r.remark === '备份中...')
        if (!running) {
          clearInterval(timer)
        }
      }, 3000)
    },
    handleRestore(row) {
      this.restoreId = row.id
      this.confirmText = ''
      this.restoreVisible = true
    },
    async doRestore() {
      this.restoreLoading = true
      try {
        const res = await request.post('/db_backup/restore', { id: this.restoreId })
        if (res.code === 0) {
          this.$message.success('恢复成功')
          this.restoreVisible = false
          this.fetchList()
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {
        this.$message.error('恢复失败')
      } finally {
        this.restoreLoading = false
      }
    },
    handleDownload(row) {
      window.open('/admin/db_backup/download?id=' + row.id, '_blank')
    },
    handleDelete(row) {
      this.$confirm('删除后不可恢复，确定删除吗？', '提示', { type: 'warning' }).then(async () => {
        try {
          const res = await request.delete('/db_backup/delete', { data: { id: row.id } })
          if (res.code === 0) {
            this.$message.success('删除成功')
            this.fetchList()
          } else {
            this.$message.error(res.msg)
          }
        } catch (e) {}
      }).catch(() => {})
    },
    formatSize(bytes) {
      if (!bytes) return '-'
      if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB'
      if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB'
      if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB'
      return bytes + ' B'
    }
  }
}
</script>
