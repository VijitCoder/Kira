<?php
/**
 * Пример перекрытия роутера движка.
 *
 * Этот роутер проще, работает с контроллерами в конкретном каталоге, может разобрать запросы типа /controller/action?q=..
 * и все на этом. Практический пример: mod_rewite уже разобрал маршрут и передал сюда /controller/action?q=...
 *
 * @TODO сейчас это практически копипаст c роутера движка. Нужно максмимально упростить для примера. Параметры в методы
 * не передаем. Только контроллер/действие, остальное в $_GET. Цель такого роутера - сайт-визитка. 3-4 простых URL-а.
 */

namespace app;

class ExampleRouter implements \core\IRouter
{
    ////Контроллер по умолчанию. В принципе может несуществовать или прописан в настройках.
    //const DEFAULT_CTRL = 'MainController';

    /**
     * Парсинг маршрута и вызов соответствующего контроллера.
     */
    public static function callAction()
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return self::_notFound();
        }

        $parsedUrl = self::_parseUrl();
        $controller = APP_NS_PREFIX . 'controllers\\' . $parsedUrl['controller'];
        $action = $parsedUrl['action'];

        $controller = self::_createController($controller);

        if (!$action) {
            $action = $controller->defaultAction;
        }

        if (method_exists($controller, $action)) {
            $controller->$action();
        } else {
            return self::_notFound();
        }
    }

    /**
     * Из FQN имени класса получаем полный путь к файлу. Если он существует, создаем класс контроллера. Иначе - 404.
     *
     * @param $ctrl
     */
    private static function _createController($ctrl)
    {
        $pattern = '~^' . str_replace('\\', '/', APP_NS_PREFIX) . '~';
        $str = str_replace('\\', '/', $ctrl);
        $file = preg_replace($pattern, APP_PATH, $str, 1) . '.php';

        return file_exists($file) ? new $ctrl : self::_notFound();
    }

    /**
     * Парсим запрос к серверу.
     *
     * Если есть конечный слеш - редиректим на адрес без него.
     *
     * Первая часть до слеша - контроллер, потом действие, остальное - параметры. Полученные параметры объединяются в
     * GET-параметрами, причем у GET приоритет.
     *
     * @return array
     */
    private static function _parseUrl()
    {
        $url = explode('?', $_SERVER['REQUEST_URI'])[0]; //берем часть запроса до "?"
        $url = ltrim($url, '/');

        if ($url === '') {
            $controller = App::conf('defaultController');
            $action = '';
            $params = [];
        } else {
            $url = explode('/', $url);
            $controller = array_shift($url);
            $action = array_shift($url);

            if (!preg_match('~^[a-z]+$~i', $controller) || !preg_match('~^[a-z]*$~i', $action)) {
                return self::_notFound();
            }
        }

        return compact('controller', 'action');
    }

    /**
     * Контроллер не найден, нужно ответить юзеру страницей 404.
     *
     * В конфиге должен быть прописан контроллер, который будет обрабатывать 404 ошибку. Если он не задан (пустая строка),
     * просто возвращаем заголовок.
     *
     * @return void
     */
    private static function _notFound()
    {
        header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');

        if ($handler = App::conf('errorHandler', false)) {
            $handler = explode('->', $handler);
            $controller = (new $handler[0]);
            $action = isset($handler[1]) ? $handler[1] : $controller->defaultAction;

            $controller->$action(404);
        }
    }

    /**
     * Построение URL по параметрам.
     *
     * @param mixed $route какое-то определение роута. Например controller/action
     * @param array $params параметры для левой части роута
     * @return string готовый <b>относительный</b> URL
     */
    public static function url($route, array $params = [])
    {}
}