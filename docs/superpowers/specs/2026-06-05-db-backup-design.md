# 数据库备份功能设计

## 概述

为 PHP 后台添加数据库备份管理功能。使用 mysqldump + gzip 备份 MySQL 业务库，支持手动/定时备份、下载、恢复（恢复前自动快照）、删除。备份记录存入 `db_backup` 表，可追溯。备份文件存储在 web 根目录外，下载需鉴权。

## 恢复安全

1. **维护模式锁定**：恢复前在 Redis 设 `system:maintenance` 标志（EX 600 自动过期兜底），PHP 和 Go 两端中间件都检查此标志，非超管/所有用户请求一律拒绝，恢复完成后清除
2. **超管权限校验**：恢复接口额外检查当前管理员是否为超级管理员（role_id=1）
3. **二次确认**：前端恢复弹窗需输入"确认恢复"才可提交

## 数据模型

### db_backup 表

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| filename | VARCHAR(255) NOT NULL | 文件名，如 `myproject_20260605_143025.sql.gz` |
| file_size | BIGINT UNSIGNED NOT NULL DEFAULT 0 | 文件大小（字节） |
| trigger_type | TINYINT NOT NULL DEFAULT 1 | 1=手动 2=定时 |
| status | TINYINT NOT NULL DEFAULT 1 | 1=成功 0=失败 |
| is_snapshot | TINYINT NOT NULL DEFAULT 0 | 0=常规备份 1=恢复前自动快照 |
| remark | VARCHAR(500) DEFAULT '' | 备注（失败时记录错误信息） |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

索引：`INDEX idx_created_at (created_at)`、`INDEX idx_is_snapshot (is_snapshot)`

不设 `updated_at`——备份记录只有创建，不会修改。

## 命令类

### BackupDb 命令

```
php think backup_db
```

执行流程：
1. 生成文件名 `myproject_YYYYMMDD_HHMMSS.sql.gz`
2. 通过 `shell_exec` 执行 `mysqldump -h{host} -u{user} -p{pass} --single-transaction {db} | gzip > {path}`
3. 获取文件大小
4. 写入 `db_backup` 记录（trigger_type=2 由 CronRun 调用时；手动触发时由控制器更新为 1）
5. 记录日志 `Log::notice()`

### CleanBackup 命令

```
php think clean_backup
```

根据 `system_config` 中的 `db_backup_keep_days` 配置（默认 30 天），清理过期备份文件和记录。

## 后端 API

### DbBackup 控制器

路由前缀：`admin/db_backup/`

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | db_backup/list | 备份列表（分页 + 日期范围筛选） |
| POST | db_backup/add | 手动备份：异步执行，立即返回 |
| POST | db_backup/restore | 恢复：先自动快照，再导入 |
| GET | db_backup/download | 流式下载 .sql.gz 文件（鉴权后） |
| DELETE | db_backup/delete | 删文件 + 删 db_backup 记录 |

### 手动备份异步化

`db_backup/add` 接口不等待备份完成：
1. 创建 `db_backup` 记录（status=0 表示进行中，remark='备份中...'）
2. 返回成功响应
3. 通过 `exec('nohup php /var/www/html/think backup_db > /dev/null 2>&1 &')` 后台执行
4. 命令执行完成后更新记录的 status、file_size、filename

定时备份由 CronRun 触发，不受 PHP 超时限制，无需异步。

### 恢复流程

1. **超管校验**：检查当前管理员 role_id=1，非超管返回无权限
2. **维护锁定**：Redis `SET system:maintenance 1 EX 600`（10 分钟自动过期兜底）
3. **查记录**：验证备份文件存在
4. **自动快照**：调用 `Console::call('backup_db')`，标记 is_snapshot=1，remark='恢复前自动快照'
5. **执行恢复**：`shell_exec('gunzip < {path} | mysql -h{host} -u{user} -p{pass} {db}')`
6. **解除锁定**：Redis `DEL system:maintenance`
7. 操作记录到 operation_log（中间件自动完成）

### PHP Auth 中间件变更

在 `Auth::handle()` 开头增加维护模式检查：
- 读 Redis `system:maintenance`
- 存在且当前管理员 role_id≠1 时，返回 `{'code': 1001, 'msg': '系统维护中'}`
- 超管不受影响

### Go JWT 中间件变更

在 `JWTAuth()` 中增加维护模式检查：
- 读 Redis `system:maintenance`
- 存在时返回 `{'code': 1001, 'msg': '系统维护中'}`，阻止所有 APP 用户请求
- 维护锁是全局的，Go 端无超管概念，维护期间全部拒绝

### 备份目录

备份文件存放在 `/var/www/backups/`（web 根目录 `/var/www/html/` 之外），无法通过 URL 直接访问。下载统一走控制器鉴权后流式输出。

## 前端页面

### 数据库备份页 `/system/db-backup`

文件：`frontend/src/views/system/db-backup.vue`

- **搜索栏**：日期范围（el-date-picker type="daterange"）
- **操作按钮**：新增备份
- **表格列**：
  - 文件名
  - 文件大小（格式化为 KB/MB）
  - 触发方式（el-tag：手动/定时）
  - 状态（el-tag：成功/失败/进行中）
  - 类型（el-tag：常规备份/恢复快照）
  - 创建时间
  - 操作（恢复/下载/删除）
- **新增备份**：点击后直接触发，提示"备份已在后台执行，请稍后刷新"
- **恢复**：红色按钮，弹窗需输入"确认恢复"才可提交
- **下载**：调用 API 获取文件流，浏览器下载
- **删除**：二次确认
- **权限**：v-auth 指令控制按钮显示

## 权限

菜单挂在"运维管理"目录下（与定时任务、日志配置同级）：

- 数据库备份菜单（type=2）：`/system/db-backup`
  - 查询：`db_backup:list`
  - 新增：`db_backup:add`
  - 恢复：`db_backup:restore`
  - 删除：`db_backup:delete`

## Docker 变更

### PHP Dockerfile

在 `apt-get install` 中添加 `default-mysql-client`（提供 mysqldump 和 mysql 命令）。

### entrypoint.sh

添加 `mkdir -p /var/www/backups`。

### docker-compose.yml

添加 `backup_data` 卷，挂载到 PHP 容器 `/var/www/backups`。

## 文件清单

### 新增文件
- `services/mysql/migrations/20260605_add_db_backup.sql`
- `services/php/app/application/command/BackupDb.php`
- `services/php/app/application/command/CleanBackup.php`
- `services/php/app/application/admin/controller/DbBackup.php`
- `frontend/src/views/system/db-backup.vue`

### 修改文件
- `services/php/app/route/route.php` — 添加路由
- `services/php/app/application/command.php` — 注册 BackupDb、CleanBackup 命令
- `services/php/app/application/admin/middleware/Auth.php` — 添加维护模式检查
- `services/go/app/middleware/jwt.go` — 添加维护模式检查
- `frontend/src/router/index.js` — 添加页面路由
- `services/php/Dockerfile` — 安装 default-mysql-client
- `services/php/entrypoint.sh` — 创建 /var/www/backups 目录
- `docker-compose.yml` — 添加 backup_data 卷
