<?php
/**
 * Маршрутизатор запросов
 */

namespace core;

class Router implements IRouter
{
    /**
     * Парсинг маршрута и вызов соответствующего контроллера.
     */
    public static function parseRoute()
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return self::_notFound();
        }

        $url = explode('?', $_SERVER['REQUEST_URI'])[0]; //берем часть запроса до "?"
        $url = ltrim($url, '/');

        if (!$url) {
            $handler = App::conf('indexHandler');
            list($controller, $action) = self::_parseHandler($handler);
            $controller->$action();
            return;
        }

        $tmp = rtrim($url, '/');
        if ($tmp !== $url) {
            self::_redirect($tmp);
        }

        if (!$set = self::_findRouteFor($url)) {
            return self::_notFound();
        }

        list($ctrl, $action, $params) = $set;
//echo  '<pre>' .var_export($set, true) . '</pre>';//DBG

        if (!$controller = self::_createController($ctrl)) {
           return self::_notFound();
        }

        if (!$action) {
            $action = $controller->defaultAction;
        }

        if (method_exists($controller, $action)) {
            if ($params) {
                $ref_method = new \ReflectionMethod("$ctrl::$action");
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
            return self::_notFound();
        }
    }

    /**
     * Ищем в карте роутов подходящий.
     *
     * Каждый роут принадлежит группе пространства имени. Левая часть роута - заготовка регулярки, правая часть - указание
     * на контроллер/метод. В обеих частях возможны подстановки в угловых скобках вида <var:regexp>.
     *
     * Процесс такой: каждый роут анализируем на подстановки и если надо заменяем на именованные подшаблоны
     * (named subpatterns). После чего реальная регулярка примеряется к URL-y. Если есть совпадение, то при наличии
     * подстановок в описании контроллера/метода заменяем их на соответствующие значения из URL-а. Если подстановки
     * остались, они будут переданы, как параметры метода и продублируются в $_GET на всякий случай.
     *
     * Если подстановок нет, тогда совсем просто. Если подставки слева есть (в заготовке регулярки), а справа нет, тогда
     * всё найденное передается как параметры метода. Если в заготовке подстановок меньше, чем в описании
     * контроллера/метода, вот тогда будет ошибка при попытке вызвать такой "метод".
     *
     * Попутно обрезаются слеши слева в обеих частях роута. Они ненужны, но могут порождать глухие баги.
     *
     * Возвращаем массив: контроллер, метод и параметры для него. Эти же параметры дублируются в $_GET на всякий случай.
     * При слиянии параметров с GET, приоритет у $_GET.
     *
     * @param string $url запрос к серверу, без ведущего слеша и без GET-параметров.
     * @return array
     */
    private static function _findRouteFor($url)
    {
        foreach (App::conf('routes') as $namespace => $routes) {
            foreach ($routes as $from => $to) {
                $from = ltrim($from, '/');
                $pattern = preg_replace('~(<([a-z0-9]+):(.*)>)~U', '(?P<$2>$3)', $from);
                $pattern = "~^$pattern$~Uiu";

                if (preg_match($pattern, $url, $matches)) {
                    break 2;
                }
            }
        }
//echo  '<pre>' .var_export($matches, true) . '</pre>';//DBG
        if (!$matches) {
            return;
        }

        $params = [];
        $to = ltrim($to, '/');
        foreach ($matches as $k => $m) {
            if (is_string($k)) {
                if (strpos($to, "<$k>") !== false) {
                    $to = str_replace("<$k>", $m, $to);
                } else {
                    $params[$k] = $m;
                }
            }
        }

//echo "{$namespace}{$to}";
        $_GET = array_merge($params, $_GET);

        $to = explode('/', $to);
        return [
            $namespace . $to[0],          //FQN controller
            isset($to[1]) ? $to[1] : '',  //action
            $params                       //params
        ];
    }

    /**
     * Из FQN имени класса получаем полный путь к файлу. Если он существует, создаем класс контроллера. Иначе - 404.
     *
     * @param $ctrl
     * @return Controller|null
     */
    private static function _createController($ctrl)
    {
        $pattern = '~^' . str_replace('\\', '/', APP_NS_PREFIX) . '~';
        $str = str_replace('\\', '/', $ctrl);
        $file = preg_replace($pattern, APP_PATH, $str, 1) . '.php';

        return file_exists($file) ? new $ctrl : null;
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
            list($controller, $action) = self::_parseHandler($handler);
            $controller->$action(404);
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
    private static function _parseHandler($handler)
    {
        $handler = explode('->', $handler);
        $controller = (new $handler[0]);
        $action = isset($handler[1]) ? $handler[1] : $controller->defaultAction;
        return [$controller, $action];
    }

    /**
     * Редирект. Только для нужд роутера.
     *
     * Прим.: указание абсолютного URL - это требование спецификации HTTP/1.1,
     * {@see http://php.net/manual/ru/function.header.php}
     *
     * Прим.: Controller имеет аналогичный метод. Но там он используется в публичных целях и по очевидной логике.
     *
     * @TODO Можно добавить логирование таких редиректов для анализа ошибок, неправильных URL-в на страницах сайта и т.п.
     *
     * @param string $uri новый относительный адрес. Всегда без слеша слева, таков тут мой код.
     */
    private static function _redirect($uri)
    {
        $scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];

        if ($_SERVER['QUERY_STRING']) {
            $uri .= '?' . $_SERVER['QUERY_STRING'];
        }

        header("location:{$scheme}://{$host}/{$uri}", true, 301);
        exit;
    }
}
