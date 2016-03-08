<?php
/**
 * Подключение скрипта конфига можно сделать более корректно, но будет сложнее. Сейчас подключается напрямую в App.php.
 */
$main = [
    // FQN контроллера + метод для главной страницы сайта
    'indexHandler' => APP_NS_PREFIX . 'controllers\Login',

    // FQN контроллера + метод, отвечающий при 404 от роутера. Туда же можно направить web-сервер с его
    // ошибками 401-404, 500
    'errorHandler' => APP_NS_PREFIX . 'controllers\Error',

    // 'router' => APP_NS_PREFIX . 'SimpleRouter',
    'routes'       => require __DIR__ . '/routes.php',

    'validators' => [
        'password' => [
            'min_len'  => 5, // минимальная длина пароля
            'min_comb' => 3, // минимальная комбинация наборов символов в пароле
        ],

        'mail' => [
            // Запрещенные для регистрации mail-сервера (это сервисы 5-тиминутных ящиков)
            'black_servers' => [
                'fakeinbox.com', 'sharklasers.com', 'mailcker.com', 'yopmail.com', 'jnxjn.com', 'jnxjn.com', '0815.ru',
                '10minutemail.com', 'agedmail.com', 'ano-mail.net', 'asdasd.ru', 'brennendesreich.de', 'buffemail.com',
                'bund.us', 'cool.fr.nf', 'courriel.fr.nf', 'cust.in', 'dbunker.com', 'dingbone.com', 'discardmail.com',
                'discardmail.de', 'dispostable.com', 'duskmail.com', 'emaillime.com', 'flyspam.com', 'fudgerub.com',
                'guerrillamail.com', 'hulapla.de', 'jetable.fr.nf', 'jetable.org', 'lookugly.com', 'mailcatch.com',
                'mailspeed.ru', 'mega.zik.dj', 'meltmail.com', 'moncourrier.fr.nf', 'monemail.fr.nf', 'monmail.fr.nf',
                'noclickemail.com', 'nomail.xl.cx', 'nospam.ze.tc', 'rtrtr.com', 's0ny.net', 'smellfear.com',
                'spambog.com', 'spambog.de', 'spambog.ru', 'speed.1s.fr', 'superstachel.de', 'teewars.org',
                'tempemail.net', 'tempinbox.com', 'tittbit.in', 'yopmail.fr', 'yopmail.net', 'ypmail.webarnak.fr.eu.org',
                'cuvox.de', 'armyspy.com', 'dayrep.com', 'einrot.com', 'fleckens.hu', 'gustr.com', 'jourrapide.com',
                'rhyta.com', 'superrito.com', 'teleworm.us', 'trbvm.com', 'cherevatyy.ru', 'my.dropmail.me',
                'dropmail.me', '10mail.org', 'yomail.info', 'excite.co.jp',
            ],
        ],
    ],

    // ограничения на аватар
    'avatar'     => [
        'minSize'   => 200, // размер картинки по узкой стороне
        'minWeight' => 200, // минимальный вес файла, кБ
        'maxWeight' => 2.5, // максимальный вес, Мб
        'format'    => 'gif, jpg, png',
        'w'         => 300, // размеры создаваемой миниатюры
        'h'         => 300,

    ],

    // Заготовка. Реальный конфиг подключения сливается из env.php  (см. dummy_env.php)
    'db'         => [
        'dsn'      => 'mysql:dbname=base0; host=127.0.0.1; charset=UTF8',
        'user'     => 'guest',
        'password' => '',
        'options'  => [
            PDO::ATTR_TIMEOUT            => 10,                     // таймаут соединения, в секудах
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // позволит ловить исключения PDO
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC        // по умолчанию - в ассоциативный массив
        ],
    ],
];

$env = require __DIR__ . '/env.php';

return engine\utils\Arrays::merge_recursive($main, $env);
