<?php
return [
    'type'       => 'redis',
    'host'       => getenv('REDIS_HOST') ?: '127.0.0.1',
    'port'       => getenv('REDIS_PORT') ?: 6379,
    'password'   => getenv('REDIS_PASSWORD') ?: '',
    'select'     => 0,
    'timeout'    => 0,
    'expire'     => 3600,
    'persistent' => false,
    'prefix'     => 'session:',
];
