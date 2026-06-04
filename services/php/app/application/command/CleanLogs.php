<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class CleanLogs extends Command
{
    protected function configure()
    {
        $this->setName('clean_logs')->setDescription('清理过期的操作日志和登录日志');
    }

    protected function execute(Input $input, Output $output)
    {
        $configs = Db::table('system_config')
            ->whereIn('key', ['log_retention_days', 'clean_operation_log', 'clean_login_log'])
            ->column('value', 'key');

        $days = intval($configs['log_retention_days'] ?? 360);
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $ts = date('Y-m-d H:i:s');

        if (($configs['clean_operation_log'] ?? '1') === '1') {
            $count = Db::table('operation_log')->where('created_at', '<', $cutoff)->delete();
            $output->writeln("[{$ts}] 清理操作日志: {$count} 条（保留最近 {$days} 天）");
        }

        if (($configs['clean_login_log'] ?? '1') === '1') {
            $count = Db::table('login_log')->where('created_at', '<', $cutoff)->delete();
            $output->writeln("[{$ts}] 清理登录日志: {$count} 条（保留最近 {$days} 天）");
        }
    }
}
