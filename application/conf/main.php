<?php
/**
 * Подключение скрипта конфига можно сделать более корректно, но будет сложнее. Сейчас подключается напрямую в App.php.
 */
$main = [
    // FQN контроллера + метод для главной страницы сайта
    'indexHandler' => 'app\controllers\Login',

    // FQN контроллера + метод, отвечающий при 404 от роутера. Туда же можно направить web-сервер с его
    // ошибками 401-404, 500
    'errorHandler' => 'app\controllers\Error',

    // 'router' => 'app\SimpleRouter',
    'routes' => require 'routes.php',

    // конфигурация аватарок
    'avatar' => [
        'path'       => ROOT_PATH . 'public/files/avatar/',
        'url'        => '/public/files/avatar/',
        'min_size'   => 200, // размер картинки по узкой стороне
        'min_weight' => 200, // минимальный вес файла, кБ
        'max_weight' => 2.5, // максимальный вес, Мб
        'format'     => 'gif, jpg, png',
        'w'          => 300, // размеры создаваемой миниатюры
        'h'          => 300,
    ],

    // Заготовка. Реальный конфиг подключения сливается из env.php  (см. dummy_env.php)
    'db' => [
        'dsn'      => 'mysql:dbname=base0; host=127.0.0.1; charset=UTF8',
        'user'     => 'guest',
        'password' => '',
        'options'  => [ // {@link http://php.net/manual/ru/pdo.setattribute.php}
            PDO::ATTR_TIMEOUT            => 21,                     // таймаут соединения, в секудах
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // позволит ловить исключения PDO
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // по умолчанию - в ассоциативный массив
        ],
    ],
];

$env = require 'env.php';

return engine\utils\Arrays::merge_recursive($main, $env, true);
