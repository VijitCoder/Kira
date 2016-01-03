<?php
/**
 * Маршрутизатор запросов
 */

namespace core;

//use core\Controller;

class Router
{
    /**
     * Парсинг маршрута и передача дальшейшего выполнения приложения
     * Ожидаем запросы вида controller/action. Обе части могут быть не заданы, тогда работаем
     * по умолчанию.
     */
    public static function parseRoute()
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = explode('?', $_SERVER['REQUEST_URI'])[0]; //берем часть запроса до "?"
            $uri = ltrim($uri, '/');

            //обращение к корню сайта
            if ($uri === '') {
                $controller = App::conf('defaultController');
                $action = '';
            } else {
                //слеш в конце -=> редирект на адрес без слеша
                $tmp = rtrim($uri, '/');
                if ($tmp !== $uri) {
                    self::_redirect($tmp);
                }

                $uri = explode('/', $uri);

                $controller = ucfirst($uri[0]) . 'Controller';
                $action = isset($uri[1]) ? $uri[1] : '';
                //валидация
                if (!preg_match('~^[a-z]+$~i', $controller) || !preg_match('~^[a-z]*$~i', $action)) {
                    Controller::error404();
                }
            }

//exit("$controller*$action*"); //DBG
            if (file_exists(APP_PATH . "controller/{$controller}.php")) {
                $controller = new $controller;
                if (!$action) {
                    $action = $controller->defaultAction;
                }
                if (method_exists($controller, $action)) {
                    $controller->$action();
                } else {
                    Controller::error404();
                }
            } else {
                Controller::error404();
            }
        } else {
            Controller::error404();
        }
    }

    /**
     * Редирект. Только для нужд роутера.
     *
     * Прим.: указание абсолютного URI - это требование спецификации HTTP/1.1,
     * {@see http://php.net/manual/ru/function.header.php}
     * Прим.: Controller имеет аналогичный метод. Но там он используется в публичных целях и по
     * очевидной логике.
     *
     * @TODO Можно добавить логирование таких редиректов для анализа ошибок, неправильных URL-в на
     * страницах сайта и т.п.
     *
     * @param string $uri новый относительный адрес. Всегда без слеша слева, таков тут мой код.
     */
    private static function _redirect($uri)
    {
        $scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];

        if ($_SERVER['QUERY_STRING'])
            $uri .= '?' . $_SERVER['QUERY_STRING'];

        header("location:{$scheme}://{$host}/{$uri}", true, 301);
        exit;
    }
}
