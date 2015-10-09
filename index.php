<?php
/**
 * Главный скрипт
 */
mb_internal_encoding('UTF-8');

//физический путь от корня тома
//прим.: без кроссплатформы. Только unix-пути
define('PATH_ROOT', __DIR__ . '/');

//путь от корня сайта (URI)
//@DEPRECATED ?
define('WEB_ROOT', substr(__DIR__, strrpos(__DIR__, '/')) . '/');

//физический каталог со скриптами приложения
define('PATH_APP', PATH_ROOT . 'application/');

//физический путь к шаблонам
define('PARTH_VIEWS', PATH_ROOT . 'view/');

//URI к css/js
define('URI_ASSETS', '/view/assets/');

define('DEBUG', true);

ini_set('display_errors', (int)DEBUG);
ini_set('display_startup_errors', (int)DEBUG);
error_reporting(DEBUG ? E_ALL : 0);

require 'core/autoloader.php';

Router::parseRoute();
