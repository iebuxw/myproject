# 速查手册

日常开发操作备忘。

## 启动 & 停止

```bash
# 首次启动（构建镜像，3-5 分钟）
docker-compose up -d --build

# 日常启动（已有镜像，几秒）
docker-compose up -d

# 停止所有服务（MySQL + Redis 数据均保留）
docker-compose down

# ⚠️ 摧毁一切包括数据库（非必要不用）
docker-compose down -v

# 查看运行状态
docker ps

# 查看日志
docker-compose logs -f php
docker-compose logs -f go
docker-compose logs -f nginx
```

## 改代码后如何生效

| 改了什么 | 怎么生效 |
|----------|----------|
| PHP（`services/php/app/`） | 无需操作，即时生效 |
| Go（`services/go/app/`） | `docker-compose restart go` |
| Vue（`frontend/src/`） | `cd frontend && npm run build`，刷新浏览器 |
| 数据库（加表/加字段/改数据） | 写迁移文件后执行 `docker exec mysql bash -c "tr -d '\r' < /scripts/migrate.sh | bash"` |

## 服务地址

| 服务 | 地址 |
|------|------|
| 管理后台页面 | http://localhost |
| PHP 管理 API | http://localhost/admin |
| Go API | http://localhost/api/v1 |

## 默认账号

| 角色 | 账号 | 密码 | 哪里登录 |
|------|------|------|----------|
| 超级管理员 | `admin` | `123456` | http://localhost 后台 |
| 测试用户 | `13800000000` | `123456` | Go API `/api/v1/auth/login` |

## Go API 速查

```bash
# 登录
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"phone":"13800000000","password":"123456"}'

# 获取个人信息
curl http://localhost/api/v1/user/profile \
  -H "Authorization: Bearer <access_token>"

# 修改个人信息
curl -X PUT http://localhost/api/v1/user/profile \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <access_token>" \
  -d '{"nickname":"新昵称","email":"test@example.com","gender":1}'

# 刷新 Token
curl -X POST http://localhost/api/v1/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refresh_token":"<refresh_token>"}'

# 登出
curl -X POST http://localhost/api/v1/auth/logout \
  -H "Authorization: Bearer <access_token>"
```

## 端口被占用（Windows）

```powershell
# 查看端口占用
netstat -ano | findstr :80
netstat -ano | findstr :3306
netstat -ano | findstr :6379

# 杀进程（替换 PID）
taskkill /PID <PID> /F
```

## 数据库

```bash
# 进入 MySQL 交互式命令行
docker exec -it mysql mysql -uroot -p"root123" myproject --default-character-set=utf8mb4

# 直接执行一条 SQL
docker exec mysql mysql -uroot -p"root123" myproject -e "SELECT * FROM user;"

# 解决交互式命令中文乱码
SET NAMES utf8mb4;
```

### 数据库迁移（加字段/加表/改数据）

数据库变更统一通过迁移文件管理，**不改 init.sql**。migrate.sh 自动追踪已执行过的文件，不会重复跑。

#### 第一步：创建迁移文件

在 `services/mysql/migrations/` 下新建 SQL 文件，按日期+描述命名，例如 `20260601_add_birthday.sql`：

```sql
ALTER TABLE user ADD COLUMN birthday DATE DEFAULT NULL AFTER gender;
ALTER TABLE user ADD COLUMN address VARCHAR(255) DEFAULT '' AFTER email;
```

#### 第二步：执行迁移

```bash
docker exec mysql bash -c "tr -d '\r' < /scripts/migrate.sh | bash"
```

> Windows 下迁移脚本有换行符问题，用 `tr -d '\r'` 处理。Linux/Mac 下直接 `docker exec mysql bash /scripts/migrate.sh` 即可。

> **千万不要** `docker-compose down -v`，会把 MySQL 数据卷整个删掉，用户数据全丢。

## 容器内排查

```bash
# 进 PHP 容器
docker exec -it php sh

# 进 Go 容器
docker exec -it go sh

# 查看 Go 编译日志
docker logs go
```

## 新环境部署

1. 克隆代码
2. 确保 `.env` 文件存在且配置正确
3. 确保端口 80、3306、6379 未被占用
4. `docker-compose up -d --build`（首次启动自动执行数据库迁移）

## 统一响应格式

```json
{"code": 0, "msg": "success", "data": { ... }}
```

| code | 含义 |
|------|------|
| 0 | 成功 |
| 1001 | 未登录 / Token 无效 |
| 1002 | 参数错误 |
| 1003 | 用户名或密码错误 |
| 1004 | 管理员/用户不存在 |
| 500 | 服务端错误 |
