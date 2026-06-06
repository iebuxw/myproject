<template>
  <div>
    <el-card>
      <div slot="header">
        <span>通知公告</span>
        <el-button v-auth="'notice:add'" type="primary" size="small" style="float:right" @click="handleAdd">新增</el-button>
      </div>

      <el-form :model="searchForm" inline size="small" style="margin-bottom:15px">
        <el-form-item label="标题">
          <el-input v-model="searchForm.title" placeholder="请输入标题" clearable @keyup.enter.native="handleSearch" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width:120px">
            <el-option :value="1" label="已发布" />
            <el-option :value="0" label="草稿" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="list" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="title" label="标题" show-overflow-tooltip />
        <el-table-column prop="admin_name" label="发布人" width="120" />
        <el-table-column prop="status" label="状态" width="80">
          <template slot-scope="{row}">
            <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">{{ row.status === 1 ? '已发布' : '草稿' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="is_popup" label="弹窗" width="80">
          <template slot-scope="{row}">
            <el-tag :type="row.is_popup === 1 ? 'warning' : 'info'" size="small">{{ row.is_popup === 1 ? '是' : '否' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" width="150">
          <template slot-scope="{row}">
            <el-button v-auth="'notice:edit'" type="text" @click="handleEdit(row)">编辑</el-button>
            <el-button v-auth="'notice:delete'" type="text" style="color:#f56c6c" @click="handleDelete(row)">删除</el-button>
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

    <el-dialog :title="dialogTitle" :visible.sync="dialogVisible" width="600px">
      <el-form ref="form" :model="form" label-width="80px">
        <el-form-item label="标题" required>
          <el-input v-model="form.title" placeholder="请输入标题" />
        </el-form-item>
        <el-form-item label="内容" required>
          <el-input v-model="form.content" type="textarea" :rows="8" placeholder="请输入内容" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="form.status" style="width:100%">
            <el-option :value="1" label="已发布" />
            <el-option :value="0" label="草稿" />
          </el-select>
        </el-form-item>
        <el-form-item label="登录弹窗">
          <el-switch v-model="form.is_popup" :active-value="1" :inactive-value="0" active-text="是" inactive-text="否" />
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
  name: 'SystemNotice',
  data() {
    return {
      list: [],
      page: 1,
      limit: 10,
      total: 0,
      searchForm: { title: '', status: undefined },
      dialogVisible: false,
      dialogTitle: '',
      form: { id: 0, title: '', content: '', status: 1, is_popup: 0 }
    }
  },
  created() {
    this.fetchList()
  },
  methods: {
    async fetchList() {
      try {
        const params = { page: this.page, limit: this.limit }
        if (this.searchForm.title) params.title = this.searchForm.title
        if (this.searchForm.status !== undefined && this.searchForm.status !== '') params.status = this.searchForm.status
        const res = await request.get('/notice/list', { params })
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
      this.searchForm = { title: '', status: undefined }
      this.page = 1
      this.fetchList()
    },
    handlePageChange(page) {
      this.page = page
      this.fetchList()
    },
    handleAdd() {
      this.dialogTitle = '新增公告'
      this.form = { id: 0, title: '', content: '', status: 1, is_popup: 0 }
      this.dialogVisible = true
    },
    handleEdit(row) {
      this.dialogTitle = '编辑公告'
      this.form = { id: row.id, title: row.title, content: row.content, status: row.status, is_popup: row.is_popup }
      this.dialogVisible = true
    },
    handleDelete(row) {
      this.$confirm('确定删除该公告吗?', '提示', { type: 'warning' }).then(async () => {
        try {
          const res = await request.delete('/notice/delete', { data: { id: row.id } })
          if (res.code === 0) {
            this.$message.success('删除成功')
            this.fetchList()
          } else {
            this.$message.error(res.msg)
          }
        } catch (e) {
          // 1001 已由拦截器处理
        }
      }).catch(() => {})
    },
    async handleSubmit() {
      const { id, title, content, status, is_popup } = this.form
      if (!title) return this.$message.warning('请输入标题')
      if (!content) return this.$message.warning('请输入内容')

      const api = id ? request.put : request.post
      const url = id ? '/notice/edit' : '/notice/add'
      const data = id ? { id, title, content, status, is_popup } : { title, content, status, is_popup }

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
