<template>
  <div>
    <el-card>
      <div slot="header">
        <span>用户管理</span>
        <el-button v-auth="'user:add'" type="primary" size="small" style="float:right" @click="handleAdd">新增</el-button>
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
          <el-button v-auth="'user:export'" type="success" @click="handleExport">导出</el-button>
          <el-button v-auth="'user:import'" type="warning" @click="handleImportDialog">导入</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="list" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="phone" label="手机号" width="130" />
        <el-table-column prop="nickname" label="昵称" />
        <el-table-column prop="email" label="邮箱" />
        <el-table-column prop="gender" label="性别" width="70">
          <template slot-scope="{row}">
            {{ genderMap[row.gender] || '未知' }}
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
            <el-button v-auth="'user:edit'" type="text" @click="handleEdit(row)">编辑</el-button>
            <el-button v-auth="'user:delete'" type="text" style="color:#f56c6c" @click="handleDelete(row)">删除</el-button>
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

    <el-dialog title="导入用户" :visible.sync="importVisible" width="450px" :close-on-click-modal="false">
      <el-upload
        ref="upload"
        drag
        :auto-upload="false"
        :on-change="handleFileChange"
        :limit="1"
        accept=".xlsx,.xls"
        action=""
      >
        <i class="el-icon-upload" />
        <div class="el-upload__text">将 Excel 文件拖到此处，或<em>点击上传</em></div>
        <div class="el-upload__tip" slot="tip">支持 .xlsx / .xls 格式，<a href="/templates/user_import_template.xlsx" download style="color:#409eff">下载导入模板</a></div>
      </el-upload>
      <span slot="footer">
        <el-button @click="importVisible = false">取消</el-button>
        <el-button type="primary" :loading="importing" @click="handleImportSubmit">确定导入</el-button>
      </span>
    </el-dialog>

    <el-dialog :title="dialogTitle" :visible.sync="dialogVisible" width="500px" :close-on-click-modal="false">
      <el-form ref="form" :model="form" :rules="rules" label-width="80px">
        <el-form-item label="手机号" prop="phone">
          <el-input v-model="form.phone" :disabled="!!form.id" autocomplete="off" />
        </el-form-item>
        <el-form-item label="密码" :required="!form.id">
          <el-input v-model="form.password" type="password" :placeholder="form.id ? '留空不修改' : ''" autocomplete="new-password" />
        </el-form-item>
        <el-form-item label="昵称">
          <el-input v-model="form.nickname" />
        </el-form-item>
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="form.email" />
        </el-form-item>
        <el-form-item label="性别">
          <el-select v-model="form.gender" style="width:100%">
            <el-option v-for="item in genderOptions" :key="item.value" :value="Number(item.value)" :label="item.label" />
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
import axios from 'axios'
import { loadDicts, dictMap } from '@/utils/dict'

export default {
  name: 'UserList',
  data() {
    return {
      list: [],
      genderMap: {},
      genderOptions: [],
      page: 1,
      limit: 10,
      total: 0,
      searchForm: { phone: '', nickname: '' },
      dialogVisible: false,
      dialogTitle: '',
      importVisible: false,
      importFile: null,
      importing: false,
      form: { id: 0, phone: '', password: '', nickname: '', email: '', gender: 0, status: 1 },
      rules: {
        phone: [
          { required: true, message: '请输入手机号', trigger: 'blur' },
          { pattern: /^1[3-9]\d{9}$/, message: '手机号格式不正确', trigger: 'blur' }
        ],
        email: [
          { type: 'email', message: '邮箱格式不正确', trigger: 'blur' }
        ]
      }
    }
  },
  async created() {
    const dicts = await loadDicts('gender')
    this.genderMap = dictMap(dicts.gender)
    this.genderOptions = dicts.gender || []
    this.fetchList({ page: this.page, limit: this.limit })
  },
  methods: {
    async fetchList(params) {
      try {
        const res = await request.get('/user/list', { params })
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
      const params = { page: this.page, limit: this.limit }
      if (this.searchForm.phone) params.phone = this.searchForm.phone
      if (this.searchForm.nickname) params.nickname = this.searchForm.nickname
      this.fetchList(params)
    },
    handleReset() {
      this.searchForm = { phone: '', nickname: '' }
      this.page = 1
      this.fetchList({ page: 1, limit: this.limit })
    },
    handlePageChange(page) {
      this.page = page
      const params = { page, limit: this.limit }
      if (this.searchForm.phone) params.phone = this.searchForm.phone
      if (this.searchForm.nickname) params.nickname = this.searchForm.nickname
      this.fetchList(params)
    },
    async handleExport() {
      const params = new URLSearchParams()
      if (this.searchForm.phone) params.append('phone', this.searchForm.phone)
      if (this.searchForm.nickname) params.append('nickname', this.searchForm.nickname)
      try {
        const res = await axios.get('/admin/user/export?' + params.toString(), { responseType: 'blob' })
        if (res.data.type === 'application/json') {
          const text = await res.data.text()
          const json = JSON.parse(text)
          if (json.code === 1001) {
            this.$router.push('/login')
            return
          }
          this.$message.error(json.msg || '导出失败')
          return
        }
        const url = window.URL.createObjectURL(res.data)
        const link = document.createElement('a')
        link.href = url
        link.download = ''
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url)
      } catch {
        this.$message.error('导出失败')
      }
    },
    handleImportDialog() {
      this.importFile = null
      this.importVisible = true
      this.$nextTick(() => {
        this.$refs.upload && this.$refs.upload.clearFiles()
      })
    },
    handleFileChange(file) {
      this.importFile = file.raw
    },
    async handleImportSubmit() {
      if (!this.importFile) return this.$message.warning('请选择文件')
      this.importing = true
      try {
        const formData = new FormData()
        formData.append('file', this.importFile)
        const res = await request.post('/user/import', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        if (res.code === 0) {
          this.$message.success(res.msg)
          this.importVisible = false
          this.fetchList({ page: this.page, limit: this.limit })
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {
        // 1001 已由拦截器处理
      }
      this.importing = false
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
            this.fetchList({ page: this.page, limit: this.limit })
          } else {
            this.$message.error(res.msg)
          }
        } catch (e) {
          // 1001 已由拦截器处理
        }
      }).catch(() => {})
    },
    async handleSubmit() {
      try {
        await this.$refs.form.validate()
      } catch {
        return
      }
      const { id, phone, password, nickname, email, gender, status } = this.form
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
          this.fetchList({ page: this.page, limit: this.limit })
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
