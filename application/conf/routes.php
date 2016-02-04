<?php
/**
 * Роуты приложения
 * Идея описания маршрутов взята в Yii 1.x. Реализация своя.
 *
 * Левая часть, маска подстановок: <[a-z0-9_]+:.+>, не зависит от регистра. В остальном - синтаксис обычной
 * PCRE регулярки.
 *
 * Простые ошибки:
 * - 404 = обратный слеш вместо прямого. Нужно использовать '/'
 * - 404 = справа несоблюдение регистра, например контроллер с маленькой буквы, а на деле - с большой.
 */
return [
    APP_NS_PREFIX . 'controllers\\' => [
        'registration' => 'Registration',
        'sendconfirm'  => 'Registration/sendconfirm',
        'confirm'      => 'Registration/confirm',
        'login'   => 'Login',
        'logout'  => 'Login/out',
        'recover' => 'Login/recover',
        'profile' => 'Profile',

        'ajax/check' => 'Registration/check',

        //Общие правила. Должны быть самыми последними вообще
        '<controller:[a-z]+>'                 => '<controller>',
        '<controller:[a-z]+>/<action:[a-z]+>' => '<controller>/<action>',
    ],
];
