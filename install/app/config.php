<?php
return [
    'router' => [
        'class' => 'install\app\SingleRouter',
        'routes' => [
            'install'          => 'SingleController/index',
            'install/success'  => 'SingleController/success',
            'install/error'    => 'SingleController/error',
            'install/rollback' => 'SingleController/rollback',
        ],
    ],
];
