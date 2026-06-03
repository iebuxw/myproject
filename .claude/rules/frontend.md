---
paths:
  - "frontend/**"
---

# 前端结构

```
frontend/src/
├── api/index.js          # axios 实例（baseURL=/admin，1001 拦截跳转登录）
├── router/index.js       # 路由表 + beforeEach 守卫（检查 admin_token）
├── store/index.js        # Vuex：login → getInfo → SET_ADMIN/SET_ROLES/SET_MENUS
├── utils/auth.js         # token 存取工具函数
├── layout/index.vue      # 主布局（侧边栏 + 顶栏 + 内容区）
├── components/Sidebar.vue # 根据 store.menus 动态渲染菜单树
└── views/
    ├── login/index.vue   # 登录页
    ├── dashboard/index.vue
    ├── user/index.vue    # 用户管理（分页）
    ├── profile/index.vue # 个人中心（头像上传）
    ├── log/              # 登录日志、操作日志
    └── system/
        ├── admin.vue     # 管理员 CRUD
        ├── role.vue      # 角色 CRUD + el-tree 菜单分配
        ├── menu.vue      # 菜单树形表格 CRUD
        ├── dict.vue      # 字典类型 + 字典数据
        └── attachment.vue # 文件管理（上传/列表/删除）
```

Vue2 + Element UI 2.15 + Vue Router + Vuex。前端通过 Nginx 统一入口访问，无跨域问题。PHP 返回的菜单权限树驱动侧边栏渲染。

## 框架文档

写前端代码时必须遵循 Vue2/Element UI 规范，不确定用法时用 WebFetch 查阅官方文档：

- **Vue 2**：https://cn.vuejs.org/guide/quick-start.html
- **Element UI 2**：https://element.eleme.cn/#/zh-CN/component/installation
