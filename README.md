# MyProject

全栈项目实战 —— Docker 容器化部署，PHP + Vue2 后台管理系统，Go + Gin 高性能 RESTful API 服务。

> 本项目由 **Claude Code**（AI 编程助手）辅助开发，串联本人Docker+PHP+Golang+Vue技术栈的练手项目，零手写代码。

## 架构设计

```
                    ┌──────────────────────────────────┐
                    │          Nginx (:80)              │
                    │  静态资源 · 反向代理 · 路由分发      │
                    └──────┬──────┬──────┬──────────────┘
                           │      │      │
                 /         │   /admin   │    /api/v1  /swagger/
           ┌───────────┐   │      ┌────┴────┐      ┌──────────────┐
           │ Vue 静态页  │   │      │ PHP-FPM │      │   Go (Gin)   │
           │ (Element   │   │      │ (TP5.1) │      │   高性能API    │
           │  UI 后台)  │   │      └───┬─────┘      └──────┬───────┘
           └───────────┘   │          │                    │
                           │     ┌────┴────┐          ┌───┴───┐
                           │     │  Redis  │          │ Redis │
                           │     │ Session │          │ JWT   │
                           │     │ CAPTCHA │          │ 黑名单 │
                           │     └────┬────┘          └───┬───┘
                           │          │               ┌───┴───┐
                           │          │               │Swagger│
                           │          │               │  Docs │
                           │          │               └───┬───┘
                           │     ┌────┴──────────────────┴───┐
                           │     │         MySQL 5.7         │
                           │     │  admin / user / role /    │
                           │     │  menu / admin_role /      │
                           │     │  role_menu / login_log /  │
                           │     │  operation_log             │
                           │     └───────────────────────────┘
```

**设计理念：关注点分离**

| 服务 | 职责 | 技术选型理由 |
|------|------|-------------|
| **PHP + Vue2** | 内部后台管理 | PHP 生态成熟的 ThinkPHP，Vue2 + Element UI 快速搭建 CRUD 界面 |
| **Go + Gin** | 对外开放 API | Go 高并发、低内存，Gin 性能接近原生 HTTP，适合 to-C 场景 |
| **Nginx** | 统一网关 | 路径分发、静态资源、负载均衡，对外只暴露 80 端口 |
| **MySQL** | 持久化存储 | 关系型数据库，8 表支撑 RBAC 权限 + 日志审计体系 |
| **Redis** | 缓存 / 会话 | PHP Session + CAPTCHA 验证码存储，Go JWT 黑名单，TTL 自动过期 |

## 技术栈

| 层级 | 技术 | 版本 |
|------|------|------|
| 后台框架 | ThinkPHP | 5.1 |
| 后台语言 | PHP | 7.4-fpm |
| API 框架 | Gin + GORM | Go 1.18 |
| API 文档 | swaggo/swag | 自动生成 Swagger UI |
| 前端 | Vue2 + Element UI + Vuex + Vue Router | — |
| 数据库 | MySQL | 5.7 |
| 缓存 | Redis | 3 (128MB / allkeys-lru) |
| Web 服务器 | Nginx | 1.24-alpine |
| Excel 处理 | PhpSpreadsheet | ^1.29 |
| 容器编排 | Docker Compose | 3.8 |

## 核心亮点

### 1. RBAC 权限体系（PHP 后台）

```
管理员 ──多对多──▶ 角色 ──多对多──▶ 菜单权限
                                ├── 目录 (type=1)
                                ├── 菜单 (type=2)
                                └── 按钮 (type=3)
```

- **三级粒度**：目录可见性 → 页面访问 → 按钮操作，精确到接口级
- **树形菜单**：递归构建权限树，后端返回结构直接驱动侧边栏渲染
- **中间件拦截**：Auth 中间件查 `admin_role` + `role_menu` 联表，注入 `authPaths`，控制器自行校验按钮权限
- **Session + Redis**：PHP Session 存入 Redis，TTL 3600s，分布式无状态

### 2. JWT 双 Token 认证（Go API）

| Token | 有效期 | 用途 |
|-------|--------|------|
| access_token | 2 小时 | 业务请求鉴权 |
| refresh_token | 7 天 | 无感刷新 access_token |

- **无状态设计**：不依赖 Session，适合 APP 端水平扩展
- **黑名单机制**：登出后 access_token 加入 Redis 黑名单（TTL 同步剩余有效期）
- **Bearer 标准**：`Authorization: Bearer <token>`
- **密码安全**：bcrypt 哈希存储，`golang.org/x/crypto/bcrypt`

### 3. 登录验证码

- **GD 生成**：4 位随机字符验证码，Base64 图片返回前端
- **Redis 存储**：验证码键值存入 Redis，TTL 300s 自动过期
- **防暴力破解**：登录接口必须携带 `captcha_key` + `captcha_code`

### 4. 日志审计

**登录日志**：
- 自动记录每次登录尝试（成功/失败），含账号、IP、User-Agent、失败原因
- 前端支持日期范围、状态筛选，分页浏览

**操作日志**：
- OperationLog 中间件自动拦截 POST/PUT/DELETE 请求，记录操作人、模块、动作、参数
- 密码字段自动脱敏为 `******`
- 路由自动映射为中文模块名（admin→管理员管理，role→角色管理，…）
- 前端支持模块/用户名/日期筛选，详情弹窗展示 JSON 参数

### 5. 用户导入导出

- **导出**：按搜索条件生成 `.xlsx` 文件，含手机号/昵称/邮箱/性别/状态/创建时间
- **导入**：上传 `.xlsx/.xls`，跳过已存在手机号，默认密码 `123456`（bcrypt 哈希）
- **前端**：导出按钮直接下载，导入支持拖拽上传

### 6. Swagger API 文档

- **自动生成**：Go Docker 构建时执行 `swag init`，从注解生成 OpenAPI 规范
- **在线浏览**：`http://localhost/swagger/index.html`，Nginx 代理到 Go 服务
- **Bearer 认证**：注解使用 `@Security Bearer`，关联全局 SecurityDefinition

### 7. Docker 容器化

- **一键部署**：`docker-compose up -d --build`，5 个服务自动编排
- **多阶段构建**：Nginx 镜像（node 编译 Vue → nginx 托管）、Go 镜像（golang 编译 + swag init → alpine 运行），最终镜像仅含产物
- **开发热加载**：PHP 代码通过卷挂载即时生效
- **环境隔离**：数据库密码、JWT 密钥等敏感配置通过 `.env` 注入
- **数据库迁移**：MySQL 首次启动自动执行幂等迁移脚本，后续新增迁移文件即可

### 8. RESTful API 设计

**Go API**：

```
POST   /api/v1/auth/login      # 登录
POST   /api/v1/auth/refresh    # 刷新 Token
POST   /api/v1/auth/logout     # 登出
GET    /api/v1/user/profile    # 获取个人信息
PUT    /api/v1/user/profile    # 修改个人信息
GET    /swagger/index.html     # Swagger API 文档
```

**PHP 后台 API**：

```
GET    /admin/auth/captcha         # 获取验证码
POST   /admin/auth/login           # 登录
POST   /admin/auth/logout          # 登出
GET    /admin/auth/info            # 获取管理员信息
GET    /admin/admin/list           # 管理员列表
POST   /admin/admin/add            # 新增管理员
PUT    /admin/admin/edit           # 编辑管理员
DELETE /admin/admin/delete         # 删除管理员
GET    /admin/role/list            # 角色列表
POST   /admin/role/add             # 新增角色
PUT    /admin/role/edit            # 编辑角色
DELETE /admin/role/delete          # 删除角色
GET    /admin/user/list            # 用户列表
POST   /admin/user/add             # 新增用户
PUT    /admin/user/edit            # 编辑用户
DELETE /admin/user/delete          # 删除用户
GET    /admin/user/export          # 导出用户 Excel
POST   /admin/user/import          # 导入用户 Excel
GET    /admin/menu/list            # 菜单列表
POST   /admin/menu/add             # 新增菜单
PUT    /admin/menu/edit            # 编辑菜单
DELETE /admin/menu/delete          # 删除菜单
GET    /admin/login_log/list       # 登录日志列表
DELETE /admin/login_log/delete     # 删除登录日志
GET    /admin/operation_log/list   # 操作日志列表
DELETE /admin/operation_log/delete # 删除操作日志
```

- **统一响应格式**：`{"code": 0, "msg": "success", "data": {...}}`
- **语义化错误码**：`0` 成功 / `1001` 未登录 / `1002` 参数错误 / `1003` 认证失败 / `500` 服务端错误
- **幂等设计**：迁移 SQL 全量 `IF NOT EXISTS` / `ON DUPLICATE KEY UPDATE`，可重复执行

## 快速开始

```bash
# 1. 克隆项目
git clone <repo-url> && cd myproject

# 2. 确保 .env 文件存在（数据库密码、JWT 密钥等）
# 3. 确保端口 80、3306、6379 未被占用
# 4. 一键构建并启动
docker-compose up -d --build
```

## 服务地址

| 服务 | 地址 | 说明 |
|------|------|------|
| 管理后台 | http://localhost | Vue2 + Element UI |
| PHP API | http://localhost/admin | ThinkPHP 5.1 |
| Go API | http://localhost/api/v1 | Gin + GORM |
| Swagger 文档 | http://localhost/swagger/index.html | Go API 在线文档 |

## 默认账号

| 角色 | 账号 | 密码 | 说明 |
|------|------|------|------|
| 超级管理员 | `admin` | `123456` | PHP 后台，拥有全部权限 |
| 测试用户 | `13800000000` | `123456` | APP 用户，Go API 登录 |

## 数据库设计

8 张表，严格区分 **管理员（内部）** 与 **用户（to-C）** 两个体系：

```
admin ──MM── role ──MM── menu
user (独立，Go API 管理)
login_log      (登录日志)
operation_log  (操作日志，关联 admin)
```

| 表 | 说明 | 关键字段 |
|----|------|----------|
| `admin` | 后台管理员 | username, password(bcrypt), status |
| `user` | APP 用户 | phone, password(bcrypt), nickname, email, gender |
| `role` | 角色 | name, description |
| `menu` | 菜单/权限 | parent_id, name, path, type(1目录2菜单3按钮) |
| `admin_role` | 管理员↔角色 | admin_id, role_id |
| `role_menu` | 角色↔菜单 | role_id, menu_id |
| `login_log` | 登录日志 | username, ip, user_agent, status, message |
| `operation_log` | 操作日志 | admin_id, username, module, action, method, url, params, ip |

## API 示例（Go 服务）

```bash
# 登录
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"phone":"13800000000","password":"123456"}'

# 响应
# {"code":0,"msg":"success","data":{"access_token":"eyJ...","refresh_token":"eyJ...","expires_in":7200}}

# 获取个人信息
curl http://localhost/api/v1/user/profile \
  -H "Authorization: Bearer <access_token>"

# 修改个人信息
curl -X PUT http://localhost/api/v1/user/profile \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <access_token>" \
  -d '{"nickname":"新昵称","email":"test@example.com","gender":1}'
```

## API 示例（PHP 后台）

```bash
# 获取验证码
curl http://localhost/admin/auth/captcha

# 登录（需验证码）
curl -X POST http://localhost/admin/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"123456","captcha_key":"xxx","captcha_code":"abcd"}'

# 导出用户 Excel
curl http://localhost/admin/user/export -b "PHPSESSID=<session_id>"

# 导入用户 Excel
curl -X POST http://localhost/admin/user/import \
  -b "PHPSESSID=<session_id>" \
  -F "file=@users.xlsx"
```

## 项目结构

```
myproject/
├── frontend/src/                 # Vue2 前端
│   ├── api/                      # axios 封装 + 拦截器
│   ├── router/                   # 路由表 + beforeEach 守卫
│   ├── store/                    # Vuex 状态管理（含验证码）
│   ├── layout/                   # 主布局（侧边栏 + 顶栏）
│   ├── components/               # 公共组件（Sidebar）
│   └── views/
│       ├── login/index.vue       # 登录页（含验证码）
│       ├── dashboard/index.vue
│       ├── user/index.vue        # 用户管理（含导入导出）
│       ├── system/               # 管理员 / 角色 / 菜单管理
│       └── log/                  # 登录日志 / 操作日志
├── services/
│   ├── nginx/                    # Nginx 配置 + 多阶段 Dockerfile
│   ├── php/app/
│   │   ├── application/admin/
│   │   │   ├── controller/       # Auth, Admin, Role, Menu, User, LoginLog, OperationLog
│   │   │   └── middleware/       # Auth 中间件（RBAC）+ OperationLog 中间件
│   │   ├── config/               # 数据库/缓存/Session 配置
│   │   └── route/                # 路由定义（含中间件路由组）
│   ├── go/app/
│   │   ├── handler/              # auth.go, user.go
│   │   ├── middleware/           # JWT 中间件
│   │   ├── model/                # User 模型 + Response 结构体
│   │   ├── router/               # Gin 路由 + CORS + Swagger
│   │   ├── config/               # 环境变量读取
│   │   └── docs/                 # swag 自动生成的 Swagger 文档
│   ├── mysql/
│   │   ├── init/                 # init.sql（建库 + 迁移追踪表）
│   │   └── migrations/           # 时间戳迁移文件（幂等）
│   └── redis/
│       └── redis.conf            # 自定义配置（128MB / allkeys-lru）
├── docker-compose.yml            # 5 服务编排
├── .env                          # 环境变量（不入库）
└── CLAUDE.md                     # AI 编程助手指令
```

## 开发指南

| 服务 | 代码路径 | 修改后生效方式 |
|------|----------|---------------|
| PHP | `services/php/app/` | 即时生效（volume 挂载） |
| Go | `services/go/app/` | `docker-compose up go -d --build`（多阶段构建） |
| Vue | `frontend/src/` | `docker-compose up nginx -d --build`（多阶段构建） |

## 许可

MIT
