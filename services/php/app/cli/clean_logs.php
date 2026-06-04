#!/usr/bin/env php
<?php
namespace think;

date_default_timezone_set('PRC');

require __DIR__ . '/../thinkphp/base.php';

// 初始化应用（加载 database 等配置，Db::table() 才能用）
Container::get('app')->initialize();

$configs = \think\Db::table('system_config')
    ->whereIn('key', ['log_retention_days', 'clean_operation_log', 'clean_login_log'])
    ->column('value', 'key');

$days = intval($configs['log_retention_days'] ?? 360);
$cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
$ts = date('Y-m-d H:i:s');

if (($configs['clean_operation_log'] ?? '1') === '1') {
    $count = \think\Db::table('operation_log')->where('created_at', '<', $cutoff)->delete();
    echo "[{$ts}] 清理操作日志: {$count} 条（保留最近 {$days} 天）\n";
}

if (($configs['clean_login_log'] ?? '1') === '1') {
    $count = \think\Db::table('login_log')->where('created_at', '<', $cutoff)->delete();
    echo "[{$ts}] 清理登录日志: {$count} 条（保留最近 {$days} 天）\n";
}
