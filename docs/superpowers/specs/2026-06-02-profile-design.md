# 个人中心功能设计文档

## 背景

后台管理员目前只能由超管通过 Admin 控制器编辑，没有自助管理个人信息的能力。需要添加"个人中心"功能，让每个管理员可以查看自己的信息、上传/更换头像、修改密码。admin 表已有 `avatar` 列但从未被使用。

## 功能范围

1. 查看当前管理员基本信息（用户名、头像、状态、创建时间）
2. 上传/更换头像（支持 jpg/png/gif，2MB 上限）
3. 修改密码（需验证原密码，新密码 ≥6 位）

## 方案选择

对比三种方案后选择 **独立 Profile 控制器 + Docker 共享卷存储头像**：

| 方案 | 优点 | 缺点 |
|------|------|------|
| A: 独立控制器 + 共享卷 | 关注点分离、Nginx 直接伺服静态文件高性能 | 需共享卷配置 |
| B: Auth 扩展 + Base64 存 DB | 零基础设施变更 | DB 存大文本影响性能、Base64 膨胀 |
| C: 独立控制器 + PHP 代理输出 | 无需共享卷 | 每次头像请求经 PHP 处理，性能差 |

## 后端设计

### Profile 控制器

路径：`services/php/app/application/admin/controller/Profile.php`

| 方法 | HTTP | 路径 | 职责 |
|------|------|------|------|
| `read()` | GET | `/admin/profile` | 返回当前管理员信息 |
| `avatar()` | POST | `/admin/profile/avatar` | 上传头像，存共享卷，删旧文件，更新 DB |
| `password()` | PUT | `/admin/profile/password` | 验证原密码，bcrypt 更新新密码 |
| `update()` | PUT | `/admin/profile` | 预留通用更新接口 |

身份获取：通过 `$request->adminId` / `$request->admin`（Auth 中间件注入）。无需 RBAC 权限检查——每个管理员只能操作自己的数据。

头像文件名格式：`{adminId}_{timestamp}.{ext}`，ThinkPHP `move()` 自动追加原始扩展名。上传新头像时 `@unlink` 删除旧文件。

### 路由

在认证路由组内添加，受 Auth + OperationLog 中间件保护：

```
GET  admin/profile          → Profile/read
POST admin/profile/avatar   → Profile/avatar
PUT  admin/profile/password → Profile/password
PUT  admin/profile          → Profile/update
```

### 操作日志

OperationLog `$moduleMap` 添加 `profile => 个人中心`。`$sensitiveFields` 已包含 `old_password`/`new_password`，密码修改自动脱敏。

## 基础设施设计

### Docker 共享卷

`docker-compose.yml` 添加命名卷 `upload_data`：
- PHP 容器：`upload_data:/var/www/html/uploads`（读写）
- Nginx 容器：`upload_data:/uploads:ro`（只读）

PHP 写入 `/var/www/html/uploads/avatars/1_xxx.jpg`，Nginx 从 `/uploads/avatars/1_xxx.jpg` 读取。

### Nginx 配置

`default.conf` HTTPS server 块中添加：

```nginx
location /uploads/ {
    alias /uploads/;
    expires 30d;
    add_header Cache-Control "public, no-transform";
}
```

## 前端设计

### 个人中心页面

路径：`frontend/src/views/profile/index.vue`

两个 el-card 区域：
- **基本信息卡片**：用户名（只读）+ el-avatar 64px 展示 + el-upload 更换按钮
- **修改密码卡片**：原密码 + 新密码 + 确认密码，el-form rules 验证，成功后自动登出跳转登录页

头像上传：`el-upload` + `http-request` 自定义 → FormData + multipart/form-data，成功后 dispatch `getInfo` 刷新 store。

### 布局入口

`layout/index.vue` 顶栏下拉菜单：
- 添加"个人中心"选项（`divided` 分隔）
- 触发区域改为 el-avatar(28px) + 用户名
- `handleCommand` 添加 `profile` 命令跳转 `/profile`

### 路由

`router/index.js` Layout children 添加 `/profile` 路由，不在菜单权限树中，所有管理员可访问。

## 改动文件清单

| 文件 | 操作 |
|------|------|
| `docker-compose.yml` | 修改：添加 upload_data 卷 |
| `services/nginx/conf/default.conf` | 修改：添加 /uploads/ location |
| `services/php/app/application/admin/controller/Profile.php` | 新建 |
| `services/php/app/route/route.php` | 修改：添加 4 条路由 |
| `services/php/app/application/admin/middleware/OperationLog.php` | 修改：$moduleMap 添加 profile |
| `frontend/src/views/profile/index.vue` | 新建 |
| `frontend/src/router/index.js` | 修改：添加 /profile 路由 |
| `frontend/src/layout/index.vue` | 修改：下拉菜单 + 头像 |
