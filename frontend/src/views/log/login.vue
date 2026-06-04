<template>
  <div>
    <el-card>
      <div slot="header">
        <span>登录日志</span>
      </div>

      <el-form :model="searchForm" inline size="small" style="margin-bottom:15px">
        <el-form-item label="用户名">
          <el-input v-model="searchForm.username" placeholder="请输入用户名" clearable @keyup.enter.native="handleSearch" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width:120px">
            <el-option :value="1" label="成功" />
            <el-option :value="0" label="失败" />
          </el-select>
        </el-form-item>
        <el-form-item label="登录时间">
          <el-date-picker
            v-model="searchForm.dateRange"
            type="daterange"
            range-separator="-"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"
            style="width:350px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="list" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="username" label="用户名" width="120" />
        <el-table-column prop="ip" label="登录IP" width="140" />
        <el-table-column prop="user_agent" label="浏览器" show-overflow-tooltip />
        <el-table-column prop="status" label="状态" width="80">
          <template slot-scope="{row}">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">{{ row.status === 1 ? '成功' : '失败' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="message" label="消息" width="150" />
        <el-table-column prop="created_at" label="登录时间" width="180" />
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
  </div>
</template>

<script>
import request from '@/api'

export default {
  name: 'LoginLog',
  data() {
    return {
      list: [],
      page: 1,
      limit: 10,
      total: 0,
      searchForm: { username: '', status: undefined, dateRange: null }
    }
  },
  created() {
    this.fetchList()
  },
  methods: {
    async fetchList() {
      try {
        const params = { page: this.page, limit: this.limit }
        if (this.searchForm.username) params.username = this.searchForm.username
        if (this.searchForm.status !== undefined && this.searchForm.status !== '') params.status = this.searchForm.status
        if (this.searchForm.dateRange && this.searchForm.dateRange.length === 2) {
          params.start_at = this.searchForm.dateRange[0]
          params.end_at = this.searchForm.dateRange[1]
        }
        const res = await request.get('/login_log/list', { params })
        if (res.code === 0) {
          this.list = res.data.list
          this.total = res.data.total
        }
      } catch (e) {
        // 1001 已由拦截器处理
      }
    },
    handleSearch() {
      this.page = 1
      this.fetchList()
    },
    handleReset() {
      this.searchForm = { username: '', status: undefined, dateRange: null }
      this.page = 1
      this.fetchList()
    },
    handlePageChange(page) {
      this.page = page
      this.fetchList()
    },
    // handleDelete(row) {
    //   this.$confirm('确定删除该日志吗?', '提示', { type: 'warning' }).then(async () => {
    //     try {
    //       const res = await request.delete('/login_log/delete', { data: { id: row.id } })
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
