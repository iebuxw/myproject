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
docker-compose restart php | go | nginx
```

**本地开发（不使用 Docker）**：
```bash
cd frontend && npm run serve           # 前端开发服务器（端口 8081，自动代理 /admin）
cd services/go/app && go run main.go   # Go API 开发
php -l services/php/app/...            # PHP 语法检查
docker-compose config --quiet           # 验证 docker-compose 配置
```

没有配置单元测试、lint 或 CI。

### 前端部署约束

Nginx 多阶段 Dockerfile 构建 Vue，前端产物打在镜像里。改前端后需 `docker-compose up -d --build`，Docker 缓存机制保证文件未变时不重新构建（秒过）。

## 架构概览

Nginx 对外暴露 :80（HTTP→HTTPS 重定向）和 :443（SSL），按路径前缀分发：

| 路径 | 目标 | 说明 |
|------|------|------|
| `/admin` | `php:9000` (PHP-FPM) | 后台管理，入口 `public/index.php` |
| `/api/` | `go:8080` (Gin) | APP API，前缀 `/api/v1` |
| `/swagger/` | `go:8080` (Gin) | Swagger API 文档 UI |
| `/` | `/usr/share/nginx/html` | Vue 静态资源 |

- PHP 代码卷挂载到容器，`APP_DEBUG=1` 时改代码即时生效（Opcache 实时刷新）；生产模式（`APP_DEBUG=0`）需 `docker-compose restart php` 使改动生效
- `APP_DEBUG=1` 同时跳过管理员登录验证码校验，切勿在生产环境开启
- Go/前端用多阶段 Dockerfile 构建，改代码后需 `docker-compose up -d --build`

### 认证体系

- **PHP 后台**：Session 驱动（Redis `session:` 前缀）。`Auth` 中间件从 Session 取 `admin_id`，通过 `admin_role` + `role_menu` 联表查权限路径存入 `$request->authPaths`。
- **Go API**：JWT 无状态认证。access token（2h）+ refresh token（7d），请求头 `Authorization: Bearer <token>`。登出时 token 加入 Redis 黑名单（`blacklist:` 前缀，2h TTL）。
- **维护模式**：Go JWT 中间件检查 Redis DB 0 的 `system:maintenance` 键，存在时拒绝所有 APP 请求。注意：PHP `DbBackup` 通过 Cache 写入 Redis DB 1，与 Go 读取的 DB 0 不同，两端维护标志互不可见。
- **Redis 分库**：Session（DB 0）、Go 黑名单/维护模式（DB 0）、PHP Cache/验证码（DB 1）。跨端共享 Redis 键必须注意 DB 一致性。

### 数据库

MySQL 5.7，迁移文件在 `services/mysql/migrations/`。**改数据库只新增迁移文件，禁止修改已有迁移**——为什么：已有环境 schema 状态不可预测，覆盖式迁移会丢数据。已有环境执行 `docker exec mysql bash -c "tr -d '\r' < /scripts/migrate.sh | bash"`。迁移 SQL 建议幂等（`IF NOT EXISTS`、`ON DUPLICATE KEY UPDATE`）。

### 超级管理员

id=1 为超级管理员，硬编码不可删除/禁用，跳过按钮级权限检查和验证码，拥有所有功能权限。新增管理员相关逻辑时需考虑此特殊角色。

### 术语约束（重要）

admin 和 user 是完全独立的身份体系，混淆会导致错误的数据库操作和界面歧义。

| 英文 | 中文 | 表 | 说明 |
|------|------|-----|------|
| **admin** | **管理员** | `admin` | 公司内部后台账号，PHP 管理后台 |
| **user** | **用户** | `user` | to-C 终端用户，Go API |

**必须遵守：**
- 后台界面（`/system/admin` 页面、菜单、按钮权限）中涉及 admin 实体的地方，一律用"管理员"，不得写"用户"
- 错误消息中指代 admin 时用"管理员"（如"管理员不存在"），指代 user 时用"用户"
- `username` 字段的标签翻译为"用户名"是可接受的（字段名，不是实体名）
- 写注释、提交消息、文档时同样遵守此区分

密码统一使用 bcrypt 哈希（PHP `password_hash`/`password_verify`，Go `golang.org/x/crypto/bcrypt`）。

### 统一响应格式

```json
{"code": 0, "msg": "success", "data": {...}}
```

错误码：常见的有 `0`=成功，`1001`=未登录/token无效，`1002`=参数错误，`1005`=已存在/重复，`1007`=无权限，`500`=服务端错误。其他不一一列举

### 文件上传

统一通过 `Attachment` 控制器（`POST /admin/attachment/upload`），不入库的上传（如头像）用 `Profile::avatar()`。**Go 端不做上传**——为什么：文件存储在 PHP 容器，Go 容器无法访问文件系统；MIME 校验等安全逻辑统一在 PHP 端处理，避免重复实现和标准不一致。

- **存储**：PHP 容器 `/var/www/html/uploads/{子目录}/`，Docker 卷 `upload_data` 持久化，Nginx `location /uploads/` 静态服务（30d 缓存，禁 PHP 执行）
- **子目录约定**：`avatars`（头像）、`attachments`（通用附件），新场景在 `entrypoint.sh` 加 `mkdir -p`
- **安全**：MIME 白名单校验（`finfo` 读取真实类型，非扩展名），上限 10MB（nginx `client_max_body_size` + php `upload_max_filesize` 对齐）
- **删除**：同时删 DB 记录和物理文件

### 详细结构

按技术栈拆分到 `.claude/rules/`，仅匹配对应路径时加载：
- `go.md` → `services/go/**`
- `php.md` → `services/php/**`
- `frontend.md` → `frontend/**`
