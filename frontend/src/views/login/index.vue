<template>
  <div class="login-container">
    <el-card class="login-card">
      <h2 style="text-align:center;margin-bottom:20px">{{ siteName }}</h2>
      <el-form ref="form" :model="form" :rules="rules">
        <el-form-item prop="username">
          <el-input v-model="form.username" placeholder="用户名" prefix-icon="el-icon-user" />
        </el-form-item>
        <el-form-item prop="password">
          <el-input v-model="form.password" type="password" placeholder="密码" prefix-icon="el-icon-lock" @keyup.enter.native="handleLogin" />
        </el-form-item>
        <el-form-item prop="captcha_code">
          <div class="captcha-row">
            <el-input v-model="form.captcha_code" placeholder="验证码" prefix-icon="el-icon-key" @keyup.enter.native="handleLogin" />
            <img
              v-if="captchaImage"
              :src="captchaImage"
              class="captcha-img"
              title="点击刷新"
              @click="refreshCaptcha"
            />
          </div>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" style="width:100%" :loading="loading" @click="handleLogin">登录</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script>
import { mapState } from 'vuex'
import request from '@/api'

export default {
  name: 'Login',
  data() {
    return {
      form: { username: 'admin', password: '', captcha_code: '' },
      rules: {
        username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
        password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
        captcha_code: [{ required: true, message: '请输入验证码', trigger: 'blur' }]
      },
      loading: false,
      siteName: '后台管理系统'
    }
  },
  computed: {
    ...mapState(['captchaImage'])
  },
  mounted() {
    this.refreshCaptcha()
    this.fetchSiteName()
  },
  methods: {
    refreshCaptcha() {
      this.$store.dispatch('getCaptcha')
    },
    async fetchSiteName() {
      try {
        const res = await request.get('/system_config/read')
        if (res.code === 0 && res.data.site_name) {
          this.siteName = res.data.site_name
        }
      } catch (e) { /* ignore */ }
    },
    handleLogin() {
      this.$refs.form.validate(valid => {
        if (!valid) return
        this.loading = true
        this.$store.dispatch('login', {
          username: this.form.username,
          password: this.form.password,
          captcha_code: this.form.captcha_code
        }).then(res => {
          this.loading = false
          if (res.code === 0) {
            this.$router.push('/dashboard')
          } else {
            this.form.captcha_code = ''
            this.$message.error(res.msg || '登录失败')
          }
        }).catch(() => {
          this.loading = false
          this.form.captcha_code = ''
          this.refreshCaptcha()
        })
      })
    }
  }
}
</script>

<style scoped>
.login-container {
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f0f2f5;
}
.login-card {
  width: 400px;
}
.captcha-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.captcha-row .el-input {
  flex: 1;
}
.captcha-img {
  height: 40px;
  cursor: pointer;
  border-radius: 4px;
  border: 1px solid #dcdfe6;
  flex-shrink: 0;
}
</style>
