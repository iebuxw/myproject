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
        path: 'system/dict',
        name: 'Dict',
        component: () => import('@/views/system/dict'),
        meta: { title: '字典管理' }
      },
      {
        path: 'system/attachment',
        name: 'Attachment',
        component: () => import('@/views/system/attachment'),
        meta: { title: '文件管理' }
      },
      {
        path: 'system/config',
        name: 'SystemConfig',
        component: () => import('@/views/system/config'),
        meta: { title: '系统配置' }
      },
      {
        path: 'system/notice',
        name: 'Notice',
        component: () => import('@/views/system/notice'),
        meta: { title: '通知公告' }
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
      },
      {
        path: 'log/settings',
        name: 'LogSettings',
        component: () => import('@/views/log/settings'),
        meta: { title: '日志设置' }
      },
      {
        path: 'profile',
        name: 'Profile',
        component: () => import('@/views/profile/index'),
        meta: { title: '个人中心' }
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
