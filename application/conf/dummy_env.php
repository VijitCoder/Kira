<?php
/**
 * Болванка конфига окружения.
 * Выложить на соответствующем сервере и переименовать в env.php
 * Обязательный параметр - только 'environment'
 */
return [
    'environment' => 'local', // local | dev | stage | production

    'db'  => [
        'dsn'      => 'mysql:dbname=testdb; host=127.0.0.1; charset=UTF8',
        'user'     => 'user',
        'password' => 'password',
        'options'  => [],        // {@link http://php.net/manual/ru/pdo.setattribute.php}
        'set_timezone' => false, // по умолчанию - TRUE

    ],

    // имя домена. Для ситуаций, когда его невозможно определить из $_SERVER (например, в консоли).
    'domain' => 'my_site.com',

    // почта для писем из движка. Баги, логи и т.п.
    'admin_mail'   => 'a@my_site.com',
    // адрес отправителя для автоматических писателей, типа логера.
    'noreply_mail' => 'noreply@my_site.com',

    //Логер
    'log' => [
        'switch_on'   => true,      // включить логирование
        'store'       => \engine\Log::STORE_IN_DB, // STORE_IN_DB | STORE_IN_FILES - тип хранителя логов
        'db_conf_key' => 'db',      // ключ конфига БД, если храним логи в базе
        'log_path'    => TEMP_PATH, // путь к каталогу, куда складывать файлы логов, если храним в файлах
        'timezone'    => '',        // часовой пояс для записи лога
    ],

    // ключи от соц. сетей (REST API)
    //...
];
