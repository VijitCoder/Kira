<?php
/**
 * Генерация документации движка в составе вендорных библиотек.
 *
 * Из каталога вендоров нельзя получить документацию по версиям, только на основе текущего кода.
 */
use Sami\Sami;
use Sami\RemoteRepository\GitHubRemoteRepository;
use Symfony\Component\Finder\Finder;

$rootDir = realpath(__DIR__ . '/../..');
$sourceDir = $rootDir . '/src';

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->in($sourceDir);

return new Sami($iterator, [
    'theme'                => 'default',
    'title'                => 'Kira API',
    'build_dir'            => $rootDir . '/docs/api/build',
    'cache_dir'            => $rootDir . '/docs/api/cache',
    'remote_repository'    => new GitHubRemoteRepository('VijitCoder/Kira', $rootDir),
     // сколько уровней развернуть сразу в меню навигации по doc API (меню слева)
    'default_opened_level' => 2,
]);
