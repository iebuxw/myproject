<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;

class LogConfig extends Controller
{
    // GET /admin/log_config/read
    public function read()
    {
        $rows = Db::table('system_config')
            ->whereIn('key', ['log_retention_days', 'clean_operation_log', 'clean_login_log'])
            ->column('value', 'key');

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'log_retention_days'  => $rows['log_retention_days'] ?? '360',
                'clean_operation_log' => $rows['clean_operation_log'] ?? '1',
                'clean_login_log'     => $rows['clean_login_log'] ?? '1',
            ],
        ]);
    }

    // PUT /admin/log_config/update
    public function update()
    {
        $logRetentionDays  = input('put.log_retention_days', 360);
        $cleanOperationLog = input('put.clean_operation_log', '1');
        $cleanLoginLog     = input('put.clean_login_log', '1');

        $days = (int)$logRetentionDays;
        if ($days < 1 || $days > 3650) {
            return json(['code' => 1002, 'msg' => '日志保留天数需在 1~3650 之间', 'data' => null]);
        }

        Db::table('system_config')->where('key', 'log_retention_days')->update(['value' => (string)$days]);
        Db::table('system_config')->where('key', 'clean_operation_log')->update(['value' => $cleanOperationLog === '1' ? '1' : '0']);
        Db::table('system_config')->where('key', 'clean_login_log')->update(['value' => $cleanLoginLog === '1' ? '1' : '0']);

        return json(['code' => 0, 'msg' => '保存成功', 'data' => null]);
    }
}
