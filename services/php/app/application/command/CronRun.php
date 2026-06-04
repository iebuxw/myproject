<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\Db;
use think\facade\Log;

class CronRun extends Command
{
    protected function configure()
    {
        $this->setName('cron:run')
            ->addArgument('command_name', Argument::REQUIRED, '要执行的命令名')
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
