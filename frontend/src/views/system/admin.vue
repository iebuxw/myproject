<template>
  <div>
    <el-card>
      <div slot="header">
        <span>管理员管理</span>
        <el-button type="primary" size="small" style="float:right" @click="handleAdd">新增</el-button>
      </div>
      <el-table :data="list" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="username" label="管理员" />
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

    <el-dialog :title="dialogTitle" :visible.sync="dialogVisible" width="500px">
      <el-form ref="form" :model="form" label-width="80px">
        <el-form-item label="管理员" required>
          <el-input v-model="form.username" :disabled="!!form.id" />
        </el-form-item>
        <el-form-item label="密码" :required="!form.id">
          <el-input v-model="form.password" type="password" :placeholder="form.id ? '留空不修改' : ''" />
        </el-form-item>
        <el-form-item label="角色">
          <el-checkbox-group v-model="form.role_ids">
            <el-checkbox v-for="r in roleList" :key="r.id" :label="r.id">{{ r.name }}</el-checkbox>
          </el-checkbox-group>
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
  name: 'SystemAdmin',
  data() {
    return {
      list: [],
      roleList: [],
      dialogVisible: false,
      dialogTitle: '',
      form: { id: 0, username: '', password: '', status: 1, role_ids: [] }
    }
  },
  created() {
    this.fetchList()
    this.fetchRoles()
  },
  methods: {
    async fetchList() {
      const res = await request.get('/admin/list')
      if (res.code === 0) this.list = res.data.list
    },
    async fetchRoles() {
      const res = await request.get('/role/list')
      if (res.code === 0) this.roleList = res.data.list
    },
    handleAdd() {
      this.dialogTitle = '新增管理员'
      this.form = { id: 0, username: '', password: '', status: 1, role_ids: [] }
      this.dialogVisible = true
    },
    handleEdit(row) {
      this.dialogTitle = '编辑管理员'
      this.form = { id: row.id, username: row.username, password: '', status: row.status, role_ids: row.role_ids || [] }
      this.dialogVisible = true
    },
    handleDelete(row) {
      this.$confirm('确定删除该管理员吗?', '提示', { type: 'warning' }).then(async () => {
        const res = await request.delete('/admin/delete', { data: { id: row.id } })
        if (res.code === 0) {
          this.$message.success('删除成功')
          this.fetchList()
        } else {
          this.$message.error(res.msg)
        }
      }).catch(() => {})
    },
    async handleSubmit() {
      const { id, username, password, status, role_ids } = this.form
      if (!username) return this.$message.warning('请输入管理员用户名')
      if (!id && !password) return this.$message.warning('请输入密码')

      const api = id ? request.put : request.post
      const url = id ? '/admin/edit' : '/admin/add'
      const data = id ? { id, password, status, role_ids } : { username, password, role_ids }

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
