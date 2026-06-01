<?php
return [
    'type'     => 'mysql',
    'hostname' => getenv('DB_HOST') ?: '127.0.0.1',
    'database' => getenv('DB_NAME') ?: 'myproject',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: 'root123',
    'hostport' => getenv('DB_PORT') ?: '3306',
    'charset'  => 'utf8mb4',
    'prefix'   => '',
    'debug'    => getenv('APP_DEBUG') === '1',
];
