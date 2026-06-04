<template>
  <div>
    <el-card>
      <div slot="header">
        <span>角色管理</span>
        <el-button v-auth="'role:add'" type="primary" size="small" style="float:right" @click="handleAdd">新增</el-button>
      </div>
      <el-table :data="list" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="name" label="角色名" />
        <el-table-column prop="description" label="描述" />
        <el-table-column prop="status" label="状态" width="80">
          <template slot-scope="{row}">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" width="150">
          <template slot-scope="{row}">
            <el-button v-auth="'role:edit'" type="text" @click="handleEdit(row)">编辑</el-button>
            <el-button v-auth="'role:delete'" type="text" style="color:#f56c6c" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog :title="dialogTitle" :visible.sync="dialogVisible" width="500px">
      <el-form ref="form" :model="form" label-width="80px">
        <el-form-item label="角色名" required>
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="form.description" type="textarea" />
        </el-form-item>
        <el-form-item label="菜单权限">
          <el-tree
            ref="tree"
            :data="menuTree"
            show-checkbox
            node-key="id"
            :default-checked-keys="form.menu_ids"
            :props="{ label: 'name', children: 'children' }"
          />
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
  name: 'SystemRole',
  data() {
    return {
      list: [],
      menuTree: [],
      dialogVisible: false,
      dialogTitle: '',
      form: { id: 0, name: '', description: '', menu_ids: [] }
    }
  },
  created() {
    this.fetchList()
    this.fetchMenus()
  },
  methods: {
    async fetchList() {
      const res = await request.get('/role/list')
      if (res.code === 0) this.list = res.data.list
    },
    async fetchMenus() {
      const res = await request.get('/menu/list')
      if (res.code === 0) this.menuTree = res.data.list
    },
    handleAdd() {
      this.dialogTitle = '新增角色'
      this.form = { id: 0, name: '', description: '', menu_ids: [] }
      this.dialogVisible = true
    },
    handleEdit(row) {
      this.dialogTitle = '编辑角色'
      this.form = {
        id: row.id,
        name: row.name,
        description: row.description,
        menu_ids: row.menu_ids || []
      }
      this.dialogVisible = true
    },
    handleDelete(row) {
      this.$confirm('确定删除该角色吗?', '提示', { type: 'warning' }).then(async () => {
        const res = await request.delete('/role/delete', { data: { id: row.id } })
        if (res.code === 0) {
          this.$message.success('删除成功')
          this.fetchList()
        } else {
          this.$message.error(res.msg)
        }
      }).catch(() => {})
    },
    async handleSubmit() {
      if (!this.form.name) return this.$message.warning('请输入角色名')

      const checkedKeys = this.$refs.tree.getCheckedKeys()
      const halfCheckedKeys = this.$refs.tree.getHalfCheckedKeys()
      const menuIds = [...checkedKeys, ...halfCheckedKeys]

      const { id, name, description } = this.form
      const api = id ? request.put : request.post
      const url = id ? '/role/edit' : '/role/add'
      const data = id ? { id, name, description, menu_ids: menuIds } : { name, description, menu_ids: menuIds }

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
