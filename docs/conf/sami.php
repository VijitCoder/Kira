<?php
/**
 * Генерация документации для исходного проекта движка
 *
 * Генерация по версиям глючная, лучше не пользоваться.
 *
 * Для версионной генерации нужно коммитить все изменения, генератор будет переключать ветки.
 */
use Sami\Sami;
use Sami\RemoteRepository\GitHubRemoteRepository;
use Sami\Version\GitVersionCollection;
use Symfony\Component\Finder\Finder;

$rootDir = realpath(__DIR__ . '/../..');
$sourceDir = $rootDir . '/src';

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->in($sourceDir);

/*
// Глючная фича
$versions = GitVersionCollection::create($sourceDir)
    ->addFromTags('v1.4')
    //->add('master', 'master branch')
    ->add('development', 'dev branch');
*/

return new Sami($iterator, [
    'theme'                => 'default',
    //'versions'             => $versions,
    'title'                => 'Kira API',
    'build_dir'            => $rootDir . '/docs/api/build/%version%',
    'cache_dir'            => $rootDir . '/docs/api/cache/%version%',
    'remote_repository'    => new GitHubRemoteRepository('VijitCoder/Kira', $rootDir),
     // сколько уровней развернуть сразу в меню навигации по doc API (меню слева)
    'default_opened_level' => 2,
]);
