<?php
/**
 * Приложение по созданию нового приложения :)
 */

mb_internal_encoding('UTF-8');

// Особое вычисление корневого каталога. ROOT_PATH будет реальным корнем сайта, т.е. на каталог выше текущего.
$dir = rtrim(__DIR__, '/');
$dir = str_replace('\\', '/', $dir); // кроссплатформа
$dir = realpath($dir . '/..');
define('ROOT_PATH', $dir . '/');
define('APP_NAMESPACE', 'install\\');
define('APP_PATH', ROOT_PATH . 'install/');
define('TEMP_PATH', APP_PATH);
define('VIEWS_PATH', APP_PATH . 'static/views/');
define('MAIN_CONFIG', APP_PATH . 'app/config.php');

define('DEBUG', true);

ini_set('display_errors', (int)DEBUG);
ini_set('display_startup_errors', (int)DEBUG);
error_reporting(DEBUG ? E_ALL : 0);

require ROOT_PATH . 'engine/autoloader.php';

engine\App::router()->callAction();
