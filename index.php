<?php
/**
 * Главный скрипт
 */
mb_internal_encoding('UTF-8');

//физический путь от корня тома. Гарантированный завершающий слеш. Кроссплатформа. Приводим пути к unix-стилю.
define('ROOT_PATH', str_replace('\\', '/', rtrim(__DIR__, '/')) . '/');

//Относительный URL корня сайта, типа '/kira/'.
//@TODO убрать. Данные можно получить из $_SERVER, в классе Utils будет реализован метод получения значений
//из этой переменной.
//@DEPRECATED Использовался, когда сайт на этом движке был лишь частью другого сайта.
//define('ROOT_URL', substr(__DIR__, strrpos(__DIR__, '/')) . '/');


//Префикс пространства имен приложения. Используется в автозагрузчике. Все классы приложения, должны быть
//в его подпространстве.
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

require 'core/autoloader.php';

$router = core\App::conf('router', false) ? : 'core\Router';
$router::parseRoute();
