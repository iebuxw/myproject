<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

class CleanLogs extends Command
{
    protected function configure()
    {
        $this->setName('clean_logs')->setDescription('清理过期的日志');
    }

    protected function execute(Input $input, Output $output)
    {
        $result = \app\admin\controller\LogConfig::doCleanup();
        $msg = "清理操作日志 {$result['operation_log']} 条，清理登录日志 {$result['login_log']} 条（保留最近 {$result['days']} 天）";
        Log::notice($msg);
        $output->writeln($msg);
    }
}
