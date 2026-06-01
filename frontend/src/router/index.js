import Vue from 'vue'
import VueRouter from 'vue-router'
import Layout from '@/layout'

Vue.use(VueRouter)

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/login/index'),
    meta: { title: '登录' }
  },
  {
    path: '/',
    component: Layout,
    redirect: '/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/dashboard/index'),
        meta: { title: '首页' }
      },
      {
        path: 'system/admin',
        name: 'Admin',
        component: () => import('@/views/system/admin'),
        meta: { title: '管理员管理' }
      },
      {
        path: 'user/list',
        name: 'User',
        component: () => import('@/views/user'),
        meta: { title: '用户管理' }
      },
      {
        path: 'system/role',
        name: 'Role',
        component: () => import('@/views/system/role'),
        meta: { title: '角色管理' }
      },
      {
        path: 'system/menu',
        name: 'Menu',
        component: () => import('@/views/system/menu'),
        meta: { title: '菜单管理' }
      },
      {
        path: 'log/login',
        name: 'LoginLog',
        component: () => import('@/views/log/login'),
        meta: { title: '登录日志' }
      },
      {
        path: 'log/operation',
        name: 'OperationLog',
        component: () => import('@/views/log/operation'),
        meta: { title: '操作日志' }
      }
    ]
  }
]

const router = new VueRouter({
  mode: 'history',
  routes
})

// 路由守卫
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('admin_token')

  if (to.path === '/login') {
    if (token) {
      next('/dashboard')
    } else {
      next()
    }
    return
  }

  if (!token) {
    next('/login')
    return
  }

  next()
})

export default router
