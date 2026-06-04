<template>
  <div>
    <!-- 字典类型列表 -->
    <el-card>
      <div slot="header">
        <span>字典管理</span>
        <el-button v-auth="'dict:add'" type="primary" size="small" style="float:right" @click="handleAddType">新增</el-button>
      </div>
      <el-row style="margin-bottom:15px">
        <el-input v-model="keyword" placeholder="输入编码或名称搜索" clearable style="width:220px" @keyup.enter.native="fetchTypeList" />
        <el-button type="primary" style="margin-left:10px" @click="fetchTypeList">搜索</el-button>
      </el-row>
      <el-table :data="typeList" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="code" label="类型编码" width="150" />
        <el-table-column prop="name" label="类型名称" width="150" />
        <el-table-column prop="status" label="状态" width="80">
          <template slot-scope="{row}">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'">{{ row.status === 1 ? '启用' : '停用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="120" />
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" width="200">
          <template slot-scope="{row}">
            <el-button v-auth="'dict:edit'" type="text" @click="handleEditType(row)">编辑</el-button>
            <el-button type="text" @click="openDrawer(row)">字典项</el-button>
            <el-button v-auth="'dict:delete'" type="text" style="color:#f56c6c" @click="handleDeleteType(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-pagination
        v-if="typeTotal > 0"
        style="margin-top:15px;text-align:right"
        :current-page="typePage"
        :page-size="typeLimit"
        :total="typeTotal"
        layout="total, prev, pager, next"
        @current-change="onTypePageChange"
      />
    </el-card>

    <!-- 字典类型弹窗 -->
    <el-dialog :title="typeDialogTitle" :visible.sync="typeDialogVisible" width="500px">
      <el-form ref="typeForm" :model="typeForm" label-width="80px">
        <el-form-item label="类型编码" required>
          <el-input v-model="typeForm.code" :disabled="!!typeForm.id" />
        </el-form-item>
        <el-form-item label="类型名称" required>
          <el-input v-model="typeForm.name" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="typeForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="typeForm.remark" type="textarea" />
        </el-form-item>
      </el-form>
      <span slot="footer">
        <el-button @click="typeDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitType">确定</el-button>
      </span>
    </el-dialog>

    <!-- 字典项抽屉 -->
    <el-drawer :title="'字典项 — ' + currentTypeName" :visible.sync="drawerVisible" size="500px">
      <div style="padding:0 20px">
        <el-button v-auth="'dict:add'" type="primary" size="small" style="margin-bottom:15px" @click="handleAddData">新增字典项</el-button>
        <el-table :data="dataList" border stripe>
          <el-table-column prop="label" label="标签" width="120" />
          <el-table-column prop="value" label="值" width="100" />
          <el-table-column prop="sort" label="排序" width="60" />
          <el-table-column prop="status" label="状态" width="70">
            <template slot-scope="{row}">
              <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">{{ row.status === 1 ? '启用' : '停用' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="remark" label="备注" min-width="100" />
          <el-table-column label="操作" width="120">
            <template slot-scope="{row}">
              <el-button v-auth="'dict:edit'" type="text" @click="handleEditData(row)">编辑</el-button>
              <el-button v-auth="'dict:delete'" type="text" style="color:#f56c6c" @click="handleDeleteData(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-pagination
          v-if="dataTotal > 0"
          style="margin-top:15px;text-align:right"
          :current-page="dataPage"
          :page-size="dataLimit"
          :total="dataTotal"
          layout="total, prev, pager, next"
          @current-change="onDataPageChange"
        />
      </div>
    </el-drawer>

    <!-- 字典项弹窗 -->
    <el-dialog :title="dataDialogTitle" :visible.sync="dataDialogVisible" width="500px" append-to-body>
      <el-form ref="dataForm" :model="dataForm" label-width="80px">
        <el-form-item label="标签" required>
          <el-input v-model="dataForm.label" />
        </el-form-item>
        <el-form-item label="值" required>
          <el-input v-model="dataForm.value" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="dataForm.sort" :min="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="dataForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="dataForm.remark" type="textarea" />
        </el-form-item>
      </el-form>
      <span slot="footer">
        <el-button @click="dataDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitData">确定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import request from '@/api'

export default {
  name: 'SystemDict',
  data() {
    return {
      // 字典类型
      keyword: '',
      typeList: [],
      typePage: 1,
      typeLimit: 20,
      typeTotal: 0,
      typeDialogVisible: false,
      typeDialogTitle: '',
      typeForm: { id: 0, code: '', name: '', status: 1, remark: '' },
      // 字典项
      currentTypeId: 0,
      currentTypeName: '',
      drawerVisible: false,
      dataList: [],
      dataPage: 1,
      dataLimit: 20,
      dataTotal: 0,
      dataDialogVisible: false,
      dataDialogTitle: '',
      dataForm: { id: 0, type_id: 0, label: '', value: '', sort: 0, status: 1, remark: '' }
    }
  },
  created() {
    this.fetchTypeList()
  },
  methods: {
    async fetchTypeList() {
      const res = await request.get('/dict_type/list', { params: { keyword: this.keyword, page: this.typePage, limit: this.typeLimit } })
      if (res.code === 0) {
        this.typeList = res.data.list
        this.typeTotal = res.data.total
      }
    },
    onTypePageChange(page) {
      this.typePage = page
      this.fetchTypeList()
    },
    handleAddType() {
      this.typeDialogTitle = '新增字典类型'
      this.typeForm = { id: 0, code: '', name: '', status: 1, remark: '' }
      this.typeDialogVisible = true
    },
    handleEditType(row) {
      this.typeDialogTitle = '编辑字典类型'
      this.typeForm = { id: row.id, code: row.code, name: row.name, status: row.status, remark: row.remark }
      this.typeDialogVisible = true
    },
    handleDeleteType(row) {
      this.$confirm('确定删除该字典类型吗？其下所有字典项也将被删除', '提示', { type: 'warning' }).then(async () => {
        const res = await request.delete('/dict_type/delete', { data: { id: row.id } })
        if (res.code === 0) {
          this.$message.success('删除成功')
          this.fetchTypeList()
        } else {
          this.$message.error(res.msg)
        }
      }).catch(() => {})
    },
    async handleSubmitType() {
      const { id, code, name, status, remark } = this.typeForm
      if (!code || !name) return this.$message.warning('请填写编码和名称')

      const api = id ? request.put : request.post
      const url = id ? '/dict_type/edit' : '/dict_type/add'
      const data = id ? { id, code, name, status, remark } : { code, name, status, remark }

      const res = await api(url, data)
      if (res.code === 0) {
        this.$message.success(id ? '编辑成功' : '新增成功')
        this.typeDialogVisible = false
        this.fetchTypeList()
      } else {
        this.$message.error(res.msg)
      }
    },
    // 字典项
    openDrawer(row) {
      this.currentTypeId = row.id
      this.currentTypeName = row.name
      this.dataPage = 1
      this.drawerVisible = true
      this.fetchDataList()
    },
    async fetchDataList() {
      const res = await request.get('/dict_data/list', { params: { type_id: this.currentTypeId, page: this.dataPage, limit: this.dataLimit } })
      if (res.code === 0) {
        this.dataList = res.data.list
        this.dataTotal = res.data.total
      }
    },
    onDataPageChange(page) {
      this.dataPage = page
      this.fetchDataList()
    },
    handleAddData() {
      this.dataDialogTitle = '新增字典项'
      this.dataForm = { id: 0, type_id: this.currentTypeId, label: '', value: '', sort: 0, status: 1, remark: '' }
      this.dataDialogVisible = true
    },
    handleEditData(row) {
      this.dataDialogTitle = '编辑字典项'
      this.dataForm = { id: row.id, type_id: row.type_id, label: row.label, value: row.value, sort: row.sort, status: row.status, remark: row.remark }
      this.dataDialogVisible = true
    },
    handleDeleteData(row) {
      this.$confirm('确定删除该字典项吗？', '提示', { type: 'warning' }).then(async () => {
        const res = await request.delete('/dict_data/delete', { data: { id: row.id } })
        if (res.code === 0) {
          this.$message.success('删除成功')
          this.fetchDataList()
        } else {
          this.$message.error(res.msg)
        }
      }).catch(() => {})
    },
    async handleSubmitData() {
      const { id, type_id, label, value, sort, status, remark } = this.dataForm
      if (!label || value === '') return this.$message.warning('请填写标签和值')

      const api = id ? request.put : request.post
      const url = id ? '/dict_data/edit' : '/dict_data/add'
      const data = id ? { id, label, value, sort, status, remark } : { type_id, label, value, sort, status, remark }

      const res = await api(url, data)
      if (res.code === 0) {
        this.$message.success(id ? '编辑成功' : '新增成功')
        this.dataDialogVisible = false
        this.fetchDataList()
      } else {
        this.$message.error(res.msg)
      }
    }
  }
}
</script>
