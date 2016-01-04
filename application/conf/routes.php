<?php
/**
 * Роуты приложения
 * Идея описания маршрутов взята в Yii 1.x. Реализация своя.
 */
return [
    //модуль юзера
    APP_NS_PREFIX . 'modules\user\controllers\\' => [
        'registration' => 'RegistrationController',
        //@TODO остальные actions контроллера прописать

        'login'        => 'loginController',
        'logout'       => 'loginController/out',
        'recover'      => 'loginController/recover',
        'user/profile' => 'profileController',
    ],

    //модуль админки
    APP_NS_PREFIX . 'modules\admin\\' => [
    ],

    //Основная зона контроллеров
    APP_NS_PREFIX . 'controllers\\'       => [
        'test' => 'Test',
        '<v:\d+>' => 'Test/some',
        'some/<v:\d+>' => 'Test/some',
        'another/<v:\d+>/<s:\w+>' => 'Test/some',
        'test/<action:\w+>' => 'Test/<action>',
        'test/<action:\w+>/<id:\d+>' => 'Test/<action>',

        //Общие правила. Должны быть самыми последними вообще
        '<controller:[a-z]+>' => '<controller>',
        '<controller:[a-z]+>/<action:[a-z]+>' => '<controller>/<action>',
    ]
];
