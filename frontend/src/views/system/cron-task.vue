<template>
  <div>
    <el-card>
      <div slot="header">
        <span>定时任务</span>
        <el-button v-auth="'cron_task:add'" type="primary" size="small" style="float:right" @click="handleAdd">新增</el-button>
      </div>

      <el-form :model="searchForm" inline size="small" style="margin-bottom:15px">
        <el-form-item label="名称">
          <el-input v-model="searchForm.name" placeholder="任务名称" clearable @keyup.enter.native="handleSearch" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width:120px">
            <el-option :value="1" label="启用" />
            <el-option :value="0" label="停用" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="list" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="name" label="任务名称" show-overflow-tooltip />
        <el-table-column prop="command" label="命令" width="140" />
        <el-table-column prop="cron_expr" label="Cron 表达式" width="140" />
        <el-table-column prop="status" label="状态" width="80">
          <template slot-scope="{row}">
            <el-switch
              v-auth="'cron_task:edit'"
              :value="row.status === 1"
              active-color="#13ce66"
              inactive-color="#ff4949"
              @change="handleToggle(row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="last_run_at" label="上次执行" width="170">
          <template slot-scope="{row}">{{ row.last_run_at || '-' }}</template>
        </el-table-column>
        <el-table-column prop="last_status" label="上次结果" width="90">
          <template slot-scope="{row}">
            <el-tag v-if="row.last_run_at" :type="row.last_status === 1 ? 'success' : 'danger'" size="small">
              {{ row.last_status === 1 ? '成功' : '失败' }}
            </el-tag>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" show-overflow-tooltip />
        <el-table-column label="操作" width="200">
          <template slot-scope="{row}">
            <el-button v-auth="'cron_task:edit'" type="text" @click="handleEdit(row)">编辑</el-button>
            <el-button type="text" @click="handleRun(row)">执行</el-button>
            <el-button v-auth="'cron_task:delete'" type="text" style="color:#f56c6c" @click="handleDelete(row)">删除</el-button>
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

    <el-dialog :title="dialogTitle" :visible.sync="dialogVisible" width="550px">
      <el-form ref="form" :model="form" label-width="110px">
        <el-form-item label="任务名称" required>
          <el-input v-model="form.name" placeholder="请输入任务名称" />
        </el-form-item>
        <el-form-item label="命令" required>
          <el-select v-model="form.command" placeholder="请选择命令" style="width:100%" :disabled="!!form.id">
            <el-option v-for="c in commands" :key="c.name" :label="c.name + (c.description ? ' - ' + c.description : '')" :value="c.name" />
          </el-select>
        </el-form-item>
        <el-form-item label="Cron 表达式" required>
          <el-input v-model="form.cron_expr" placeholder="如 0 3 * * *" />
          <div style="margin-top:8px">
            <el-button size="mini" @click="form.cron_expr = '* * * * *'">每分钟</el-button>
            <el-button size="mini" @click="form.cron_expr = '0 * * * *'">每小时</el-button>
            <el-button size="mini" @click="form.cron_expr = '0 0 * * *'">每天</el-button>
            <el-button size="mini" @click="form.cron_expr = '0 0 * * 1'">每周</el-button>
            <el-button size="mini" @click="form.cron_expr = '0 0 1 * *'">每月</el-button>
          </div>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
      </el-form>
      <span slot="footer">
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit">确定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import request from '@/api'

export default {
  name: 'SystemCronTask',
  data() {
    return {
      list: [],
      page: 1,
      limit: 10,
      total: 0,
      searchForm: { name: '', status: undefined },
      dialogVisible: false,
      dialogTitle: '',
      form: { id: 0, name: '', command: '', cron_expr: '', remark: '' },
      commands: []
    }
  },
  created() {
    this.fetchList()
    this.fetchCommands()
  },
  methods: {
    async fetchList() {
      try {
        const params = { page: this.page, limit: this.limit }
        if (this.searchForm.name) params.name = this.searchForm.name
        if (this.searchForm.status !== undefined && this.searchForm.status !== '') params.status = this.searchForm.status
        const res = await request.get('/cron_task/list', { params })
        if (res.code === 0) {
          this.list = res.data.list
          this.total = res.data.total
        }
      } catch (e) {}
    },
    async fetchCommands() {
      try {
        const res = await request.get('/cron_task/commands')
        if (res.code === 0) {
          this.commands = res.data
        }
      } catch (e) {}
    },
    handleSearch() {
      this.page = 1
      this.fetchList()
    },
    handleReset() {
      this.searchForm = { name: '', status: undefined }
      this.page = 1
      this.fetchList()
    },
    handlePageChange(page) {
      this.page = page
      this.fetchList()
    },
    handleAdd() {
      this.dialogTitle = '新增任务'
      this.form = { id: 0, name: '', command: '', cron_expr: '', remark: '' }
      this.dialogVisible = true
    },
    handleEdit(row) {
      this.dialogTitle = '编辑任务'
      this.form = { id: row.id, name: row.name, command: row.command, cron_expr: row.cron_expr, remark: row.remark || '' }
      this.dialogVisible = true
    },
    async handleToggle(row) {
      try {
        const res = await request.put('/cron_task/toggle', { id: row.id })
        if (res.code === 0) {
          this.$message.success(res.data.status === 1 ? '已启用' : '已停用')
          this.fetchList()
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {}
    },
    async handleRun(row) {
      try {
        const res = await request.post('/cron_task/run', { id: row.id })
        if (res.code === 0) {
          this.$message.success('执行完成：' + (res.data.status === 1 ? '成功' : '失败') + '，耗时 ' + res.data.duration + 's')
          this.fetchList()
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {}
    },
    handleDelete(row) {
      this.$confirm('删除任务会同时删除其执行日志，确定删除吗？', '提示', { type: 'warning' }).then(async () => {
        try {
          const res = await request.delete('/cron_task/delete', { data: { id: row.id } })
          if (res.code === 0) {
            this.$message.success('删除成功')
            this.fetchList()
          } else {
            this.$message.error(res.msg)
          }
        } catch (e) {}
      }).catch(() => {})
    },
    async handleSubmit() {
      const { id, name, command, cron_expr, remark } = this.form
      if (!name) return this.$message.warning('请输入任务名称')
      if (!command) return this.$message.warning('请选择命令')
      if (!cron_expr) return this.$message.warning('请输入 Cron 表达式')

      const api = id ? request.put : request.post
      const url = id ? '/cron_task/edit' : '/cron_task/add'
      const data = id ? { id, name, cron_expr, remark } : { name, command, cron_expr, remark }

      const res = await api(url, data)
      if (res.code === 0) {
        this.$message.success(id ? '编辑成功' : '新增成功')
        this.dialogVisible = false
        this.fetchList()
      } else {
        this.$message.error(res.msg)
      }
    }
  }
}
</script>
