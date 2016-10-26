#!/usr/bin/php
<?php
/**
 * Главный скрипт консольного запуска скриптов приложения "Convisor"
 * Полный формат запуска:
 *
 *    php convisor.php <script> <param1> ... <paramN> -k <key>
 */
mb_internal_encoding('UTF-8');

define('APP_NAMESPACE', 'app');

define('ROOT_PATH', str_replace('\\', '/', rtrim(__DIR__, '/')) . '/');
define('APP_PATH', ROOT_PATH . 'application/');
define('TEMP_PATH', APP_PATH . 'temp/');
define('MAIN_CONFIG', APP_PATH . 'conf/main.php');

require ROOT_PATH . 'vendor/autoload.php';

define('DEBUG', kira\web\Env::isLocal()); // перепишите на свой Env, если есть его реализация

ini_set('display_errors', (int)DEBUG);
ini_set('display_startup_errors', (int)DEBUG);
error_reporting(DEBUG ? E_ALL : 0);

date_default_timezone_set(kira\core\App::conf('timezone'));

new kira\core\Convisor($argv);
