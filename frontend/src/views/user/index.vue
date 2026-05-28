<template>
  <div>
    <el-card>
      <div slot="header">
        <span>用户管理</span>
        <el-button type="primary" size="small" style="float:right" @click="handleAdd">新增</el-button>
      </div>

      <el-form :model="searchForm" inline size="small" style="margin-bottom:15px">
        <el-form-item label="手机号">
          <el-input v-model="searchForm.phone" placeholder="请输入手机号" clearable @keyup.enter.native="handleSearch" />
        </el-form-item>
        <el-form-item label="昵称">
          <el-input v-model="searchForm.nickname" placeholder="请输入昵称" clearable @keyup.enter.native="handleSearch" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="list" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="phone" label="手机号" width="130" />
        <el-table-column prop="nickname" label="昵称" />
        <el-table-column prop="email" label="邮箱" />
        <el-table-column prop="gender" label="性别" width="70">
          <template slot-scope="{row}">
            {{ ['未知','男','女'][row.gender] || '未知' }}
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="80">
          <template slot-scope="{row}">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" width="150">
          <template slot-scope="{row}">
            <el-button type="text" @click="handleEdit(row)">编辑</el-button>
            <el-button type="text" style="color:#f56c6c" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog :title="dialogTitle" :visible.sync="dialogVisible" width="500px" :close-on-click-modal="false">
      <el-form ref="form" :model="form" label-width="80px">
        <el-form-item label="手机号" required>
          <el-input v-model="form.phone" :disabled="!!form.id" />
        </el-form-item>
        <el-form-item label="密码" :required="!form.id">
          <el-input v-model="form.password" type="password" :placeholder="form.id ? '留空不修改' : ''" />
        </el-form-item>
        <el-form-item label="昵称">
          <el-input v-model="form.nickname" />
        </el-form-item>
        <el-form-item label="邮箱">
          <el-input v-model="form.email" />
        </el-form-item>
        <el-form-item label="性别">
          <el-select v-model="form.gender" style="width:100%">
            <el-option :value="0" label="未知" />
            <el-option :value="1" label="男" />
            <el-option :value="2" label="女" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="form.id" label="状态">
          <el-select v-model="form.status" style="width:100%">
            <el-option :value="1" label="启用" />
            <el-option :value="0" label="禁用" />
          </el-select>
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
  name: 'UserList',
  data() {
    return {
      list: [],
      searchForm: { phone: '', nickname: '' },
      dialogVisible: false,
      dialogTitle: '',
      form: { id: 0, phone: '', password: '', nickname: '', email: '', gender: 0, status: 1 }
    }
  },
  created() {
    this.fetchList()
  },
  methods: {
    async fetchList(params) {
      try {
        const res = await request.get('/user/list', { params })
        if (res.code === 0) this.list = res.data.list
      } catch (e) {
        // 1001 已由拦截器处理
      }
    },
    handleSearch() {
      const params = {}
      if (this.searchForm.phone) params.phone = this.searchForm.phone
      if (this.searchForm.nickname) params.nickname = this.searchForm.nickname
      this.fetchList(params)
    },
    handleReset() {
      this.searchForm = { phone: '', nickname: '' }
      this.fetchList()
    },
    handleAdd() {
      this.dialogTitle = '新增用户'
      this.form = { id: 0, phone: '', password: '', nickname: '', email: '', gender: 0, status: 1 }
      this.dialogVisible = true
    },
    handleEdit(row) {
      this.dialogTitle = '编辑用户'
      this.form = {
        id: row.id,
        phone: row.phone,
        password: '',
        nickname: row.nickname || '',
        email: row.email || '',
        gender: row.gender,
        status: row.status
      }
      this.dialogVisible = true
    },
    handleDelete(row) {
      this.$confirm('确定删除该用户吗?', '提示', { type: 'warning' }).then(async () => {
        try {
          const res = await request.delete('/user/delete', { data: { id: row.id } })
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
      const { id, phone, password, nickname, email, gender, status } = this.form
      if (!phone) return this.$message.warning('请输入手机号')
      if (!id && !password) return this.$message.warning('请输入密码')

      const api = id ? request.put : request.post
      const url = id ? '/user/edit' : '/user/add'
      const data = id
        ? { id, password, nickname, email, gender, status }
        : { phone, password, nickname, email, gender }

      try {
        const res = await api(url, data)
        if (res.code === 0) {
          this.$message.success(id ? '编辑成功' : '新增成功')
          this.dialogVisible = false
          this.fetchList()
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {
        // 1001 已由拦截器处理，dialog 会在路由跳转后自然消失
      }
    }
  }
}
</script>
