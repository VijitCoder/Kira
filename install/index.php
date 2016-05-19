<?php
/**
 * Приложение по созданию нового приложения :)
 */

mb_internal_encoding('UTF-8');

$dir = rtrim(__DIR__, '/');
$dir = str_replace('\\', '/', $dir); // кроссплатформа
$dir = realpath($dir . '/..');
define('ROOT_PATH', $dir . '/');
define('APP_PATH', ROOT_PATH . 'install/');
define('VIEWS_PATH', APP_PATH . 'static/');
define('MAIN_CONFIG', APP_PATH . 'app/config.php');
define('APP_NS_PREFIX', 'install\\');

define('DEBUG', true);

ini_set('display_errors', (int)DEBUG);
ini_set('display_startup_errors', (int)DEBUG);
error_reporting(DEBUG ? E_ALL : 0);

require ROOT_PATH . 'engine/autoloader.php';

engine\App::router()->callAction();
