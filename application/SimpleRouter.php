<?php
/**
 * Пример перекрытия роутера движка.
 *
 * Этот роутер проще, работает с контроллерами в конкретном каталоге, может разобрать запросы типа
 *   /controller
 *   /controller/action
 *   /controller/action/pd/NN/ps/WWW
 *   /controller/action/pd/NN/odd
 *
 * В именах контроллеров и URL-ах нужно учитывать регистр. Имя контроллера в адресе пишем <b>полностью</b>.
 *
 * Практические примеры использования такого роутера:
 * - mod_rewite уже разобрал маршрут и передал сюда /controller/action...
 * - сайт-визитка. 3-4 простых URL-а
 */

namespace app;

use engine\App;

class SimpleRouter implements \engine\IRouter
{
    //Пространство имен контроллеров. Без учета префикса приложения APP_NS_PREFIX.
    const CTRL_NS = 'controllers\\';

    /**
     * Парсинг маршрута и вызов соответствующего контроллера.
     */
    public function callAction()
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return $this->_notFound();
        }

        $url = explode('?', $_SERVER['REQUEST_URI'])[0]; //берем часть запроса до "?"
        $url = ltrim($url, '/');

        if (!$url) {
            $handler = App::conf('indexHandler');
            list($controller, $action) = $this->_parseHandler($handler);
            $controller->$action();
            return;
        }

        $parsedUrl = $this->_parseUrl($url);
        $ctrlName = APP_NS_PREFIX . self::CTRL_NS . $parsedUrl['controller'];
        $action = $parsedUrl['action'];
        $params = $parsedUrl['params'];

        $controller = $this->_createController($ctrlName);

        if (!$action) {
            $action = $controller->defaultAction;
        }

        if (method_exists($controller, $action)) {
            if ($params) {
                $ref_method = new \ReflectionMethod("$ctrlName::$action");
                $funcParams = [];
                foreach ($ref_method->getParameters() as $v) {
                    $k = $v->name;
                    if (isset($params[$k])) {
                        $funcParams[$k] = $params[$k];
                    }
                }
                call_user_func_array([$controller, $action], $funcParams);
            } else {
                $controller->$action();
            }
        } else {
            return $this->_notFound();
        }
    }

    /**
     * Из FQN имени класса получаем полный путь к файлу. Если он существует, создаем класс контроллера. Иначе - 404.
     *
     * @param $ctrl
     */
    private function _createController($ctrl)
    {
        $pattern = '~^' . str_replace('\\', '/', APP_NS_PREFIX) . '~';
        $str = str_replace('\\', '/', $ctrl);
        $file = preg_replace($pattern, APP_PATH, $str, 1) . '.php';

        return file_exists($file) ? new $ctrl : $this->_notFound();
    }

    /**
     * Парсим запрос к серверу.
     *
     * Если есть конечный слеш - редиректим на адрес без него.
     *
     * Первая часть до слеша - контроллер, потом действие, остальное - параметры. Полученные параметры объединяются в
     * GET-параметрами, причем у GET приоритет.
     *
     * @param string $url запрос к серверу, без ведущего слеша и без GET-параметров.
     * @return array ['controller', 'action', 'params']
     */
    private function _parseUrl($url)
    {
        $url = explode('/', $url);
        $controller = array_shift($url);
        $action = array_shift($url);
//dd($controller, $action);//DBG
        if (!preg_match('~^[a-z]+$~i', $controller) || !preg_match('~^[a-z]*$~i', $action)) {
            return $this->_notFound();
        }

        if ($url) {
            $cnt = count($url);
            //если нечетное количество, считаем последний элемент параметром без данных. Сразу его забираем.
            if ($cnt & 1) {
                $params = [array_pop($url) => null];
                $cnt--;
            } else {
                $params = [];
            }
            for ($i = 0; $i < $cnt; $i = $i + 2) {
                $params[$url[$i]] = $url[$i + 1];
            }

            $_GET = array_merge($params, $_GET);
        } else {
            $params = [];
        }

        return compact('controller', 'action', 'params');
    }

    /**
     * Контроллер не найден, нужно ответить юзеру страницей 404.
     *
     * В конфиге должен быть прописан контроллер, который будет обрабатывать 404 ошибку. Если он не задан (пустая строка),
     * просто возвращаем заголовок.
     *
     * @return void
     */
    private function _notFound()
    {
        http_response_code(404);
        if ($handler = App::conf('errorHandler', false)) {
            list($controller, $action) = $this->_parseHandler($handler);
            $controller->$action();
        }
    }

    /**
     * Разбираем контроллер и метод, прописанные в конфиге.
     *
     * @TODO нужен контроль ошибки, если в конфиге фигня записана.
     *
     * @param $handler
     * @return array
     */
    private function _parseHandler($handler)
    {
        $handler = explode('->', $handler);
        $controller = (new $handler[0]);
        $action = isset($handler[1]) ? $handler[1] : $controller->defaultAction;
        return [$controller, $action];
    }

    /**
     * Построение URL по описанию.
     *
     * Никаких хитростей, все параметры дописываются в адрес через слеши.
     *
     * @param mixed $route строка 'controller/action'
     * @param array $params доп.параметры для передачи в адрес. Ассоциативный массив ['имя параметра' => 'значение']
     * @return string готовый <b>относительный</b> URL
     */
    public function url($route, array $params = [])
    {
        if ($params) {
            foreach ($params as $k => &$v) {
                $v = urlencode($k) . ($v ? '/' . urlencode($v) : '');
            }
            $route .= '/' . implode('/', $params);
        }
        return $route;
    }
}
