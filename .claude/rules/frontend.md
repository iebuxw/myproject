---
paths:
  - "frontend/**"
---

# 前端结构

Vue2 + Element UI 2.15 + Vue Router + Vuex。前端通过 Nginx 统一入口访问，无跨域问题。PHP 返回的菜单权限树驱动侧边栏渲染。

## 本地开发注意

`npm run serve` 仅代理 `/admin` 到本地 PHP，`/api/`（Go API）无代理规则。需直连 Go API 时，在 `vue.config.js` 的 `devServer.proxy` 中添加 `/api` 代理，或通过 Docker Nginx 统一入口访问。

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
