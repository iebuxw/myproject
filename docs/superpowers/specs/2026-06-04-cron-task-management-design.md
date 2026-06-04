# 定时任务管理功能设计

## 概述

为 PHP 后台添加定时任务可视化管理功能。以数据库为单一数据源，通过智能合并策略同步 crontab，只管理 ThinkPHP console 命令，不覆盖非 think 条目。包含执行日志记录。

## 数据模型

### cron_task 表

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| name | VARCHAR(100) NOT NULL | 任务名称 |
| command | VARCHAR(200) NOT NULL | ThinkPHP 命令名，如 `clean_logs` |
| cron_expr | VARCHAR(50) NOT NULL | cron 表达式，如 `0 3 * * *` |
| status | TINYINT NOT NULL DEFAULT 1 | 1=启用 0=停用 |
| last_run_at | DATETIME NULL | 上次执行时间 |
| last_status | TINYINT NULL | 0=失败 1=成功 |
| remark | VARCHAR(255) DEFAULT '' | 备注 |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |
| updated_at | DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | |

索引：`UNIQUE uk_command (command)`

### cron_task_log 表

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| task_id | INT UNSIGNED NOT NULL | 关联 cron_task.id |
| command | VARCHAR(200) NOT NULL | 冗余存储，防任务删除后丢失 |
| status | TINYINT NOT NULL | 0=失败 1=成功 |
| output | TEXT | 命令输出 |
| duration | INT DEFAULT 0 | 执行耗时（秒） |
| started_at | DATETIME NOT NULL | 开始时间 |

索引：`INDEX idx_task_id (task_id)`、`INDEX idx_started_at (started_at)`

## 后端 API

### CronTask 控制器

路由前缀：`admin/cron_task/`

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | cron_task/list | 任务列表（分页 + 名称/状态搜索） |
| POST | cron_task/add | 新增任务 |
| PUT | cron_task/edit | 编辑任务 |
| DELETE | cron_task/delete | 删除任务 |
| PUT | cron_task/toggle | 快速启停 |
| POST | cron_task/run | 手动触发一次执行 |
| GET | cron_task/commands | 获取可用 think 命令列表 |

### CronTaskLog 控制器

路由前缀：`admin/cron_task_log/`

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | cron_task_log/list | 执行日志列表（按 task_id/状态/日期范围筛选） |
| DELETE | cron_task_log/delete | 删除日志 |

### 核心同步逻辑：syncCrontab()

```
1. exec('crontab -l') 获取当前 crontab 内容
2. 按 \n 分割，识别含 "php /var/www/html/think cron:run" 的行为系统管理行
3. 保留非系统行（不丢非 think 任务）
4. 从 cron_task 表查 status=1 的记录，生成新系统管理行：
   格式：<cron_expr> php /var/www/html/think cron:run <command>
5. 合并非系统行 + 新系统管理行，写回 crontab
```

### CronRun 包装命令

新增 ThinkPHP console 命令 `cron:run`，作为所有定时任务的执行入口：

```
php think cron:run <command>
```

职责：
1. 根据 command 参数查找 cron_task 记录
2. 记录开始时间
3. 调用实际命令（通过 Console::call()）
4. 捕获输出和退出码
5. 写入 cron_task_log（command、status、output、duration、started_at）
6. 更新 cron_task 的 last_run_at 和 last_status

### 命令发现：commands 接口

扫描 `application/command.php` 注册的命令列表，返回 `[{name, description}]`。排除 `cron:run` 自身。

### 参数校验

- 新增/编辑时验证 cron 表达式合法性（5 段格式，正则校验）
- 新增时验证 command 在已注册命令列表中存在
- command 字段唯一约束（一个命令只能有一个定时任务）

## 前端页面

### 定时任务列表页 `/system/cron-task`

文件：`frontend/src/views/system/cron-task.vue`

- **搜索栏**：名称关键词 + 状态下拉（全部/启用/停用）
- **表格列**：
  - 任务名称
  - 命令
  - cron 表达式
  - 状态（el-switch，调 toggle 接口）
  - 上次执行时间
  - 上次结果（el-tag 成功/失败）
  - 操作（编辑/删除/手动执行）
- **新增/编辑弹窗**：
  - 任务名称（input）
  - 命令（el-select，从 /cron_task/commands 获取选项）
  - cron 表达式（input + 5 个快捷按钮：每分钟/每小时/每天/每周/每月）
  - 备注（textarea）
- **权限**：v-auth 指令控制按钮显示

### 执行日志页 `/system/cron-task-log`

文件：`frontend/src/views/system/cron-task-log.vue`

- **搜索栏**：任务名称 + 状态 + 日期范围
- **表格列**：任务名称、命令、状态、耗时、开始时间、操作（查看输出）
- **查看输出**：弹窗显示完整输出内容

## 权限

菜单挂在"系统管理"目录（parent_id=1）下：

- 定时任务菜单（type=2）：`/system/cron-task`
  - 查询：`cron_task:list`
  - 新增：`cron_task:add`
  - 编辑：`cron_task:edit`
  - 删除：`cron_task:delete`
- 执行日志菜单（type=2）：`/system/cron-task-log`
  - 查询：`cron_task_log:list`
  - 删除：`cron_task_log:delete`

## 迁移策略

1. 新增迁移文件创建 `cron_task` 和 `cron_task_log` 表
2. 插入菜单 + 按钮权限 + 超管角色关联
3. 将现有 `clean_logs` 作为初始数据插入 `cron_task`（command='clean_logs', cron_expr='0 3 * * *', status=1）
4. 部署后首次调用 `syncCrontab()` 将 clean_logs 条目转为新格式（`php think cron:run clean_logs`），替换原始 crontab 中的旧格式条目
5. 更新 `services/php/crontab` 初始内容，新环境部署时直接使用新格式

## 文件清单

### 新增文件
- `services/mysql/migrations/20260604_add_cron_task.sql`
- `services/php/app/application/admin/controller/CronTask.php`
- `services/php/app/application/admin/controller/CronTaskLog.php`
- `services/php/app/application/command/CronRun.php`
- `frontend/src/views/system/cron-task.vue`
- `frontend/src/views/system/cron-task-log.vue`

### PHP exec 函数解禁

`services/php/conf/php.ini` 中 `disable_functions` 禁用了 `exec`、`shell_exec` 等。定时任务同步 crontab 需要调用系统命令，因此从禁用列表中移除 `exec` 和 `shell_exec`，其余保持禁用：

```
; 修改前
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,pcntl_exec,putenv

; 修改后
disable_functions = passthru,system,proc_open,popen,pcntl_exec,putenv
```

### 修改文件
- `services/php/app/route/route.php` — 添加路由
- `services/php/app/application/command.php` — 注册 CronRun 命令
- `frontend/src/router/index.js` — 添加页面路由
- `services/php/crontab` — 更新为新格式
- `services/php/conf/php.ini` — 从 disable_functions 中移除 exec、shell_exec
