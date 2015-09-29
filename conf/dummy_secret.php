<?php
/**
 * Болванка секретного конфига
 * Переименовать в secret.php
 */
return [
    'db' => [
        'dsn' => 'mysql:dbname=testdb; host=127.0.0.1; charset=UTF8',
        'user' => 'user',
        'password' => 'password',
        'options' => [
            PDO::ATTR_TIMEOUT => 10,                         //таймаут соединения, в секудах
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,     //позволит ловить исключения PDO
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC //по умолчанию - в ассоциативный массив
        ],
    ],

    //почта для писем из движка. Баги, логи и т.п.
    'adminMail' => 'u@s.com',

    //ключи от соц. сетей (REST API)
    //...
];
