---
paths:
  - "frontend/**"
---

# 前端结构

Vue2 + Element UI 2.15 + Vue Router + Vuex。前端通过 Nginx 统一入口访问，无跨域问题。PHP 返回的菜单权限树驱动侧边栏渲染。

## 权限指令

`v-auth="'xxx'"` 控制按钮级显示，基于 Vuex `permissions` 状态响应式隐藏无权限元素。

## 字典工具

`utils/dict.js` 提供 `loadDicts(...codes)` 批量加载字典项（带缓存）和 `dictMap(items)` 转换为 `{value: label}` 映射，配合后端 `/dict_data/items` 接口。

## 源码结构

```
frontend/src/
├── api/           # 后端 API 封装（按模块拆分，统一 axios 实例）
├── components/    # 公共组件
├── directives/    # 自定义指令（如权限指令）
├── layout/        # 布局组件（侧边栏、顶栏、标签页等）
├── router/        # 路由配置 + 动态权限路由
├── store/         # Vuex 状态管理
├── utils/         # 工具函数
├── views/         # 页面视图（目录按业务模块自动划分，包含：登录页、用户中心、日志管理、系统管理等，具体以实际路由与文件为准）
├── App.vue
└── main.js
```

## 框架文档

写前端代码时必须遵循 Vue2/Element UI 规范，不确定用法时用 WebFetch 查阅官方文档：

- **Vue 2**：https://cn.vuejs.org/guide/quick-start.html
- **Element UI 2**：https://element.eleme.cn/#/zh-CN/component/installation
