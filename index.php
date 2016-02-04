<?php
/**
 * Главный скрипт
 */
mb_internal_encoding('UTF-8');

//физический путь от корня тома. Гарантированный завершающий слеш. Кроссплатформа. Приводим пути к unix-стилю.
define('ROOT_PATH', str_replace('\\', '/', rtrim(__DIR__, '/')) . '/');

//Префикс пространства имен приложения. Все классы приложения, должны быть в его подпространстве.
define('APP_NS_PREFIX', 'app\\');
//Физический каталог со скриптами приложения.
define('APP_PATH', ROOT_PATH . 'application/');
//Физический путь к шаблонам приложения. Они могут быть где угодно, для этого и введена константа.
define('VIEWS_PATH', ROOT_PATH . 'views/');
//URL к css/js приложения. Путь к статике светится в браузере, поэтому ее нужно разместить, не компроментируя иерархию
//приложения. @TODO че бы эдакое придумать, чтоб не использовать константу и не связывать движок?
define('ASSETS_URL', '/views/assets/');
//путь к файлу основноЙ конфигурации приложения
define('MAIN_CONFIG', APP_PATH . 'conf/main.php');


define('DEBUG', true);

ini_set('display_errors', (int)DEBUG);
ini_set('display_startup_errors', (int)DEBUG);
error_reporting(DEBUG ? E_ALL : 0);

require 'engine/autoloader.php';

//Скрипт инициализации моего приложения-примера. Не имеет отношения к движку, это костыли :)
include APP_PATH . 'init.php';

engine\App::router()->callAction();
