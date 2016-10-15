<?php
/**
 * Главный скрипт приложения 'app'
 */
mb_internal_encoding('UTF-8');

define('APP_NAMESPACE', 'app');

define('ROOT_PATH', str_replace('\\', '/', rtrim(__DIR__, '/')) . '/');
define('APP_PATH', ROOT_PATH . 'application/');
define('VIEWS_PATH', APP_PATH . 'views/');
define('TEMP_PATH', APP_PATH . 'temp/');

define('MAIN_CONFIG', APP_PATH . 'conf/main.php');

define('DEBUG', true);

ini_set('display_errors', (int)DEBUG);
ini_set('display_startup_errors', (int)DEBUG);
error_reporting(DEBUG ? E_ALL : 0);

$composer = require ROOT_PATH . 'vendor/autoload.php';

date_default_timezone_set(kira\App::conf('timezone'));

kira\App::router($composer)->callAction();
