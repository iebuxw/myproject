import axios from 'axios'
import router from '@/router'
import { removeToken } from '@/utils/auth'

const request = axios.create({
  baseURL: '/admin',
  timeout: 10000
})

// 响应拦截器
request.interceptors.response.use(
  response => {
    const res = response.data
    if (res.code === 1001) {
      // 清除 token 后再跳转，避免路由守卫死循环
      removeToken()
      router.push('/login')
      return Promise.reject(new Error(res.msg || '未登录'))
    }
    return res
  },
  error => {
    console.error('Request error:', error)
    return Promise.reject(error)
  }
)

export default request
