<?php
/**
 * Болванка конфига окружения.
 * Выложить на соответствующем сервере и переименовать в env.php
 * Обязательный параметр - только 'environment'
 */
return [
    'environment' => 'local', // local|dev|stage|production

    'db' => [
        'dsn' => 'mysql:dbname=testdb; host=127.0.0.1; charset=UTF8',
        'user' => 'user',
        'password' => 'password',
    ],

    // имя домена. Для ситуаций, когда его невозможно определить из $_SERVER (например, в консоли).
    'domain' => 'my_site.com',

    // почта для писем из движка. Баги, логи и т.п.
    'admin_mail'   => 'a@my_site.com',
    // адрес отправителя для автоматических писателей, типа логера.
    'noreply_mail' => 'noreply@my_site.com',

    // ключи от соц. сетей (REST API)
    //...
];
