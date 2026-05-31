<template>
  <div>
    <el-card>
      <div slot="header">
        <span>操作日志</span>
      </div>

      <el-form :model="searchForm" inline size="small" style="margin-bottom:15px">
        <el-form-item label="模块">
          <el-select v-model="searchForm.module" placeholder="全部" clearable style="width:140px">
            <el-option label="管理员管理" value="管理员管理" />
            <el-option label="角色管理" value="角色管理" />
            <el-option label="菜单管理" value="菜单管理" />
            <el-option label="用户管理" value="用户管理" />
            <el-option label="登录日志" value="登录日志" />
            <el-option label="操作日志" value="操作日志" />
          </el-select>
        </el-form-item>
        <el-form-item label="操作人">
          <el-input v-model="searchForm.username" placeholder="请输入用户名" clearable @keyup.enter.native="handleSearch" />
        </el-form-item>
        <el-form-item label="操作时间">
          <el-date-picker
            v-model="searchForm.dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="list" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="username" label="操作人" width="120" />
        <el-table-column prop="module" label="模块" width="120" />
        <el-table-column prop="action" label="动作" width="80">
          <template slot-scope="{row}">
            <el-tag :type="actionTagType(row.action)" size="small">{{ row.action }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="method" label="方法" width="80" />
        <el-table-column prop="url" label="URL" show-overflow-tooltip />
        <el-table-column prop="ip" label="IP" width="140" />
        <el-table-column prop="created_at" label="操作时间" width="180" />
        <el-table-column label="操作" width="80">
          <template slot-scope="{row}">
            <el-button type="text" style="color:#409eff" @click="handleDetail(row)">详情</el-button>
            <!-- <el-button type="text" style="color:#f56c6c" @click="handleDelete(row)">删除</el-button> -->
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

    <el-dialog title="请求参数" :visible.sync="detailVisible" width="600px">
      <pre style="max-height:400px;overflow:auto;background:#f5f5f5;padding:12px;border-radius:4px">{{ detailParams }}</pre>
    </el-dialog>
  </div>
</template>

<script>
import request from '@/api'

export default {
  name: 'OperationLog',
  data() {
    return {
      list: [],
      page: 1,
      limit: 10,
      total: 0,
      searchForm: { module: '', username: '', dateRange: null },
      detailVisible: false,
      detailParams: ''
    }
  },
  created() {
    this.fetchList()
  },
  methods: {
    async fetchList() {
      try {
        const params = { page: this.page, limit: this.limit }
        if (this.searchForm.module) params.module = this.searchForm.module
        if (this.searchForm.username) params.username = this.searchForm.username
        if (this.searchForm.dateRange && this.searchForm.dateRange.length === 2) {
          params.start_at = this.searchForm.dateRange[0]
          params.end_at = this.searchForm.dateRange[1]
        }
        const res = await request.get('/operation_log/list', { params })
        if (res.code === 0) {
          this.list = res.data.list
          this.total = res.data.total
        }
      } catch (e) {
        // 1001 已由拦截器处理
      }
    },
    actionTagType(action) {
      const map = { '新增': 'success', '编辑': 'warning', '删除': 'danger' }
      return map[action] || 'info'
    },
    handleSearch() {
      this.page = 1
      this.fetchList()
    },
    handleReset() {
      this.searchForm = { module: '', username: '', dateRange: null }
      this.page = 1
      this.fetchList()
    },
    handlePageChange(page) {
      this.page = page
      this.fetchList()
    },
    handleDetail(row) {
      try {
        this.detailParams = JSON.stringify(JSON.parse(row.params), null, 2)
      } catch (e) {
        this.detailParams = row.params || ''
      }
      this.detailVisible = true
    },
    // handleDelete(row) {
    //   this.$confirm('确定删除该日志吗?', '提示', { type: 'warning' }).then(async () => {
    //     try {
    //       const res = await request.delete('/operation_log/delete', { data: { id: row.id } })
    //       if (res.code === 0) {
    //         this.$message.success('删除成功')
    //         this.fetchList()
    //       } else {
    //         this.$message.error(res.msg)
    //       }
    //     } catch (e) {
    //       // 1001 已由拦截器处理
    //     }
    //   }).catch(() => {})
    // }
  }
}
</script>
