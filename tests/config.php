<?php
$main = [
    'indexHandler' => 'app\blog\BlogController',

    'router' => [
        '404_handler' => 'app\common\ErrorController',
        'log_redirects' => true,
        'routes' => require 'routes.php',
    ],

    'timezone' => 'Asia/Krasnoyarsk',

    'err_tail' => '<br>Администратору отправлено сообщение.<br>Повторите попытку позже.',
];

$env = require 'env.php';

return kira\utils\Arrays::merge_recursive($main, $env, true);
