<template>
  <div>
    <el-card>
      <div slot="header">
        <span>执行日志</span>
      </div>

      <el-form :model="searchForm" inline size="small" style="margin-bottom:15px">
        <el-form-item label="任务ID">
          <el-input v-model="searchForm.task_id" placeholder="任务ID" clearable style="width:100px" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width:120px">
            <el-option :value="1" label="成功" />
            <el-option :value="0" label="失败" />
          </el-select>
        </el-form-item>
        <el-form-item label="日期范围">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"
            style="width:260px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="list" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="task_id" label="任务ID" width="70" />
        <el-table-column prop="command" label="命令" width="140" />
        <el-table-column prop="status" label="状态" width="80">
          <template slot-scope="{row}">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
              {{ row.status === 1 ? '成功' : '失败' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="duration" label="耗时(秒)" width="90" />
        <el-table-column prop="started_at" label="执行时间" width="170" />
        <el-table-column label="操作" width="120">
          <template slot-scope="{row}">
            <el-button type="text" @click="handleViewOutput(row)">查看输出</el-button>
            <el-button v-auth="'cron_task_log:delete'" type="text" style="color:#f56c6c" @click="handleDelete(row)">删除</el-button>
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

    <el-dialog title="执行输出" :visible.sync="outputVisible" width="650px">
      <pre style="max-height:400px;overflow:auto;background:#f5f5f5;padding:12px;border-radius:4px;font-size:13px;white-space:pre-wrap;word-break:break-all">{{ outputContent || '(无输出)' }}</pre>
    </el-dialog>
  </div>
</template>

<script>
import request from '@/api'

export default {
  name: 'SystemCronTaskLog',
  data() {
    return {
      list: [],
      page: 1,
      limit: 10,
      total: 0,
      searchForm: { task_id: '', status: undefined },
      dateRange: null,
      outputVisible: false,
      outputContent: ''
    }
  },
  created() {
    this.fetchList()
  },
  methods: {
    async fetchList() {
      try {
        const params = { page: this.page, limit: this.limit }
        if (this.searchForm.task_id) params.task_id = this.searchForm.task_id
        if (this.searchForm.status !== undefined && this.searchForm.status !== '') params.status = this.searchForm.status
        if (this.dateRange && this.dateRange.length === 2) {
          params.start_date = this.dateRange[0]
          params.end_date = this.dateRange[1]
        }
        const res = await request.get('/cron_task_log/list', { params })
        if (res.code === 0) {
          this.list = res.data.list
          this.total = res.data.total
        }
      } catch (e) {}
    },
    handleSearch() {
      this.page = 1
      this.fetchList()
    },
    handleReset() {
      this.searchForm = { task_id: '', status: undefined }
      this.dateRange = null
      this.page = 1
      this.fetchList()
    },
    handlePageChange(page) {
      this.page = page
      this.fetchList()
    },
    handleViewOutput(row) {
      this.outputContent = row.output
      this.outputVisible = true
    },
    handleDelete(row) {
      this.$confirm('确定删除该日志吗？', '提示', { type: 'warning' }).then(async () => {
        try {
          const res = await request.delete('/cron_task_log/delete', { data: { id: row.id } })
          if (res.code === 0) {
            this.$message.success('删除成功')
            this.fetchList()
          } else {
            this.$message.error(res.msg)
          }
        } catch (e) {}
      }).catch(() => {})
    }
  }
}
</script>
