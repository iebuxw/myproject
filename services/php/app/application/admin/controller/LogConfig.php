<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;

class LogConfig extends Controller
{
    // 核心清理逻辑，供 API 和定时任务共用
    public static function doCleanup(): array
    {
        $configs = Db::table('system_config')
            ->whereIn('key', ['log_retention_days', 'clean_operation_log', 'clean_login_log', 'clean_cron_task_log'])
            ->column('value', 'key');

        $days   = intval($configs['log_retention_days'] ?? 360);
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $result = ['operation_log' => 0, 'login_log' => 0, 'cron_task_log' => 0, 'days' => $days];

        if (($configs['clean_operation_log'] ?? '1') === '1') {
            $result['operation_log'] = Db::table('operation_log')->where('created_at', '<', $cutoff)->delete();
        }

        if (($configs['clean_login_log'] ?? '1') === '1') {
            $result['login_log'] = Db::table('login_log')->where('created_at', '<', $cutoff)->delete();
        }

        if (($configs['clean_cron_task_log'] ?? '1') === '1') {
            $result['cron_task_log'] = Db::table('cron_task_log')->where('started_at', '<', $cutoff)->delete();
        }

        return $result;
    }

    // GET /admin/log_config/read
    public function read()
    {
        $rows = Db::table('system_config')
            ->whereIn('key', ['log_retention_days', 'clean_operation_log', 'clean_login_log', 'clean_cron_task_log'])
            ->column('value', 'key');

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'log_retention_days'  => $rows['log_retention_days'] ?? '360',
                'clean_operation_log' => $rows['clean_operation_log'] ?? '1',
                'clean_login_log'     => $rows['clean_login_log'] ?? '1',
                'clean_cron_task_log' => $rows['clean_cron_task_log'] ?? '1',
            ],
        ]);
    }

    // PUT /admin/log_config/update
    public function update()
    {
        $logRetentionDays  = input('put.log_retention_days', 360);
        $cleanOperationLog = input('put.clean_operation_log', '1');
        $cleanLoginLog     = input('put.clean_login_log', '1');
        $cleanCronTaskLog  = input('put.clean_cron_task_log', '1');

        $days = (int)$logRetentionDays;
        if ($days < 1 || $days > 3650) {
            return json(['code' => 1002, 'msg' => '日志保留天数需在 1~3650 之间', 'data' => null]);
        }

        Db::table('system_config')->where('key', 'log_retention_days')->update(['value' => (string)$days]);
        Db::table('system_config')->where('key', 'clean_operation_log')->update(['value' => $cleanOperationLog === '1' ? '1' : '0']);
        Db::table('system_config')->where('key', 'clean_login_log')->update(['value' => $cleanLoginLog === '1' ? '1' : '0']);
        Db::table('system_config')->where('key', 'clean_cron_task_log')->update(['value' => $cleanCronTaskLog === '1' ? '1' : '0']);

        return json(['code' => 0, 'msg' => '保存成功', 'data' => null]);
    }

    // POST /admin/log_config/cleanup
    public function cleanup()
    {
        $result = self::doCleanup();
        return json([
            'code' => 0,
            'msg'  => "已清理 {$result['operation_log']} 条操作日志、{$result['login_log']} 条登录日志、{$result['cron_task_log']} 条执行日志（保留最近 {$result['days']} 天）",
            'data' => $result,
        ]);
    }
}
