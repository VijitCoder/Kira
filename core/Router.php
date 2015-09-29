<?php
/**
 * Маршрутизатор запросов
 */
class Router
{
    /**
     * Парсинг маршрута и передача дальшейшего выполнения приложения
     * Подобие маршрутизатора. Поддерживаем только необходимые в задаче роуты, по умолчанию 'login/index'
     * Ожидаем запросы вида controller/action. Обе части могут быть не заданы, тогда работаем по умолчанию.
     *
     * @TODO с GET-параметрами роутер работает неправильно, нужно допиливать
     */
    public static function parseRoute()
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = explode('?', $_SERVER['REQUEST_URI'])[0]; //берем часть запроса до "?"

            //Исключительно для тестовой задачи: вход в корень раздела /users редиректит на страницу
            //входа в ЛК. В реальности должна быть главная страница сайта.
            if ($uri == WEB_ROOT) {
                self::_redirect(AuthService::checkAccess() ? 'profile' : 'login');
            }

            //слеш в конце -=> редирект на адрес без слеша
            $tmp = rtrim($uri, '/');
            if ($tmp !== $uri) {
                self::_redirect($tmp);
            }

            //Корень тестовой задачи не совпадет с реальным корнем сайта. Нужно подправлять, чтоб
            //тест работал в таком окружении.
            $uri = str_replace(WEB_ROOT, '', $uri);

            $uri = explode('/', $uri);

            //валидация, приведение URL-а к норме
            $controller = preg_replace('~[^a-z]~i', '', $uri[0]);
            $action = isset($uri[1]) ? preg_replace('~[^a-z]~i', '', $uri[1]) : '';
            if ($controller != $uri[0] || ($action && $action != $uri[1])) {
                self::_redirect(rtrim("{$controller}/{$action}", '/'));
            }

            $controller = ucfirst($controller) . 'Controller';
            if (!$action) {
                $action =  'index';
            }

            //if (in_array($controller, ['login', 'registration', 'profile', 'logout'])) {

            if (file_exists(PATH_ROOT . "controller/{$controller}.php")) {
                $controller = new $controller;

                if (method_exists($controller, $action)) {
                    $controller->$action();
                } else {
                    AppController::error404();
                }
            } else {
                AppController::error404();
            }
        } else {
            AppController::error404();
        }
    }

    /**
     * Редирект. Только для нужд роутера. Можно добавить логирование таких редиректов для анализа ошибок,
     * неправильных URL-в на страницах сайта и т.п.
     *
     * Прим.: AppController имеет аналогичный метод. Но там он используется в публичных целях и по очевидной логике.
     */
    private static function _redirect($url)
    {
        if ($_SERVER['QUERY_STRING']) $url .= '?' . $_SERVER['QUERY_STRING'];
        header("location:{$url}");
        exit;
    }
}
