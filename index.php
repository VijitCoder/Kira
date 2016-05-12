<?php
/**
 * Главный скрипт
 */
mb_internal_encoding('UTF-8');

// Часовой пояс по версии PHP {@link http://php.net/manual/en/timezones.php}
// Можно задать через php.ini, так или иначе часовой пояс должен быть определен.
date_default_timezone_set('Asia/Novosibirsk');

// Физический путь от корня тома. Гарантированный завершающий слеш. Кроссплатформа. Приводим пути к unix-стилю.
define('ROOT_PATH', str_replace('\\', '/', rtrim(__DIR__, '/')) . '/');

// Префикс пространства имен приложения. Все классы приложения, должны быть в его подпространстве.
define('APP_NS_PREFIX', 'app\\');

// Физический каталог со скриптами приложения.
define('APP_PATH', ROOT_PATH . 'application/');

// Физический путь к шаблонам приложения.
define('VIEWS_PATH', APP_PATH . 'views/');

// Временный каталог приложения. В него, как минимум, складываются логи. Обязательно создать этот каталог и дать доступ
// на запись для процесса веб-сервера.
define('TEMP_PATH', APP_PATH . 'temp/');

// путь к файлу основноЙ конфигурации приложения
define('MAIN_CONFIG', APP_PATH . 'conf/main.php');

define('DEBUG', true);

ini_set('display_errors', (int)DEBUG);
ini_set('display_startup_errors', (int)DEBUG);
error_reporting(DEBUG ? E_ALL : 0);

require 'engine/autoloader.php';

// Скрипт инициализации моего приложения-примера. Не имеет отношения к движку, это костыли :)
include APP_PATH . 'init.php';

engine\App::router()->callAction();
