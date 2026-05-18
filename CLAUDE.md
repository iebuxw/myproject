# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 启动与开发

```bash
# 一键构建并启动所有服务
docker-compose up -d --build

# 查看日志
docker-compose logs -f

# 停止所有服务
docker-compose down

# 单独重启某个服务
docker-compose restart php
docker-compose restart go
docker-compose restart nginx
```

**本地开发（不使用 Docker）**：
```bash
# 前端开发服务器（端口 8081，自动代理 /admin 到 127.0.0.1）
cd frontend && npm run serve

# Go API 开发
cd services/go/app && go run main.go

# PHP 语法检查
php -l services/php/app/application/admin/controller/Admin.php

# 验证 docker-compose 配置
docker-compose config --quiet
```

没有配置单元测试、lint 或 CI。

## 架构概览

### 路由分发（Nginx → PHP / Go / 静态文件）

Nginx 是唯一对外暴露端口的服务（:80），根据路径前缀分发：

| 路径 | 目标 | 说明 |
|------|------|------|
| `/admin` | `php:9000` (PHP-FPM) | 后台管理，入口 `public/index.php` |
| `/api/` | `go:8080` (Gin) | APP API，前缀 `/api/v1` |
| `/` | `/usr/share/nginx/html` | Vue 打包后的静态资源 |

Nginx 多阶段 Dockerfile 先编译 Vue（`npm run build`），再将 dist 拷贝到最终镜像。PHP 代码仅存在于 PHP 容器内（COPY 进去的），Nginx 通过 fastcgi_pass 转发，SCRIPT_FILENAME 指向 PHP 容器内路径 `/var/www/html/public/index.php`，没有卷挂载冲突。

### 认证体系

- **PHP 后台**：Session 驱动，存入 Redis（`session:` 前缀，3600s 过期）。`Auth` 中间件从 Session 取 `admin_id` 验证登录态，再通过 `admin_role` + `role_menu` 联表查出该管理员的所有权限路径存入 `$request->authPaths`。
- **Go API**：JWT 无状态认证。登录返回 access token（2h）+ refresh token（7d），请求头 `Authorization: Bearer <token>`。登出时将 token 加入 Redis 黑名单（`blacklist:` 前缀，2h TTL）。`JWTAuth` 中间件验证 token 有效性 + 检查黑名单。

### 数据库

MySQL 5.7，6 张表（均在 `services/mysql/init/init.sql` 中幂等定义）：

- **admin** — 后台管理员（PHP 后台登录用）
- **user** — APP 普通用户（Go API 登录用）
- **role** — 角色
- **menu** — 菜单/权限（type: 1=目录 2=菜单 3=按钮）
- **admin_role** — 管理员↔角色多对多
- **role_menu** — 角色↔菜单多对多

两个用户表是独立的：admin 是后台管理员，user 是 to-C 的 APP 用户，两者不交叉。

密码统一使用 bcrypt 哈希（PHP 端 `password_hash`/`password_verify`，Go 端 `golang.org/x/crypto/bcrypt`）。

### 统一响应格式

PHP 和 Go 都返回相同结构：
```json
{"code": 0, "msg": "success", "data": {...}}
```

错误码约定：`0`=成功，`1001`=未登录/token无效，`1002`=参数错误，`1003`=用户名/密码错误，`1004`=用户不存在，`500`=服务端错误。

### Go 项目结构

```
services/go/app/
├── main.go              # 入口：初始化 DB/Redis，启动 Gin
├── config/config.go      # 环境变量读取（含默认值回退）
├── router/router.go      # 路由注册 + CORS 中间件
├── middleware/jwt.go     # JWT 生成/验证 + Claims 定义
├── handler/auth.go       # Login/Refresh/Logout
├── handler/user.go       # GetProfile/UpdateProfile
└── model/
    ├── user.go           # User 模型 + bcrypt 密码方法 + 数据库查询
    └── response.go       # 统一响应结构体 + Success/Error 辅助函数
```

Go 模块名为 `go-api`，Go 1.18，依赖：gin, gorm (MySQL), go-redis/v8, golang-jwt/v4, x/crypto。

### PHP 项目结构

```
services/php/app/
├── application/admin/
│   ├── controller/       # Auth, Admin, Role, Menu（CRUD 控制器）
│   └── middleware/Auth.php  # 登录检查 + RBAC 权限查询
├── config/               # database, session, cache, app
├── route/route.php       # 所有路由定义（admin/* 前缀）
└── public/index.php      # TP5.1 入口
```

PHP 7.3 + ThinkPHP 5.1。数据库从环境变量读取配置（`getenv()`）。所有 `/admin` 路由通过 `Auth` 中间件（除 login/logout 外），中间件通过 `admin_role` + `role_menu` 获取管理员的 `authPaths`，各控制器自行检查具体按钮权限。

### 前端结构

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
    └── system/
        ├── admin.vue     # 管理员 CRUD
        ├── role.vue      # 角色 CRUD + el-tree 菜单分配
        └── menu.vue      # 菜单树形表格 CRUD
```

Vue2 + Element UI + Vue Router + Vuex。前端通过 Nginx 统一入口访问，无跨域问题。PHP 返回的菜单权限树驱动侧边栏渲染。
