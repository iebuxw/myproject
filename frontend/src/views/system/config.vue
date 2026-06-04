<template>
  <el-card>
    <div slot="header"><span>系统配置</span></div>
    <el-form ref="form" :model="form" :rules="rules" label-width="120px" style="max-width:600px">
      <el-form-item label="站点名称" prop="site_name">
        <el-input v-model="form.site_name" maxlength="50" show-word-limit placeholder="请输入站点名称" />
      </el-form-item>
      <el-form-item label="Logo">
        <div v-if="logoUrl" class="logo-preview">
          <img :src="logoUrl" />
          <el-button size="mini" type="danger" icon="el-icon-delete" circle @click="handleLogoRemove" />
        </div>
        <el-upload
          v-else
          :before-upload="beforeLogoUpload"
          :http-request="handleLogoUpload"
          action=""
          accept="image/jpeg,image/png,image/svg+xml"
          :show-file-list="false"
        >
          <el-button size="small" type="primary">上传 Logo</el-button>
          <span slot="tip" class="el-upload__tip" style="margin-left:8px">jpg/png/svg，不超过2MB</span>
        </el-upload>
      </el-form-item>
      <el-form-item>
        <el-button v-auth="'system_config:edit'" type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </el-form-item>
    </el-form>
  </el-card>
</template>

<script>
import request from '@/api'

export default {
  name: 'SystemConfig',
  data() {
    return {
      saving: false,
      form: {
        site_name: '',
        logo: ''
      },
      logoUrl: '',
      rules: {
        site_name: [{ required: true, message: '请输入站点名称', trigger: 'blur' }]
      }
    }
  },
  created() {
    this.fetchConfig()
  },
  methods: {
    async fetchConfig() {
      try {
        const res = await request.get('/system_config/read')
        if (res.code === 0) {
          this.form.site_name = res.data.site_name || ''
          this.form.logo = res.data.logo || ''
          this.logoUrl = res.data.logo_url || ''
        }
      } catch (e) {
        this.$message.error('获取配置失败')
      }
    },
    beforeLogoUpload(file) {
      const allowTypes = ['image/jpeg', 'image/png', 'image/svg+xml']
      if (!allowTypes.includes(file.type)) {
        this.$message.error('只支持 jpg/png/svg 格式')
        return false
      }
      if (file.size > 2 * 1024 * 1024) {
        this.$message.error('Logo 文件不能超过2MB')
        return false
      }
      return true
    },
    async handleLogoUpload(options) {
      const formData = new FormData()
      formData.append('file', options.file)
      try {
        const res = await request.post('/attachment/upload', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        if (res.code === 0) {
          this.form.logo = String(res.data.id)
          this.logoUrl = res.data.file_path
          this.$message.success('Logo 上传成功')
        } else {
          this.$message.error(res.msg || 'Logo 上传失败')
        }
      } catch (e) {
        this.$message.error('Logo 上传失败')
      }
    },
    handleLogoRemove() {
      this.form.logo = ''
      this.logoUrl = ''
    },
    handleSave() {
      this.$refs.form.validate(async (valid) => {
        if (!valid) return
        this.saving = true
        try {
          const res = await request.put('/system_config/update', {
            site_name: this.form.site_name,
            logo: this.form.logo
          })
          if (res.code === 0) {
            this.$message.success('保存成功')
            this.$store.commit('SET_SITE_CONFIG', {
              site_name: this.form.site_name,
              logo: this.form.logo,
              logo_url: this.logoUrl
            })
          } else {
            this.$message.error(res.msg || '保存失败')
          }
        } catch (e) {
          this.$message.error('保存失败')
        } finally {
          this.saving = false
        }
      })
    }
  }
}
</script>

<style scoped>
.logo-preview {
  display: flex;
  align-items: center;
  gap: 12px;
}
.logo-preview img {
  height: 40px;
  max-width: 200px;
  object-fit: contain;
  border: 1px solid #e6e6e6;
  border-radius: 4px;
  padding: 4px;
}
</style>
