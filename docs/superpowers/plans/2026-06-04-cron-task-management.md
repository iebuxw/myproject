# 定时任务管理功能实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 为 PHP 后台添加定时任务可视化管理，数据库驱动，智能合并同步 crontab，只管理 think 命令，带执行日志。

**Architecture:** 新增 `cron_task` 和 `cron_task_log` 两张表，CronTask/CronTaskLog 控制器提供 CRUD + 手动触发 + 命令发现 API，CronRun console 命令包装实际执行并记录日志，syncCrontab() 智能合并写回 crontab。前端两个页面：任务列表 + 执行日志。

**Tech Stack:** PHP 7.4 + ThinkPHP 5.1 + MySQL 5.7，Vue 2 + Element UI 2.15

---

### Task 1: 解禁 PHP exec 函数 + 更新 crontab 初始格式

**Files:**
- Modify: `services/php/conf/php.ini:312`
- Modify: `services/php/crontab`

- [ ] **Step 1: 修改 php.ini 的 disable_functions**

将 `services/php/conf/php.ini` 第 312 行从：
```
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,pcntl_exec,putenv
```
改为：
```
disable_functions = passthru,system,proc_open,popen,pcntl_exec,putenv
```

- [ ] **Step 2: 更新 crontab 为新格式**

将 `services/php/crontab` 内容从：
```
# 每天 3 点清理过期日志，保留天数由 system_config.log_retention_days 控制
0 3 * * * php /var/www/html/think clean_logs >> /var/www/html/runtime/clean_logs.log 2>&1
```
改为：
```
# 由定时任务管理系统维护，请勿手动修改含 cron:run 的行
0 3 * * * php /var/www/html/think cron:run clean_logs
```

- [ ] **Step 3: Commit**

```bash
git add services/php/conf/php.ini services/php/crontab
git commit -m "feat: 解禁 exec/shell_exec + crontab 改用 cron:run 格式"
```

---

### Task 2: 数据库迁移文件

**Files:**
- Create: `services/mysql/migrations/20260604_add_cron_task.sql`

- [ ] **Step 1: 编写迁移 SQL**

```sql
-- 定时任务表
CREATE TABLE IF NOT EXISTS `cron_task` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL COMMENT '任务名称',
    `command` VARCHAR(200) NOT NULL COMMENT 'ThinkPHP 命令名',
    `cron_expr` VARCHAR(50) NOT NULL COMMENT 'cron 表达式',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用',
    `last_run_at` DATETIME NULL COMMENT '上次执行时间',
    `last_status` TINYINT NULL COMMENT '0=失败 1=成功',
    `remark` VARCHAR(255) DEFAULT '' COMMENT '备注',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_command` (`command`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='定时任务';

-- 定时任务执行日志表
CREATE TABLE IF NOT EXISTS `cron_task_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `task_id` INT UNSIGNED NOT NULL COMMENT '关联 cron_task.id',
    `command` VARCHAR(200) NOT NULL COMMENT '命令名',
    `status` TINYINT NOT NULL COMMENT '0=失败 1=成功',
    `output` TEXT COMMENT '命令输出',
    `duration` INT DEFAULT 0 COMMENT '执行耗时(秒)',
    `started_at` DATETIME NOT NULL COMMENT '开始时间',
    INDEX `idx_task_id` (`task_id`),
    INDEX `idx_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='定时任务日志';

-- 初始数据：将现有 clean_logs 任务纳入管理
INSERT INTO `cron_task` (`name`, `command`, `cron_expr`, `status`, `remark`) VALUES
('清理过期日志', 'clean_logs', '0 3 * * *', 1, '每天3点清理操作日志和登录日志');

-- 菜单：定时任务（挂在"系统管理"目录 id=1 下，sort=6 排在通知公告之后）
INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `sort`, `type`) VALUES
(52, 1, '定时任务', '/system/cron-task', '', 6, 2),
(53, 52, '查询', 'cron_task:list', '', 1, 3),
(54, 52, '新增', 'cron_task:add', '', 2, 3),
(55, 52, '编辑', 'cron_task:edit', '', 3, 3),
(56, 52, '删除', 'cron_task:delete', '', 4, 3)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 菜单：执行日志（同样挂在"系统管理"目录下）
INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `sort`, `type`) VALUES
(57, 1, '执行日志', '/system/cron-task-log', '', 7, 2),
(58, 57, '查询', 'cron_task_log:list', '', 1, 3),
(59, 57, '删除', 'cron_task_log:delete', '', 2, 3)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 超管拥有新菜单权限
INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
(1,52),(1,53),(1,54),(1,55),(1,56),
(1,57),(1,58),(1,59)
ON DUPLICATE KEY UPDATE `id`=`id`;
```

- [ ] **Step 2: Commit**

```bash
git add services/mysql/migrations/20260604_add_cron_task.sql
git commit -m "feat: cron_task + cron_task_log 迁移文件"
```

---

### Task 3: CronRun 包装命令

**Files:**
- Create: `services/php/app/application/command/CronRun.php`
- Modify: `services/php/app/application/command.php`

- [ ] **Step 1: 创建 CronRun 命令**

```php
<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\console\InputArgument;
use think\facade\Log;

class CronRun extends Command
{
    protected function configure()
    {
        $this->setName('cron:run')
            ->addArgument('command_name', InputArgument::REQUIRED, '要执行的命令名')
            ->setDescription('定时任务包装器：执行指定命令并记录日志');
    }

    protected function execute(Input $input, Output $output)
    {
        $commandName = $input->getArgument('command_name');
        $task = Db::table('cron_task')->where('command', $commandName)->find();

        $taskId = $task ? $task['id'] : 0;
        $startedAt = time();
        $startedAtStr = date('Y-m-d H:i:s', $startedAt);

        ob_start();
        $exitCode = 0;
        try {
            $result = \think\Console::call($commandName);
            $resultOutput = trim($result->fetch());
        } catch (\Exception $e) {
            $resultOutput = $e->getMessage();
            $exitCode = 1;
        }
        $stdOutput = trim(ob_get_clean());
        if ($stdOutput) {
            $resultOutput = $resultOutput ? $resultOutput . "\n" . $stdOutput : $stdOutput;
        }

        $status = ($exitCode === 0) ? 1 : 0;
        $duration = time() - $startedAt;

        Db::table('cron_task_log')->insert([
            'task_id'    => $taskId,
            'command'    => $commandName,
            'status'     => $status,
            'output'     => $resultOutput ?: '',
            'duration'   => $duration,
            'started_at' => $startedAtStr,
        ]);

        if ($task) {
            Db::table('cron_task')->where('id', $task['id'])->update([
                'last_run_at'   => $startedAtStr,
                'last_status'   => $status,
            ]);
        }

        $logMsg = "[CronRun] {$commandName} " . ($status ? 'SUCCESS' : 'FAILED') . " ({$duration}s)";
        if ($status) {
            Log::info($logMsg);
        } else {
            Log::error($logMsg . ' ' . $resultOutput);
        }

        $output->writeln($resultOutput);
        return $status ? 0 : 1;
    }
}
```

- [ ] **Step 2: 注册命令**

修改 `services/php/app/application/command.php`：

```php
<?php
return [
    'app\command\CleanLogs',
    'app\command\CronRun',
];
```

- [ ] **Step 3: Commit**

```bash
git add services/php/app/application/command/CronRun.php services/php/app/application/command.php
git commit -m "feat: CronRun 包装命令，执行 think 命令并记录日志"
```

---

### Task 4: CronTask 控制器

**Files:**
- Create: `services/php/app/application/admin/controller/CronTask.php`
- Modify: `services/php/app/route/route.php`

- [ ] **Step 1: 创建 CronTask 控制器**

```php
<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;

class CronTask extends Controller
{
    private static function syncCrontab()
    {
        $current = '';
        exec('crontab -l 2>/dev/null', $lines, $ret);
        if ($ret === 0) {
            $current = implode("\n", $lines);
        }

        $preserved = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '#') === 0) {
                continue;
            }
            if (strpos($trimmed, 'php /var/www/html/think cron:run') !== false) {
                continue;
            }
            $preserved[] = $trimmed;
        }

        $tasks = Db::table('cron_task')->where('status', 1)->select();
        $managed = [];
        foreach ($tasks as $task) {
            $managed[] = "{$task['cron_expr']} php /var/www/html/think cron:run {$task['command']}";
        }

        $newContent = "# 由定时任务管理系统维护，请勿手动修改含 cron:run 的行\n";
        foreach ($managed as $m) {
            $newContent .= $m . "\n";
        }
        foreach ($preserved as $p) {
            $newContent .= $p . "\n";
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'crontab_');
        file_put_contents($tmpFile, $newContent);
        exec("crontab {$tmpFile} 2>&1", $out, $cr);
        unlink($tmpFile);

        return $cr === 0;
    }

    private static function getRegisteredCommands()
    {
        $commands = include app()->getAppPath() . 'command.php';
        $list = [];
        foreach ($commands as $class) {
            if ($class === 'app\command\CronRun') {
                continue;
            }
            $ref = new \ReflectionClass($class);
            $instance = $ref->newInstanceWithoutConstructor();
            $prop = $ref->getProperty('definition');
            $prop->setAccessible(true);
            $definition = $prop->getValue($instance);
            $name = $definition->getName();
            $desc = $definition->getDescription() ?: '';
            $list[] = ['name' => $name, 'description' => $desc];
        }
        return $list;
    }

    private static function validateCronExpr($expr)
    {
        $parts = preg_split('/\s+/', trim($expr));
        if (count($parts) !== 5) {
            return false;
        }
        foreach ($parts as $p) {
            if (!preg_match('/^[0-9,\-\*\/]+$/', $p)) {
                return false;
            }
        }
        return true;
    }

    // GET /admin/cron_task/list
    public function index()
    {
        $page    = input('get.page', 1);
        $limit   = input('get.limit', 10);
        $name    = input('get.name', '');
        $status  = input('get.status', -1);

        $query = Db::table('cron_task');

        if (!empty($name)) {
            $query->where('name', 'like', "%{$name}%");
        }
        if ($status != -1) {
            $query->where('status', (int)$status);
        }

        $total = $query->count();
        $list  = $query->order('id', 'asc')->page((int)$page, (int)$limit)->select();

        return json(['code' => 0, 'msg' => 'success', 'data' => [
            'list'  => $list,
            'total' => $total,
        ]]);
    }

    // GET /admin/cron_task/commands
    public function commands()
    {
        $list = self::getRegisteredCommands();
        return json(['code' => 0, 'msg' => 'success', 'data' => $list]);
    }

    // POST /admin/cron_task/add
    public function save()
    {
        $name     = input('post.name', '');
        $command  = input('post.command', '');
        $cronExpr = input('post.cron_expr', '');
        $remark   = input('post.remark', '');

        if (empty($name) || empty($command) || empty($cronExpr)) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        if (!self::validateCronExpr($cronExpr)) {
            return json(['code' => 1002, 'msg' => 'cron 表达式格式错误', 'data' => null]);
        }

        $registered = array_column(self::getRegisteredCommands(), 'name');
        if (!in_array($command, $registered)) {
            return json(['code' => 1002, 'msg' => '命令不存在', 'data' => null]);
        }

        $exists = Db::table('cron_task')->where('command', $command)->find();
        if ($exists) {
            return json(['code' => 1005, 'msg' => '该命令已配置定时任务', 'data' => null]);
        }

        Db::table('cron_task')->insert([
            'name'      => $name,
            'command'   => $command,
            'cron_expr' => $cronExpr,
            'remark'    => $remark,
        ]);

        self::syncCrontab();

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // PUT /admin/cron_task/edit
    public function update()
    {
        $id       = input('put.id', 0);
        $name     = input('put.name', '');
        $cronExpr = input('put.cron_expr', '');
        $remark   = input('put.remark', '');

        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $task = Db::table('cron_task')->where('id', $id)->find();
        if (!$task) {
            return json(['code' => 1004, 'msg' => '任务不存在', 'data' => null]);
        }

        $data = [];
        if (!empty($name)) {
            $data['name'] = $name;
        }
        if (!empty($cronExpr)) {
            if (!self::validateCronExpr($cronExpr)) {
                return json(['code' => 1002, 'msg' => 'cron 表达式格式错误', 'data' => null]);
            }
            $data['cron_expr'] = $cronExpr;
        }
        if ($remark !== null && $remark !== '') {
            $data['remark'] = $remark;
        }

        if (!empty($data)) {
            Db::table('cron_task')->where('id', $id)->update($data);
            self::syncCrontab();
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // DELETE /admin/cron_task/delete
    public function delete()
    {
        $id = input('delete.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        Db::table('cron_task_log')->where('task_id', $id)->delete();
        Db::table('cron_task')->where('id', $id)->delete();

        self::syncCrontab();

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // PUT /admin/cron_task/toggle
    public function toggle()
    {
        $id = input('put.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $task = Db::table('cron_task')->where('id', $id)->find();
        if (!$task) {
            return json(['code' => 1004, 'msg' => '任务不存在', 'data' => null]);
        }

        $newStatus = $task['status'] ? 0 : 1;
        Db::table('cron_task')->where('id', $id)->update(['status' => $newStatus]);

        self::syncCrontab();

        return json(['code' => 0, 'msg' => 'success', 'data' => ['status' => $newStatus]]);
    }

    // POST /admin/cron_task/run
    public function run()
    {
        $id = input('post.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $task = Db::table('cron_task')->where('id', $id)->find();
        if (!$task) {
            return json(['code' => 1004, 'msg' => '任务不存在', 'data' => null]);
        }

        $commandName = $task['command'];
        $startedAt = time();
        $startedAtStr = date('Y-m-d H:i:s', $startedAt);

        ob_start();
        $exitCode = 0;
        try {
            $result = \think\Console::call($commandName);
            $resultOutput = trim($result->fetch());
        } catch (\Exception $e) {
            $resultOutput = $e->getMessage();
            $exitCode = 1;
        }
        $stdOutput = trim(ob_get_clean());
        if ($stdOutput) {
            $resultOutput = $resultOutput ? $resultOutput . "\n" . $stdOutput : $stdOutput;
        }

        $status = ($exitCode === 0) ? 1 : 0;
        $duration = time() - $startedAt;

        Db::table('cron_task_log')->insert([
            'task_id'    => $id,
            'command'    => $commandName,
            'status'     => $status,
            'output'     => $resultOutput ?: '',
            'duration'   => $duration,
            'started_at' => $startedAtStr,
        ]);

        Db::table('cron_task')->where('id', $id)->update([
            'last_run_at'   => $startedAtStr,
            'last_status'   => $status,
        ]);

        return json(['code' => 0, 'msg' => 'success', 'data' => [
            'status'   => $status,
            'output'   => $resultOutput,
            'duration' => $duration,
        ]]);
    }

    // 暴露 syncCrontab 供外部调用
    public static function doSyncCrontab()
    {
        return self::syncCrontab();
    }
}
```

- [ ] **Step 2: 添加路由**

在 `services/php/app/route/route.php` 的路由 group 内，Profile 路由之前添加：

```php
    // CronTask
    Route::get('cron_task/list', 'admin/CronTask/index');
    Route::get('cron_task/commands', 'admin/CronTask/commands');
    Route::post('cron_task/add', 'admin/CronTask/save');
    Route::put('cron_task/edit', 'admin/CronTask/update');
    Route::delete('cron_task/delete', 'admin/CronTask/delete');
    Route::put('cron_task/toggle', 'admin/CronTask/toggle');
    Route::post('cron_task/run', 'admin/CronTask/run');

    // CronTaskLog
    Route::get('cron_task_log/list', 'admin/CronTaskLog/index');
    Route::delete('cron_task_log/delete', 'admin/CronTaskLog/delete');
```

- [ ] **Step 3: Commit**

```bash
git add services/php/app/application/admin/controller/CronTask.php services/php/app/route/route.php
git commit -m "feat: CronTask 控制器 + 路由"
```

---

### Task 5: CronTaskLog 控制器

**Files:**
- Create: `services/php/app/application/admin/controller/CronTaskLog.php`

- [ ] **Step 1: 创建 CronTaskLog 控制器**

```php
<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;

class CronTaskLog extends Controller
{
    // GET /admin/cron_task_log/list
    public function index()
    {
        $page      = input('get.page', 1);
        $limit     = input('get.limit', 10);
        $taskId    = input('get.task_id', 0);
        $status    = input('get.status', -1);
        $startDate = input('get.start_date', '');
        $endDate   = input('get.end_date', '');

        $query = Db::table('cron_task_log');

        if ($taskId > 0) {
            $query->where('task_id', $taskId);
        }
        if ($status != -1) {
            $query->where('status', (int)$status);
        }
        if (!empty($startDate)) {
            $query->where('started_at', '>=', $startDate . ' 00:00:00');
        }
        if (!empty($endDate)) {
            $query->where('started_at', '<=', $endDate . ' 23:59:59');
        }

        $total = $query->count();
        $list  = $query->order('id', 'desc')->page((int)$page, (int)$limit)->select();

        return json(['code' => 0, 'msg' => 'success', 'data' => [
            'list'  => $list,
            'total' => $total,
        ]]);
    }

    // DELETE /admin/cron_task_log/delete
    public function delete()
    {
        $id = input('delete.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        Db::table('cron_task_log')->where('id', $id)->delete();

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add services/php/app/application/admin/controller/CronTaskLog.php
git commit -m "feat: CronTaskLog 控制器"
```

---

### Task 6: 前端 - 定时任务列表页

**Files:**
- Create: `frontend/src/views/system/cron-task.vue`
- Modify: `frontend/src/router/index.js`

- [ ] **Step 1: 创建定时任务列表页**

```vue
<template>
  <div>
    <el-card>
      <div slot="header">
        <span>定时任务</span>
        <el-button v-auth="'cron_task:add'" type="primary" size="small" style="float:right" @click="handleAdd">新增</el-button>
      </div>

      <el-form :model="searchForm" inline size="small" style="margin-bottom:15px">
        <el-form-item label="名称">
          <el-input v-model="searchForm.name" placeholder="任务名称" clearable @keyup.enter.native="handleSearch" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width:120px">
            <el-option :value="1" label="启用" />
            <el-option :value="0" label="停用" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="list" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="name" label="任务名称" show-overflow-tooltip />
        <el-table-column prop="command" label="命令" width="140" />
        <el-table-column prop="cron_expr" label="Cron 表达式" width="140" />
        <el-table-column prop="status" label="状态" width="80">
          <template slot-scope="{row}">
            <el-switch
              v-auth="'cron_task:edit'"
              :value="row.status === 1"
              active-color="#13ce66"
              inactive-color="#ff4949"
              @change="handleToggle(row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="last_run_at" label="上次执行" width="170">
          <template slot-scope="{row}">{{ row.last_run_at || '-' }}</template>
        </el-table-column>
        <el-table-column prop="last_status" label="上次结果" width="90">
          <template slot-scope="{row}">
            <el-tag v-if="row.last_run_at" :type="row.last_status === 1 ? 'success' : 'danger'" size="small">
              {{ row.last_status === 1 ? '成功' : '失败' }}
            </el-tag>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" show-overflow-tooltip />
        <el-table-column label="操作" width="200">
          <template slot-scope="{row}">
            <el-button v-auth="'cron_task:edit'" type="text" @click="handleEdit(row)">编辑</el-button>
            <el-button type="text" @click="handleRun(row)">执行</el-button>
            <el-button v-auth="'cron_task:delete'" type="text" style="color:#f56c6c" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        style="margin-top:15px;text-align:right"
        background
        layout="total, prev, pager, next"
        :current-page="page"
        :page-size="limit"
        :total="total"
        @current-change="handlePageChange"
      />
    </el-card>

    <el-dialog :title="dialogTitle" :visible.sync="dialogVisible" width="550px">
      <el-form ref="form" :model="form" label-width="110px">
        <el-form-item label="任务名称" required>
          <el-input v-model="form.name" placeholder="请输入任务名称" />
        </el-form-item>
        <el-form-item label="命令" required>
          <el-select v-model="form.command" placeholder="请选择命令" style="width:100%" :disabled="!!form.id">
            <el-option v-for="c in commands" :key="c.name" :label="c.name + (c.description ? ' - ' + c.description : '')" :value="c.name" />
          </el-select>
        </el-form-item>
        <el-form-item label="Cron 表达式" required>
          <el-input v-model="form.cron_expr" placeholder="如 0 3 * * *" />
          <div style="margin-top:8px">
            <el-button size="mini" @click="form.cron_expr = '* * * * *'">每分钟</el-button>
            <el-button size="mini" @click="form.cron_expr = '0 * * * *'">每小时</el-button>
            <el-button size="mini" @click="form.cron_expr = '0 0 * * *'">每天</el-button>
            <el-button size="mini" @click="form.cron_expr = '0 0 * * 1'">每周</el-button>
            <el-button size="mini" @click="form.cron_expr = '0 0 1 * *'">每月</el-button>
          </div>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
      </el-form>
      <span slot="footer">
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit">确定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import request from '@/api'

export default {
  name: 'SystemCronTask',
  data() {
    return {
      list: [],
      page: 1,
      limit: 10,
      total: 0,
      searchForm: { name: '', status: undefined },
      dialogVisible: false,
      dialogTitle: '',
      form: { id: 0, name: '', command: '', cron_expr: '', remark: '' },
      commands: []
    }
  },
  created() {
    this.fetchList()
    this.fetchCommands()
  },
  methods: {
    async fetchList() {
      try {
        const params = { page: this.page, limit: this.limit }
        if (this.searchForm.name) params.name = this.searchForm.name
        if (this.searchForm.status !== undefined && this.searchForm.status !== '') params.status = this.searchForm.status
        const res = await request.get('/cron_task/list', { params })
        if (res.code === 0) {
          this.list = res.data.list
          this.total = res.data.total
        }
      } catch (e) {}
    },
    async fetchCommands() {
      try {
        const res = await request.get('/cron_task/commands')
        if (res.code === 0) {
          this.commands = res.data
        }
      } catch (e) {}
    },
    handleSearch() {
      this.page = 1
      this.fetchList()
    },
    handleReset() {
      this.searchForm = { name: '', status: undefined }
      this.page = 1
      this.fetchList()
    },
    handlePageChange(page) {
      this.page = page
      this.fetchList()
    },
    handleAdd() {
      this.dialogTitle = '新增任务'
      this.form = { id: 0, name: '', command: '', cron_expr: '', remark: '' }
      this.dialogVisible = true
    },
    handleEdit(row) {
      this.dialogTitle = '编辑任务'
      this.form = { id: row.id, name: row.name, command: row.command, cron_expr: row.cron_expr, remark: row.remark || '' }
      this.dialogVisible = true
    },
    async handleToggle(row) {
      try {
        const res = await request.put('/cron_task/toggle', { id: row.id })
        if (res.code === 0) {
          this.$message.success(res.data.status === 1 ? '已启用' : '已停用')
          this.fetchList()
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {}
    },
    async handleRun(row) {
      try {
        const res = await request.post('/cron_task/run', { id: row.id })
        if (res.code === 0) {
          this.$message.success('执行完成：' + (res.data.status === 1 ? '成功' : '失败') + '，耗时 ' + res.data.duration + 's')
          this.fetchList()
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {}
    },
    handleDelete(row) {
      this.$confirm('删除任务会同时删除其执行日志，确定删除吗？', '提示', { type: 'warning' }).then(async () => {
        try {
          const res = await request.delete('/cron_task/delete', { data: { id: row.id } })
          if (res.code === 0) {
            this.$message.success('删除成功')
            this.fetchList()
          } else {
            this.$message.error(res.msg)
          }
        } catch (e) {}
      }).catch(() => {})
    },
    async handleSubmit() {
      const { id, name, command, cron_expr, remark } = this.form
      if (!name) return this.$message.warning('请输入任务名称')
      if (!command) return this.$message.warning('请选择命令')
      if (!cron_expr) return this.$message.warning('请输入 Cron 表达式')

      const api = id ? request.put : request.post
      const url = id ? '/cron_task/edit' : '/cron_task/add'
      const data = id ? { id, name, cron_expr, remark } : { name, command, cron_expr, remark }

      const res = await api(url, data)
      if (res.code === 0) {
        this.$message.success(id ? '编辑成功' : '新增成功')
        this.dialogVisible = false
        this.fetchList()
      } else {
        this.$message.error(res.msg)
      }
    }
  }
}
</script>
```

- [ ] **Step 2: 添加路由**

在 `frontend/src/router/index.js` 的 children 数组中，Notice 路由之后添加：

```js
      {
        path: 'system/cron-task',
        name: 'CronTask',
        component: () => import('@/views/system/cron-task'),
        meta: { title: '定时任务' }
      },
      {
        path: 'system/cron-task-log',
        name: 'CronTaskLog',
        component: () => import('@/views/system/cron-task-log'),
        meta: { title: '执行日志' }
      },
```

- [ ] **Step 3: Commit**

```bash
git add frontend/src/views/system/cron-task.vue frontend/src/router/index.js
git commit -m "feat: 定时任务列表页 + 路由"
```

---

### Task 7: 前端 - 执行日志页

**Files:**
- Create: `frontend/src/views/system/cron-task-log.vue`

- [ ] **Step 1: 创建执行日志页**

```vue
<template>
  <div>
    <el-card>
      <div slot="header">
        <span>执行日志</span>
      </div>

      <el-form :model="searchForm" inline size="small" style="margin-bottom:15px">
        <el-form-item label="任务ID">
          <el-input v-model="searchForm.task_id" placeholder="任务ID" clearable style="width:100px" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width:120px">
            <el-option :value="1" label="成功" />
            <el-option :value="0" label="失败" />
          </el-select>
        </el-form-item>
        <el-form-item label="日期范围">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"
            style="width:260px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="list" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="task_id" label="任务ID" width="70" />
        <el-table-column prop="command" label="命令" width="140" />
        <el-table-column prop="status" label="状态" width="80">
          <template slot-scope="{row}">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
              {{ row.status === 1 ? '成功' : '失败' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="duration" label="耗时(秒)" width="90" />
        <el-table-column prop="started_at" label="执行时间" width="170" />
        <el-table-column label="操作" width="120">
          <template slot-scope="{row}">
            <el-button type="text" @click="handleViewOutput(row)">查看输出</el-button>
            <el-button v-auth="'cron_task_log:delete'" type="text" style="color:#f56c6c" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        style="margin-top:15px;text-align:right"
        background
        layout="total, prev, pager, next"
        :current-page="page"
        :page-size="limit"
        :total="total"
        @current-change="handlePageChange"
      />
    </el-card>

    <el-dialog title="执行输出" :visible.sync="outputVisible" width="650px">
      <pre style="max-height:400px;overflow:auto;background:#f5f5f5;padding:12px;border-radius:4px;font-size:13px;white-space:pre-wrap;word-break:break-all">{{ outputContent || '(无输出)' }}</pre>
    </el-dialog>
  </div>
</template>

<script>
import request from '@/api'

export default {
  name: 'SystemCronTaskLog',
  data() {
    return {
      list: [],
      page: 1,
      limit: 10,
      total: 0,
      searchForm: { task_id: '', status: undefined },
      dateRange: null,
      outputVisible: false,
      outputContent: ''
    }
  },
  created() {
    this.fetchList()
  },
  methods: {
    async fetchList() {
      try {
        const params = { page: this.page, limit: this.limit }
        if (this.searchForm.task_id) params.task_id = this.searchForm.task_id
        if (this.searchForm.status !== undefined && this.searchForm.status !== '') params.status = this.searchForm.status
        if (this.dateRange && this.dateRange.length === 2) {
          params.start_date = this.dateRange[0]
          params.end_date = this.dateRange[1]
        }
        const res = await request.get('/cron_task_log/list', { params })
        if (res.code === 0) {
          this.list = res.data.list
          this.total = res.data.total
        }
      } catch (e) {}
    },
    handleSearch() {
      this.page = 1
      this.fetchList()
    },
    handleReset() {
      this.searchForm = { task_id: '', status: undefined }
      this.dateRange = null
      this.page = 1
      this.fetchList()
    },
    handlePageChange(page) {
      this.page = page
      this.fetchList()
    },
    handleViewOutput(row) {
      this.outputContent = row.output
      this.outputVisible = true
    },
    handleDelete(row) {
      this.$confirm('确定删除该日志吗？', '提示', { type: 'warning' }).then(async () => {
        try {
          const res = await request.delete('/cron_task_log/delete', { data: { id: row.id } })
          if (res.code === 0) {
            this.$message.success('删除成功')
            this.fetchList()
          } else {
            this.$message.error(res.msg)
          }
        } catch (e) {}
      }).catch(() => {})
    }
  }
}
</script>
```

- [ ] **Step 2: Commit**

```bash
git add frontend/src/views/system/cron-task-log.vue
git commit -m "feat: 执行日志页"
```

---

### Task 8: 验证与集成测试

**Files:** 无新增

- [ ] **Step 1: 重建并启动容器**

```bash
docker-compose up -d --build
```

- [ ] **Step 2: 执行数据库迁移**

```bash
docker exec mysql bash -c "tr -d '\r' < /scripts/migrate.sh | bash"
```

- [ ] **Step 3: 验证 CronRun 命令可执行**

```bash
docker exec php php /var/www/html/think cron:run clean_logs
```

预期：输出清理日志的结果，`cron_task_log` 表新增一条记录，`cron_task` 表 `last_run_at` 和 `last_status` 更新。

- [ ] **Step 4: 验证 API 接口**

```bash
# 登录获取 cookie
curl -sk -X POST https://localhost/admin/auth/login -H 'Content-Type: application/json' -d '{"username":"admin","password":"123456"}' -c cookies.txt

# 查看任务列表
curl -sk https://localhost/admin/cron_task/list -b cookies.txt

# 查看可用命令
curl -sk https://localhost/admin/cron_task/commands -b cookies.txt

# 手动触发执行
curl -sk -X POST https://localhost/admin/cron_task/run -H 'Content-Type: application/json' -b cookies.txt -d '{"id":1}'

# 查看执行日志
curl -sk https://localhost/admin/cron_task_log/list -b cookies.txt
```

- [ ] **Step 5: 验证 crontab 同步**

```bash
# 查看当前 crontab 内容
docker exec php crontab -l
```

预期：包含 `0 3 * * * php /var/www/html/think cron:run clean_logs`，无旧格式 `clean_logs` 行。

- [ ] **Step 6: 验证前端页面**

浏览器访问 `https://localhost/system/cron-task` 和 `https://localhost/system/cron-task-log`，检查：
- 定时任务列表可加载、可新增、可编辑、可启停、可手动执行
- 执行日志列表可加载、可按条件筛选、可查看输出

- [ ] **Step 7: Commit 验证结果（如有修复）**

如有修复，单独 commit。
