#!/usr/bin/env php
<?php

$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT `key`, `value` FROM system_config WHERE `key` IN ('log_retention_days', 'clean_operation_log', 'clean_login_log')");
    $config = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $config[$row['key']] = $row['value'];
    }

    $days = intval($config['log_retention_days'] ?? 360);
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

    $ts = date('Y-m-d H:i:s');

    if (($config['clean_operation_log'] ?? '1') === '1') {
        $count = $pdo->exec("DELETE FROM operation_log WHERE created_at < '$cutoff'");
        echo "[{$ts}] 清理操作日志: {$count} 条（保留最近 {$days} 天）\n";
    }

    if (($config['clean_login_log'] ?? '1') === '1') {
        $count = $pdo->exec("DELETE FROM login_log WHERE created_at < '$cutoff'");
        echo "[{$ts}] 清理登录日志: {$count} 条（保留最近 {$days} 天）\n";
    }
} catch (Exception $e) {
    echo '[' . date('Y-m-d H:i:s') . '] 错误: ' . $e->getMessage() . "\n";
    exit(1);
}
