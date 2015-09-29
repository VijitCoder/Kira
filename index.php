<?php
/**
 * Главный скрипт теста
 */
mb_internal_encoding('UTF-8');

require 'autoloader.php';

//физический путь от корня тома
//прим.: без кроссплатформы. Только unix-пути
define('PATH_ROOT', __DIR__ . '/');

//путь от корня сайта (URI)
define('WEB_ROOT', substr(__DIR__, strrpos(__DIR__, '/')) . '/');

//физический путь к шаблонам
define('PARTH_VIEWS', PATH_ROOT . 'view/');

//URI к css/js
define('URI_ASSETS', WEB_ROOT . 'view/assets/');

define('DEBUG', true);

ini_set('display_errors', DEBUG ? 1 : 0);
ini_set('display_startup_errors', DEBUG ? 1 : 0);
error_reporting(DEBUG ? E_ALL : 0);

//Глобальный перехватчик для исключений, которые не будут пойманы в контексте
set_exception_handler(['App', 'exceptionHandler']);

Router::parseRoute();
