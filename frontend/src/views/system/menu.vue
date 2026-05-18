<template>
  <div>
    <el-card>
      <div slot="header">
        <span>菜单管理</span>
        <el-button type="primary" size="small" style="float:right" @click="handleAdd(0)">新增顶级菜单</el-button>
      </div>
      <el-table :data="list" border stripe row-key="id" :tree-props="{ children: 'children' }">
        <el-table-column prop="name" label="菜单名" />
        <el-table-column prop="path" label="路由路径" />
        <el-table-column prop="icon" label="图标" />
        <el-table-column prop="type" label="类型" width="80">
          <template slot-scope="{row}">
            <el-tag :type="row.type === 1 ? '' : row.type === 2 ? 'success' : 'info'">
              {{ {1:'目录',2:'菜单',3:'按钮'}[row.type] }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="60" />
        <el-table-column prop="status" label="状态" width="80">
          <template slot-scope="{row}">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200">
          <template slot-scope="{row}">
            <el-button type="text" @click="handleAdd(row.id)">新增子级</el-button>
            <el-button type="text" @click="handleEdit(row)">编辑</el-button>
            <el-button type="text" style="color:#f56c6c" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog :title="dialogTitle" :visible.sync="dialogVisible" width="500px">
      <el-form ref="form" :model="form" label-width="80px">
        <el-form-item label="上级菜单">
          <el-input :value="parentName" disabled />
        </el-form-item>
        <el-form-item label="菜单名" required>
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item label="路由路径">
          <el-input v-model="form.path" />
        </el-form-item>
        <el-form-item label="图标">
          <el-input v-model="form.icon" placeholder="element-ui 图标名，如 el-icon-setting" />
        </el-form-item>
        <el-form-item label="类型" required>
          <el-select v-model="form.type" style="width:100%">
            <el-option :value="1" label="目录" />
            <el-option :value="2" label="菜单" />
            <el-option :value="3" label="按钮" />
          </el-select>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
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
  name: 'SystemMenu',
  data() {
    return {
      list: [],
      dialogVisible: false,
      dialogTitle: '',
      parentName: '',
      form: { id: 0, parent_id: 0, name: '', path: '', icon: '', type: 2, sort: 0, status: 1 }
    }
  },
  created() {
    this.fetchList()
  },
  methods: {
    async fetchList() {
      const res = await request.get('/menu/list')
      if (res.code === 0) this.list = res.data.list
    },
    handleAdd(parentId) {
      this.dialogTitle = parentId === 0 ? '新增顶级菜单' : '新增子菜单'
      this.form = { id: 0, parent_id: parentId, name: '', path: '', icon: '', type: 2, sort: 0, status: 1 }
      this.parentName = parentId === 0 ? '顶级' : this.findMenuName(parentId)
      this.dialogVisible = true
    },
    handleEdit(row) {
      this.dialogTitle = '编辑菜单'
      this.form = {
        id: row.id,
        parent_id: row.parent_id,
        name: row.name,
        path: row.path,
        icon: row.icon,
        type: row.type,
        sort: row.sort,
        status: row.status
      }
      this.parentName = row.parent_id === 0 ? '顶级' : this.findMenuName(row.parent_id)
      this.dialogVisible = true
    },
    findMenuName(id) {
      const find = (items) => {
        for (const item of items) {
          if (item.id === id) return item.name
          if (item.children) {
            const r = find(item.children)
            if (r) return r
          }
        }
        return ''
      }
      return find(this.list)
    },
    handleDelete(row) {
      this.$confirm('删除该菜单会同时删除其子菜单，确定删除吗?', '提示', { type: 'warning' }).then(async () => {
        const res = await request.delete('/menu/delete', { data: { id: row.id } })
        if (res.code === 0) {
          this.$message.success('删除成功')
          this.fetchList()
        } else {
          this.$message.error(res.msg)
        }
      }).catch(() => {})
    },
    async handleSubmit() {
      if (!this.form.name) return this.$message.warning('请输入菜单名')

      const { id, parent_id, name, path, icon, type, sort, status } = this.form
      const api = id ? request.put : request.post
      const url = id ? '/menu/edit' : '/menu/add'
      const data = id
        ? { id, parent_id, name, path, icon, type, sort, status }
        : { parent_id, name, path, icon, type, sort, status }

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
