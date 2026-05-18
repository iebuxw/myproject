import axios from 'axios'
import router from '@/router'

const request = axios.create({
  baseURL: '/admin',
  timeout: 10000
})

// 响应拦截器
request.interceptors.response.use(
  response => {
    const res = response.data
    if (res.code === 1001) {
      // 未登录，跳转登录页
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
