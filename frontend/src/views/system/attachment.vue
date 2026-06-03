<template>
  <div>
    <el-card>
      <div slot="header">
        <span>文件管理</span>
      </div>

      <el-form :model="searchForm" inline size="small" style="margin-bottom:15px">
        <el-form-item label="文件名">
          <el-input v-model="searchForm.keyword" placeholder="请输入文件名" clearable @keyup.enter.native="handleSearch" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
        <el-form-item style="float:right">
          <el-upload
            :show-file-list="false"
            :before-upload="beforeUpload"
            :http-request="handleUpload"
            accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar"
          >
            <el-button type="primary" size="small" :loading="uploading">上传文件</el-button>
          </el-upload>
        </el-form-item>
      </el-form>

      <el-table :data="list" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column label="预览" width="80" align="center">
          <template slot-scope="{row}">
            <el-image
              v-if="isImage(row.ext)"
              :src="row.file_path"
              :preview-src-list="[row.file_path]"
              fit="cover"
              style="width:50px;height:50px;border-radius:4px"
            />
            <i v-else :class="fileIcon(row.ext)" style="font-size:28px;color:#909399" />
          </template>
        </el-table-column>
        <el-table-column prop="original_name" label="文件名" show-overflow-tooltip />
        <el-table-column label="大小" width="110">
          <template slot-scope="{row}">
            {{ formatSize(row.file_size) }}
          </template>
        </el-table-column>
        <el-table-column prop="ext" label="类型" width="80" />
        <el-table-column prop="uploader_name" label="上传者" width="120" />
        <el-table-column prop="created_at" label="上传时间" width="180" />
        <el-table-column label="操作" width="120">
          <template slot-scope="{row}">
            <el-button type="text" @click="handleDownload(row)">下载</el-button>
            <el-button type="text" style="color:#f56c6c" @click="handleDelete(row)">删除</el-button>
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
  </div>
</template>

<script>
import request from '@/api'

export default {
  name: 'AttachmentList',
  data() {
    return {
      list: [],
      page: 1,
      limit: 10,
      total: 0,
      searchForm: { keyword: '' },
      uploading: false
    }
  },
  created() {
    this.fetchList({ page: this.page, limit: this.limit })
  },
  methods: {
    async fetchList(params) {
      try {
        const res = await request.get('/attachment/list', { params })
        if (res.code === 0) {
          this.list = res.data.list
          this.total = res.data.total
        }
      } catch (e) {
        // 1001 已由拦截器处理
      }
    },
    isImage(ext) {
      return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'].includes((ext || '').toLowerCase())
    },
    fileIcon(ext) {
      const e = (ext || '').toLowerCase()
      if (['doc', 'docx'].includes(e)) return 'el-icon-document'
      if (['xls', 'xlsx'].includes(e)) return 'el-icon-s-grid'
      if (['ppt', 'pptx'].includes(e)) return 'el-icon-data-board'
      if (e === 'pdf') return 'el-icon-reading'
      if (['zip', 'rar', '7z'].includes(e)) return 'el-icon-suitcase'
      return 'el-icon-folder'
    },
    formatSize(bytes) {
      if (!bytes) return '0 B'
      const units = ['B', 'KB', 'MB', 'GB']
      let i = 0
      let size = bytes
      while (size >= 1024 && i < units.length - 1) {
        size /= 1024
        i++
      }
      return size.toFixed(i === 0 ? 0 : 1) + ' ' + units[i]
    },
    beforeUpload(file) {
      const maxSize = 10 * 1024 * 1024
      if (file.size > maxSize) {
        this.$message.error('文件不能超过10MB')
        return false
      }
      return true
    },
    async handleUpload(options) {
      this.uploading = true
      try {
        const formData = new FormData()
        formData.append('file', options.file)
        const res = await request.post('/attachment/upload', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        if (res.code === 0) {
          this.$message.success('上传成功')
          this.fetchList({ page: this.page, limit: this.limit })
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {
        // 1001 已由拦截器处理
      }
      this.uploading = false
    },
    handleSearch() {
      this.page = 1
      const params = { page: 1, limit: this.limit }
      if (this.searchForm.keyword) params.keyword = this.searchForm.keyword
      this.fetchList(params)
    },
    handleReset() {
      this.searchForm = { keyword: '' }
      this.page = 1
      this.fetchList({ page: 1, limit: this.limit })
    },
    handlePageChange(page) {
      this.page = page
      const params = { page, limit: this.limit }
      if (this.searchForm.keyword) params.keyword = this.searchForm.keyword
      this.fetchList(params)
    },
    handleDownload(row) {
      window.open(row.file_path, '_blank')
    },
    handleDelete(row) {
      this.$confirm('确定删除该文件吗？删除后不可恢复', '提示', { type: 'warning' }).then(async () => {
        try {
          const res = await request.delete('/attachment/delete', { data: { id: row.id } })
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
    }
  }
}
</script>
