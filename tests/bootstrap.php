<?php
/**
 * Почти копипаст с главного index.php
 *
 * Тут создаем все необходимое, чтобы тестируемые классы приложения могли работать, как ожидается. А вот получится из
 * ожидания - как раз тесты и покажут.
 */
mb_internal_encoding('UTF-8');

define('APP_NAMESPACE', 'app');

define('ROOT_PATH', realpath(__DIR__ . '/../') . '/'); // !своя версия
define('APP_PATH', ROOT_PATH . 'src/');
define('VIEWS_PATH', APP_PATH . 'views/');
define('TEMP_PATH', APP_PATH . 'temp/');

define('MAIN_CONFIG', ROOT_PATH . 'tests/config.php');

define('DEBUG', true);

ini_set('display_errors', (int)DEBUG);
ini_set('display_startup_errors', (int)DEBUG);
error_reporting(DEBUG ? E_ALL : 0);

$composer = require ROOT_PATH . 'vendor/autoload.php';
