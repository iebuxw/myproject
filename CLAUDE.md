# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 启动与开发

```bash
# 一键构建并启动所有服务
docker-compose up -d --build

# 只改前端
docker-compose up nginx -d --build
# 只改 Go
docker-compose up go -d --build

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

### 前端部署约束

Nginx 多阶段 Dockerfile 构建 Vue，前端产物打在镜像里。改前端后需 `docker-compose up -d --build`，Docker 缓存机制保证文件未变时不重新构建（秒过）。

## 架构概览

### 路由分发（Nginx → PHP / Go / 静态文件）

Nginx 是唯一对外暴露端口的服务（:80），根据路径前缀分发：

| 路径 | 目标 | 说明 |
|------|------|------|
| `/admin` | `php:9000` (PHP-FPM) | 后台管理，入口 `public/index.php` |
| `/api/` | `go:8080` (Gin) | APP API，前缀 `/api/v1` |
| `/` | `/usr/share/nginx/html` | Vue 打包后的静态资源 |

Nginx 多阶段 Dockerfile 构建 Vue 产物打在镜像里，`docker-compose up -d --build` 时 Docker 缓存自动判断是否需要重新构建。PHP 代码通过卷挂载到容器（`application`/`config`/`route`/`public`），本地改代码即时生效。Go 代码同样使用多阶段 Dockerfile 构建，二进制打入镜像，改代码后需 `docker-compose up -d --build` 重新构建。Nginx 通过 fastcgi_pass 转发 PHP，SCRIPT_FILENAME 指向 `/var/www/html/public/index.php`。

### 认证体系

- **PHP 后台**：Session 驱动，存入 Redis（`session:` 前缀，3600s 过期）。`Auth` 中间件从 Session 取 `admin_id` 验证登录态，再通过 `admin_role` + `role_menu` 联表查出该管理员的所有权限路径存入 `$request->authPaths`。
- **Go API**：JWT 无状态认证。登录返回 access token（2h）+ refresh token（7d），请求头 `Authorization: Bearer <token>`。登出时将 token 加入 Redis 黑名单（`blacklist:` 前缀，2h TTL）。`JWTAuth` 中间件验证 token 有效性 + 检查黑名单。

### 数据库

MySQL 5.7，表结构和数据由 `services/mysql/migrations/` 迁移文件管理。`init.sql` 仅建库+迁移追踪表。**改数据库只新增迁移文件**，已有环境执行 `docker exec mysql bash -c "tr -d '\r' < /scripts/migrate.sh | bash"`。迁移 SQL 建议幂等（`IF NOT EXISTS`、`ON DUPLICATE KEY UPDATE`），防中途失败重跑出错。

- **admin** — 后台管理员（PHP 后台登录用）
- **user** — APP 普通用户（Go API 登录用）
- **role** — 角色
- **menu** — 菜单/权限（type: 1=目录 2=菜单 3=按钮）
- **admin_role** — 管理员↔角色多对多
- **role_menu** — 角色↔菜单多对多
- **dict_type / dict_data** — 字典类型 + 字典项
- **attachment** — 附件文件元数据（原始名、存储路径、大小、MIME、上传者）
- **login_log / operation_log** — 日志

两个用户表是独立的：admin 是后台管理员，user 是 to-C 的 APP 用户，两者不交叉。

### 术语约束（重要）

**两种身份必须严格区分，绝不可混用：**

| 英文 | 中文 | 表 | 说明 |
|------|------|-----|------|
| **admin** | **管理员** | `admin` | 公司内部后台账号，PHP 管理后台 |
| **user** | **用户** | `user` | to-C 终端用户，Go API |

**必须遵守：**
- 后台界面（`/system/admin` 页面、菜单、按钮权限）中涉及 admin 实体的地方，一律用"管理员"，不得写"用户"
- 错误消息中指代 admin 时用"管理员"（如"管理员不存在"），指代 user 时用"用户"
- `username` 字段的标签翻译为"用户名"是可接受的（字段名，不是实体名）
- 写注释、提交消息、文档时同样遵守此区分

密码统一使用 bcrypt 哈希（PHP 端 `password_hash`/`password_verify`，Go 端 `golang.org/x/crypto/bcrypt`）。

### 统一响应格式

PHP 和 Go 都返回相同结构：
```json
{"code": 0, "msg": "success", "data": {...}}
```

错误码约定：`0`=成功，`1001`=未登录/token无效，`1002`=参数错误，`1003`=用户名/密码错误，`1004`=管理员/用户不存在，`1005`=已存在/重复，`500`=服务端错误。

### 文件上传

统一通过 `Attachment` 控制器（`POST /admin/attachment/upload`），不入库的上传（如头像）用 `Profile::avatar()`。

- **存储**：PHP 容器 `/var/www/html/uploads/{子目录}/`，Docker 卷 `upload_data` 持久化，Nginx 通过 `location /uploads/` 静态服务（30d 缓存，禁 PHP 执行）
- **子目录约定**：`avatars`（头像）、`attachments`（通用附件），新场景在 `entrypoint.sh` 加 `mkdir -p`
- **安全**：MIME 白名单校验（`finfo` 读取真实类型，非扩展名），上限 10MB（nginx `client_max_body_size` + php `upload_max_filesize` 对齐）
- **删除**：同时删 DB 记录和物理文件
- **Go 端不做上传**，只接收 URL 字符串

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
│   ├── controller/       # Auth, Admin, Role, Menu, User, Attachment, DictType/DictData, LoginLog/OperationLog, Profile, Server
│   └── middleware/Auth.php  # 登录检查 + RBAC 权限查询
├── config/               # database, session, cache, app
├── route/route.php       # 所有路由定义（admin/* 前缀）
└── public/index.php      # TP5.1 入口
```

PHP 7.4 + ThinkPHP 5.1。数据库从环境变量读取配置（`getenv()`）。所有 `/admin` 路由通过 `Auth` 中间件（除 login/logout 外），中间件通过 `admin_role` + `role_menu` 获取管理员的 `authPaths`，各控制器自行检查具体按钮权限。

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

Vue2 + Element UI + Vue Router + Vuex。前端通过 Nginx 统一入口访问，无跨域问题。PHP 返回的菜单权限树驱动侧边栏渲染。
