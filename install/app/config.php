<?php
return [
    'router' => 'install\app\SingleRouter',
    'routes' => [
        'install'        => 'SingleController/index',
        'install/finish' => 'SingleController/finish',
        'install/rollback' => 'SingleController/rollback',
    ],
];
