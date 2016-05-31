<?php
return [
    'router' => 'install\app\SingleRouter',
    'routes' => [
        'install'        => 'SingleController/index',
        'install/create' => 'SingleController/createApp',
        'install/finish' => 'SingleController/finish',
    ],
];
