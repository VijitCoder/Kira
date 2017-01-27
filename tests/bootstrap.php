<?php
/**
 * Почти копипаст с главного index.php
 *
 * Тут создаем все необходимое, чтобы тестируемые классы приложения могли работать, как ожидается. А вот верны ли
 * ожидания - как раз тесты и покажут.
 */
mb_internal_encoding('UTF-8');

define('KIRA_APP_NAMESPACE', 'app');

define('KIRA_ROOT_PATH', realpath(__DIR__ . '/../') . '/'); // !своя версия
define('KIRA_APP_PATH', KIRA_ROOT_PATH . 'src/');
define('KIRA_VIEWS_PATH', KIRA_APP_PATH . 'views/');
define('KIRA_TEMP_PATH', KIRA_APP_PATH . 'temp/');

define('KIRA_MAIN_CONFIG', KIRA_ROOT_PATH . 'tests/config.php');

define('KIRA_DEBUG', true);

ini_set('display_errors', (int)KIRA_DEBUG);
ini_set('display_startup_errors', (int)KIRA_DEBUG);
error_reporting(KIRA_DEBUG ? E_ALL : 0);

$composer = require KIRA_ROOT_PATH . 'vendor/autoload.php';

// Подключен для примера. Тестам движка не требуется.
require 'callAsPublic/CallAsPublic.php';
