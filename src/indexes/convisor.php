#!/usr/bin/php
<?php
/**
 * Главный скрипт консольного запуска скриптов приложения "Convisor"
 * Полный формат запуска:
 *
 *    php convisor.php <script> <param1> ... <paramN> -k <key>
 */
use kira\core\App;
use kira\core\Convisor;
use kira\web\Env;

mb_internal_encoding('UTF-8');

define('KIRA_APP_NAMESPACE', 'app');

define('KIRA_ROOT_PATH', rtrim(str_replace('\\', '/', __DIR__), '/') . '/');
define('KIRA_APP_PATH', KIRA_ROOT_PATH . 'application/');
define('KIRA_TEMP_PATH', KIRA_APP_PATH . 'temp/');
define('KIRA_MAIN_CONFIG', KIRA_APP_PATH . 'conf/main.php');

$composer = require KIRA_ROOT_PATH . 'vendor/autoload.php';
App::setComposer($composer);
unset($composer);

define('KIRA_DEBUG', Env::isLocal()); // перепишите на свой Env, если есть его реализация

ini_set('display_errors', (int)KIRA_DEBUG);
ini_set('display_startup_errors', (int)KIRA_DEBUG);
error_reporting(KIRA_DEBUG ? E_ALL : 0);

date_default_timezone_set(App::conf('timezone'));

new Convisor($argv);
