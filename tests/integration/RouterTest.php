<?php
use PHPUnit\Framework\TestCase;
use kira\net\Router;
use kira\core\App;

/**
 * Тестируем роутер движка
 *
 * Полноценные тесты не получатся, проверяем только верное определение маршрута по заданному URL и обратное решение.
 * Более того, получение маршрута непосредственно связанно с конфигом роутов. Эту зависимость нельзя исключить.
 */
class RouterTest extends TestCase
{
    /**
     * Провайдер для теста "получениен роута по ссылке"
     * @return array
     */
    public function url2RouteProvider()
    {
        return [
            // пространство имен: app\admin\controllers

            'новая запись'          => ['adm/edit', 'app\admin\controllers\EditController', 'new'],
            'редактирование записи' => ['adm/edit/12', 'app\admin\controllers\EditController', '', ['id' => 12]],

            // пространство имен: app\modules\user\controllers

            'вход'            => ['login', 'app\modules\user\controllers\AuthController', 'login'],
            'вход #2'         => ['login/vijit', 'app\modules\user\controllers\AuthController', 'loginUser',
                ['user' => 'vijit']],
            'кириллица в URL' => ['вход/на/сайт', 'app\modules\user\controllers\AuthController', 'login'],

            // пространство имен: app\controllers

            'action с параметром' => ['about', 'app\controllers\StaticController', 'show', ['page' => 'about']],

            'конкретный action'      => ['news/recent/5', 'app\controllers\News', 'slice', ['cnt' => 5]],
            'произвольный action #1' => ['news/preview/18', 'app\controllers\News', 'preview', ['id' => 18]],
            'произвольный action #2' => ['news/details/12_birdie', 'app\controllers\News', 'details', ['id' => 12]],

            'список без пагинации' => ['articles/life', 'app\controllers\Story', 'list', ['topic' => 'life']],
            'список с пагинацией'  => ['articles/life/page/2', 'app\controllers\Story', 'list',
                ['topic' => 'life', 'page' => 2]],

            'прямое соответствие URL <-> роут' => ['main/index', 'app\controllers\main', 'index'],
        ];
    }

    /**
     * Нахождение маршрута по URL
     * @dataProvider url2RouteProvider
     * @param       $url
     * @param       $expectCtrl
     * @param       $expectAction
     * @param array $expectParams
     */
    public function test_findRouteFor($url, $expectCtrl, $expectAction, $expectParams = [])
    {
        list($ctrlName, $action, $params) = App::router()->findRouteFor($url);
        $this->assertEquals($expectCtrl, $ctrlName);
        $this->assertEquals($expectAction, $action);
        $this->assertEquals($expectParams, $params);
    }

    /**
     * Провайдер для теста "Получение ссылки по роуту"
     * @return array
     */
    public function route2UrlProvider()
    {
        return [
            'URL, новая запись'          => ['/adm/edit', ['app\admin\controllers', 'EditController/new']],
            'URL, редактирование записи' => ['/adm/edit/12', ['app\admin\controllers', 'EditController'], ['id' => 12]],

            'URL, вход'            => ['/login', ['app\modules\user\controllers', 'AuthController/login']],
            'URL, вход, кириллица' => ['/%D0%B2%D1%85%D0%BE%D0%B4/%D0%BD%D0%B0/%D1%81%D0%B0%D0%B9%D1%82',
                ['app\hacks', 'AuthController/login']], // используем хак. URL кодируется
            'URL, вход #2'         => ['/login/vijit', ['app\modules\user\controllers', 'AuthController/loginUser'],
                ['user' => 'vijit']],

            'URL, произвольный action #1' => ['/news/preview/12', ['app\controllers', 'News/preview'], ['id' => 12]],

            'URL, список без пагинации' => ['/articles/life', ['app\controllers', 'Story/list'], ['topic' => 'life']],
            'URL, список с пагинацией'  => ['/articles/life/page/2', ['app\controllers', 'Story/list'],
                ['topic' => 'life', 'page' => 2]],

            'URL, поиск с GET параметрами' => ['/news/search?q=birdie&last=4', ['app\controllers', 'News/search'], [
                'q' => 'birdie', 'last' => 4]],
        ];
    }

    /**
     * Получение URL по заданному маршруту
     * @dataProvider route2UrlProvider
     * @param string $expectUrl
     * @param array  $route
     * @param array  $params
     */
    public function test_url($expectUrl, $route, $params = [])
    {
        $url = App::router()->url($route, $params);
        $this->assertEquals($expectUrl, $url);
    }
}
