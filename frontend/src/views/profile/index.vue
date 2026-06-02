<template>
  <div>
    <!-- 基本信息 -->
    <el-card style="margin-bottom:20px">
      <div slot="header"><span>基本信息</span></div>
      <el-form label-width="100px">
        <el-form-item label="用户名">
          <span>{{ admin ? admin.username : '' }}</span>
        </el-form-item>
        <el-form-item label="头像">
          <div style="display:flex;align-items:center;gap:15px">
            <el-avatar :size="64" :src="avatarFullUrl" icon="el-icon-user-solid" />
            <el-upload
              :show-file-list="false"
              :before-upload="beforeAvatarUpload"
              :http-request="handleAvatarUpload"
              accept="image/jpeg,image/png,image/gif"
              action=""
            >
              <el-button size="small" type="primary">更换头像</el-button>
            </el-upload>
          </div>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 修改密码 -->
    <el-card>
      <div slot="header"><span>修改密码</span></div>
      <el-form ref="pwdForm" :model="pwdForm" :rules="pwdRules" label-width="100px" style="max-width:500px">
        <el-form-item label="原密码" prop="old_password">
          <el-input v-model="pwdForm.old_password" type="password" placeholder="请输入原密码" />
        </el-form-item>
        <el-form-item label="新密码" prop="new_password">
          <el-input v-model="pwdForm.new_password" type="password" placeholder="不少于6位" />
        </el-form-item>
        <el-form-item label="确认密码" prop="confirm_password">
          <el-input v-model="pwdForm.confirm_password" type="password" placeholder="再次输入新密码" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :loading="pwdLoading" @click="handleChangePassword">确认修改</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script>
import { mapState } from 'vuex'
import request from '@/api'

export default {
  name: 'Profile',
  data() {
    const validateConfirm = (rule, value, callback) => {
      if (value !== this.pwdForm.new_password) {
        callback(new Error('两次输入的密码不一致'))
      } else {
        callback()
      }
    }
    return {
      pwdLoading: false,
      pwdForm: {
        old_password: '',
        new_password: '',
        confirm_password: ''
      },
      pwdRules: {
        old_password: [{ required: true, message: '请输入原密码', trigger: 'blur' }],
        new_password: [
          { required: true, message: '请输入新密码', trigger: 'blur' },
          { min: 6, message: '密码长度不能少于6位', trigger: 'blur' }
        ],
        confirm_password: [
          { required: true, message: '请再次输入新密码', trigger: 'blur' },
          { validator: validateConfirm, trigger: 'blur' }
        ]
      }
    }
  },
  computed: {
    ...mapState(['admin']),
    avatarFullUrl() {
      if (this.admin && this.admin.avatar) {
        return this.admin.avatar
      }
      return ''
    }
  },
  created() {
    if (!this.admin) {
      this.$store.dispatch('getInfo')
    }
  },
  methods: {
    beforeAvatarUpload(file) {
      const allowTypes = ['image/jpeg', 'image/png', 'image/gif']
      if (!allowTypes.includes(file.type)) {
        this.$message.error('只支持 jpg/png/gif 格式')
        return false
      }
      if (file.size > 2 * 1024 * 1024) {
        this.$message.error('头像文件不能超过2MB')
        return false
      }
      return true
    },
    async handleAvatarUpload(options) {
      const formData = new FormData()
      formData.append('file', options.file)
      try {
        const res = await request.post('/profile/avatar', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        if (res.code === 0) {
          this.$message.success('头像上传成功')
          this.$store.dispatch('getInfo')
        } else {
          this.$message.error(res.msg || '头像上传失败')
        }
      } catch (e) {
        this.$message.error('头像上传失败')
      }
    },
    handleChangePassword() {
      this.$refs.pwdForm.validate(async (valid) => {
        if (!valid) return
        this.pwdLoading = true
        try {
          const res = await request.put('/profile/password', {
            old_password: this.pwdForm.old_password,
            new_password: this.pwdForm.new_password
          })
          if (res.code === 0) {
            this.$message.success('密码修改成功，请重新登录')
            this.$refs.pwdForm.resetFields()
            this.$store.dispatch('logout').then(() => {
              this.$router.push('/login')
            })
          } else {
            this.$message.error(res.msg || '密码修改失败')
          }
        } catch (e) {
          this.$message.error('密码修改失败')
        } finally {
          this.pwdLoading = false
        }
      })
    }
  }
}
</script>
