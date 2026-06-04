<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;

class CronTask extends Controller
{
    private static function syncCrontab()
    {
        $lines = [];
        exec('crontab -l 2>/dev/null', $lines, $ret);

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

    public static function doSyncCrontab()
    {
        return self::syncCrontab();
    }
}
