# myproject

PHP + Go + Vue2 全栈项目，Docker Compose 一键部署。

## 环境要求

- Docker 20.10+
- Docker Compose 3.8+
- 端口 80、3306、6379 未被占用

## 快速开始

### 首次启动（构建镜像）

```bash
docker-compose up -d --build
```

### 关机后重新启动

```bash
docker-compose up -d
```

### 开发模式（修改代码后生效）

代码目录已 volume 挂载到容器，修改源码后**无需重建镜像**，restart 即可：

| 服务 | 代码路径 | 生效命令 |
|------|----------|----------|
| PHP | `services/php/app/` | `docker-compose restart php` |
| Go | `services/go/app/` | `docker-compose restart go` |
| Vue | `frontend/src/` | `npm run build && docker-compose restart nginx` |

> Go 容器启动时会比对源码 hash，只有代码实际变更时才重新编译，未改动则秒级启动。

### 停止所有服务

```bash
docker-compose down
```

### 查看运行状态

```bash
docker ps
```

## 服务地址

| 服务 | 地址 |
|------|------|
| 管理后台页面 | http://localhost |
| PHP 管理 API | http://localhost/admin |
| Go APP API | http://localhost/api/v1 |

## 默认账号

### 管理后台（PHP）

| 字段 | 值 |
|------|-----|
| 地址 | http://localhost |
| 用户名 | `admin` |
| 密码 | `password` |

### APP 用户（Go API）

| 字段 | 值 |
|------|-----|
| 手机号 | `13800000000` |
| 密码 | `password` |

## Go API 接口示例

### 登录

```bash
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"phone":"13800000000","password":"password"}'
```

响应：

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "access_token": "eyJhbGciOiJI...",
    "refresh_token": "eyJhbGciOiJI...",
    "expires_in": 7200
  }
}
```

### 获取个人信息

```bash
TOKEN="your_access_token"
curl http://localhost/api/v1/user/profile \
  -H "Authorization: Bearer $TOKEN"
```

### 修改个人信息

```bash
curl -X PUT http://localhost/api/v1/user/profile \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"nickname":"新昵称","email":"test@example.com","gender":1}'
```

### 刷新 Token

```bash
curl -X POST http://localhost/api/v1/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refresh_token":"your_refresh_token"}'
```

### 登出

```bash
curl -X POST http://localhost/api/v1/auth/logout \
  -H "Authorization: Bearer $TOKEN"
```

## 新环境部署

1. 克隆代码到新机器
2. 确保 `.env` 文件存在（包含数据库密码、JWT 密钥等配置）
3. 确保端口 80、3306、6379 未被占用
4. 执行 `docker-compose up -d --build`

## 架构

```
用户 → Nginx (:80)  ──→ /admin/*    → PHP-FPM (ThinkPHP 5.1)
                     ├→ /api/*     → Go (Gin)
                     └→ /*         → Vue 静态文件
```

认证方式：
- PHP 后台：Session + RBAC（admin → role → menu 权限树）
- Go API：JWT（access token 2h + refresh token 7d）

## 统一响应格式

```json
{"code": 0, "msg": "success", "data": {...}}
```

错误码：`0` 成功，`1001` 未登录，`1002` 参数错误，`1003` 用户名/密码错误，`1004` 用户不存在，`500` 服务端错误。
