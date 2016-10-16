<?php
/**
 * Главный скрипт приложения 'app'
 */
use kira\core\App;

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
App::setComposer($composer);
unset($composer);

date_default_timezone_set(App::conf('timezone'));

App::router()->callAction();
