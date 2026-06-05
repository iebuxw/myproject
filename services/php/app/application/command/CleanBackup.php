<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\facade\Log;

class CleanBackup extends Command
{
    protected function configure()
    {
        $this->setName('clean_backup')->setDescription('清理过期的数据库备份');
    }

    protected function execute(Input $input, Output $output)
    {
        $result = self::doCleanup();
        $msg = "清理过期备份 {$result['count']} 条（保留最近 {$result['days']} 天）";
        Log::notice('[CleanBackup] ' . $msg);
        $output->writeln($msg);
    }

    public static function doCleanup()
    {
        $config = Db::table('system_config')->where('key', 'db_backup_keep_days')->find();
        $days = $config ? (int)$config['value'] : 30;

        $deadline = date('Y-m-d H:i:s', time() - $days * 86400);

        $expired = Db::table('db_backup')
            ->where('created_at', '<', $deadline)
            ->select();

        $count = 0;
        $backupDir = '/var/www/backups';
        foreach ($expired as $row) {
            $filepath = $backupDir . '/' . $row['filename'];
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
            Db::table('db_backup')->where('id', $row['id'])->delete();
            $count++;
        }

        return ['count' => $count, 'days' => $days];
    }
}
