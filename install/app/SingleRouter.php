<?php

namespace install\app;

class SingleRouter implements \engine\IRouter
{
    /**
     * Парсинг URL и вызов action-метода в соответствующем контроллере.
     * @return void
     */
    public function callAction()
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            $this->throwError('неизвестный запрос');
        }

        $url = explode('?', $_SERVER['REQUEST_URI'])[0];
        $url = trim($url, '/');
        $routes = \engine\App::conf('routes');
        $routes = array_flip($routes);

        if (!$route = array_search($url, $routes)) {
            $this->throwError('роут не найден');
        }

        $route = explode('/', $route);
        $controller = APP_NAMESPACE . 'app\\' . $route[0];
        $action = $route[1];

        (new $controller)->$action();
    }

    /**
     * Построение URL по описанию.
     * В этом приложении нет такой необходимости. Поэтому нет реализации.
     * @param mixed $route
     * @param array $params
     * @return string
     */
    public function url($route, array $params = [])
    {
    }

    /**
     * Не нашли роут. Отвечаем сообщением об ошибке.
     * @param $msg
     */
    private function throwError($msg)
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        exit($msg);
    }
}
