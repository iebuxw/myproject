<?php
return [
    'app_host'       => '',
    'app_debug'      => getenv('APP_DEBUG') === '1',
    'app_trace'      => false,
    'default_module' => 'admin',
    'url_route_on'   => true,
    'url_route_must' => false,
    'session_auto_start' => false,
];
