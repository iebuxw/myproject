<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;
use think\facade\Cache;
use think\facade\Log;

class DbBackup extends Controller
{
    // GET /admin/db_backup/list
    public function index()
    {
        $page      = input('get.page', 1);
        $limit     = input('get.limit', 10);
        $startDate = input('get.start_date', '');
        $endDate   = input('get.end_date', '');

        $query = Db::table('db_backup');

        if (!empty($startDate)) {
            $query->where('created_at', '>=', $startDate . ' 00:00:00');
        }
        if (!empty($endDate)) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $total = $query->count();
        $list  = $query->order('id', 'desc')->page((int)$page, (int)$limit)->select();

        return json(['code' => 0, 'msg' => 'success', 'data' => [
            'list'  => $list,
            'total' => $total,
        ]]);
    }

    // POST /admin/db_backup/add
    public function add()
    {
        $adminId = $this->request->adminId;
        if (!$this->checkPermission($adminId, 'db_backup:add')) {
            return json(['code' => 1007, 'msg' => '无权限', 'data' => null]);
        }

        // 创建进行中记录
        $id = Db::table('db_backup')->insertGetId([
            'filename'     => '',
            'file_size'    => 0,
            'trigger_type' => 1,
            'status'       => 0,
            'is_snapshot'  => 0,
            'remark'       => '备份中...',
        ]);

        // 后台异步执行备份（传 record_id 让命令更新该记录而非新建）
        // 注意：PHP_BINARY 在 FPM 下指向 php-fpm 而非 php-cli，需用 exec('which php') 获取正确路径
        $phpBin = trim(shell_exec('which php 2>/dev/null')) ?: '/usr/local/bin/php';
        $thinkPath = app()->getRootPath() . 'think';
        exec("nohup {$phpBin} {$thinkPath} backup_db {$id} > /dev/null 2>&1 &");

        return json(['code' => 0, 'msg' => '备份已在后台执行，请稍后刷新', 'data' => null]);
    }

    // POST /admin/db_backup/restore
    public function restore()
    {
        $adminId = $this->request->adminId;
        $admin   = $this->request->admin;

        // 超管校验（admin_id=1 为超管）
        if ($adminId != 1) {
            return json(['code' => 1007, 'msg' => '仅超级管理员可执行恢复操作', 'data' => null]);
        }

        $id = input('post.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $backup = Db::table('db_backup')->where('id', $id)->find();
        if (!$backup) {
            return json(['code' => 1004, 'msg' => '备份记录不存在', 'data' => null]);
        }

        $filepath = '/var/www/backups/' . $backup['filename'];
        if (!file_exists($filepath)) {
            return json(['code' => 500, 'msg' => '备份文件不存在', 'data' => null]);
        }

        // 维护锁定（10分钟自动过期兜底）
        Cache::set('system:maintenance', '1', 600);

        try {
            // 自动快照
            \app\command\BackupDb::doBackup(1, 1, '恢复前自动快照（来源：ID=' . $id . ' ' . $backup['filename'] . '）');

            // 保存备份记录元数据（恢复会覆盖整个数据库包括此表）
            $backupRecords = Db::table('db_backup')->order('id', 'asc')->select();

            // 执行恢复
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';
            $db   = getenv('DB_NAME') ?: 'myproject';

            $restoreCmd = "gunzip < {$filepath} | mysql -h{$host} -P{$port} -u{$user} -p{$pass} {$db} 2>&1";
            $output = shell_exec($restoreCmd);

            if ($output && stripos($output, 'error') !== false) {
                Cache::rm('system:maintenance');
                return json(['code' => 500, 'msg' => '恢复失败：' . $output, 'data' => null]);
            }

            // 还原备份记录元数据
            Db::execute('DELETE FROM db_backup');
            foreach ($backupRecords as $row) {
                Db::table('db_backup')->insert($row);
            }

            // 解除锁定
            Cache::rm('system:maintenance');
        } catch (\Exception $e) {
            Cache::rm('system:maintenance');
            return json(['code' => 500, 'msg' => '恢复失败：' . $e->getMessage(), 'data' => null]);
        }

        Log::notice('[DbBackup] 数据库恢复成功，来源：' . $backup['filename']);
        return json(['code' => 0, 'msg' => '恢复成功', 'data' => null]);
    }

    // GET /admin/db_backup/download
    public function download()
    {
        $id = input('get.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $backup = Db::table('db_backup')->where('id', $id)->find();
        if (!$backup) {
            return json(['code' => 1004, 'msg' => '备份记录不存在', 'data' => null]);
        }

        $filepath = '/var/www/backups/' . $backup['filename'];
        if (!file_exists($filepath)) {
            return json(['code' => 500, 'msg' => '备份文件不存在', 'data' => null]);
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $backup['filename'] . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    // DELETE /admin/db_backup/delete
    public function delete()
    {
        $id = input('delete.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $backup = Db::table('db_backup')->where('id', $id)->find();
        if (!$backup) {
            return json(['code' => 1004, 'msg' => '备份记录不存在', 'data' => null]);
        }

        if (!empty($backup['filename'])) {
            $filepath = '/var/www/backups/' . $backup['filename'];
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
        }

        Db::table('db_backup')->where('id', $id)->delete();

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // GET /admin/db_backup/config
    public function config()
    {
        $row = Db::table('system_config')->where('key', 'db_backup_keep_days')->find();
        $keepDays = $row ? (int)$row['value'] : 30;

        return json(['code' => 0, 'msg' => 'success', 'data' => ['keep_days' => $keepDays]]);
    }

    // PUT /admin/db_backup/config
    public function saveConfig()
    {
        $keepDays = input('put.keep_days', 0);
        if ($keepDays < 1 || $keepDays > 365) {
            return json(['code' => 1002, 'msg' => '保留天数应在1-365之间', 'data' => null]);
        }

        $exists = Db::table('system_config')->where('key', 'db_backup_keep_days')->find();
        if ($exists) {
            Db::table('system_config')->where('key', 'db_backup_keep_days')->update(['value' => (string)$keepDays]);
        } else {
            Db::table('system_config')->insert(['key' => 'db_backup_keep_days', 'value' => (string)$keepDays]);
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    private function checkPermission($adminId, $perm)
    {
        if ($adminId == 1) {
            return true;
        }
        $authPaths = $this->request->authPaths ?: [];
        return in_array($perm, $authPaths);
    }
}
