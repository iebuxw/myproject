<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\Db;
use think\facade\Log;

class BackupDb extends Command
{
    protected function configure()
    {
        $this->setName('backup_db')
            ->addArgument('record_id', Argument::OPTIONAL, '手动备份时更新指定记录ID')
            ->setDescription('备份数据库');
    }

    protected function execute(Input $input, Output $output)
    {
        $recordId = $input->getArgument('record_id');
        $triggerType = $recordId ? 1 : 2;

        $result = self::doBackup($triggerType, 0, '', $recordId);

        if ($result['status'] === 1) {
            $output->writeln($result['msg']);
            return 0;
        }
        $output->writeln($result['msg']);
        return 1;
    }

    /**
     * @param int $triggerType 1=手动 2=定时
     * @param int $isSnapshot 0=常规 1=恢复前快照
     * @param string $remark
     * @param int|null $recordId 手动备份时更新指定记录，null 则新建
     */
    public static function doBackup($triggerType = 2, $isSnapshot = 0, $remark = '', $recordId = null)
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $db   = getenv('DB_NAME') ?: 'myproject';

        $backupDir = '/var/www/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = $db . '_' . date('Ymd_His') . '.sql.gz';
        $filepath = $backupDir . '/' . $filename;

        $cmd = "mysqldump -h{$host} -P{$port} -u{$user} -p{$pass} --single-transaction --routines --triggers {$db} 2>/dev/null | gzip > {$filepath}";
        shell_exec($cmd);

        if (!file_exists($filepath) || filesize($filepath) === 0) {
            @unlink($filepath);
            $failMsg = $remark ?: '数据库备份失败：文件未生成或为空';
            $data = [
                'filename'     => $filename,
                'file_size'    => 0,
                'trigger_type' => $triggerType,
                'status'       => 0,
                'is_snapshot'  => $isSnapshot,
                'remark'       => $failMsg,
            ];
            if ($recordId) {
                Db::table('db_backup')->where('id', $recordId)->update($data);
            } else {
                Db::table('db_backup')->insert($data);
            }
            Log::error('[BackupDb] ' . $failMsg);
            return ['status' => 0, 'filename' => $filename, 'msg' => $failMsg];
        }

        $fileSize = filesize($filepath);
        $data = [
            'filename'     => $filename,
            'file_size'    => $fileSize,
            'trigger_type' => $triggerType,
            'status'       => 1,
            'is_snapshot'  => $isSnapshot,
            'remark'       => $remark,
        ];
        if ($recordId) {
            Db::table('db_backup')->where('id', $recordId)->update($data);
        } else {
            Db::table('db_backup')->insert($data);
        }

        $msg = "数据库备份成功：{$filename} (" . self::formatSize($fileSize) . ")";
        Log::notice('[BackupDb] ' . $msg);
        return ['status' => 1, 'filename' => $filename, 'file_size' => $fileSize, 'msg' => $msg];
    }

    private static function formatSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
