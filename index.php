<?php
/**
 * Главный скрипт
 */
mb_internal_encoding('UTF-8');

// Часовой пояс для PHP-функций времени. Можно задать через php.ini. Так или иначе часовой пояс должен быть определен.
date_default_timezone_set('Asia/Novosibirsk');

// Физический путь от корня тома. Гарантированный завершающий слеш. Кроссплатформа. Приводим пути к unix-стилю.
define('ROOT_PATH', str_replace('\\', '/', rtrim(__DIR__, '/')) . '/');

// Корень пространства имен приложения. Все классы приложения, должны быть в его подпространстве.
define('APP_NAMESPACE', 'app\\');

// Каталог приложения.
define('APP_PATH', ROOT_PATH . 'application/');

// Каталог шаблонов приложения.
define('VIEWS_PATH', APP_PATH . 'views/');

// Временный каталог приложения. В него, как минимум, складываются логи. Обязательно создать этот каталог и дать доступ
// на запись для процесса веб-сервера.
define('TEMP_PATH', APP_PATH . 'temp/');

// Путь к основному файлу конфигурации приложения
define('MAIN_CONFIG', APP_PATH . 'conf/main.php');

define('DEBUG', true);

ini_set('display_errors', (int)DEBUG);
ini_set('display_startup_errors', (int)DEBUG);
error_reporting(DEBUG ? E_ALL : 0);

require 'engine/autoloader.php';

engine\App::router()->callAction();
