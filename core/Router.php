<?php
/**
 * Маршрутизатор запросов
 */

namespace core;

use utils\Dumper;

class Router implements IRouter
{
    /**
     * Парсинг URL и вызов action-метода в соответствующем контроллере.
     *
     * @TODO это хреновое решение, нужно придумать лучше.
     * Если "HTTP status code" в списке тех кодов ошибок, на которые веб-сервер может навешать хендлер, то сразу передаем
     * управление в него. Например, для Apache это "ErrorDocument". Для других веб-серверов тоже есть подобная возможность.
     * При этом, чтобы там ни было прописано у сервера, запрос получит тот контроллер, который указан в конфиге
     * 'errorHandler'. И это - тоже слабая часть решения, т.к. эта настройка - необязательна.
     */
    public function callAction()
    {
        $code = http_response_code();
//        if (in_array($code, [401, 403, 404, 500])) {
        if ($code >= 400) {
            return $this->_notFound($code);
        }

        if (!isset($_SERVER['REQUEST_URI'])) {
            return $this->_notFound();
        }

        $url = explode('?', $_SERVER['REQUEST_URI'])[0]; //берем часть запроса до "?"
        $url = urldecode(ltrim($url, '/'));

        if (!$url) {
            $handler = App::conf('indexHandler');
            list($controller, $action) = $this->_parseHandler($handler);
            $controller->$action();
            return;
        }

        $tmp = rtrim($url, '/');
        if ($tmp !== $url) {
            $this->_redirect($tmp);
        }

        if (!$set = $this->_findRouteFor($url)) {
            return $this->_notFound();
        }

        list($ctrlName, $action, $params) = $set;
//dd($set);//DBG

        if (!$controller = $this->_createController($ctrlName)) {
            return $this->_notFound();
        }

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
    private function _findRouteFor($url)
    {
//echo $url;
        foreach (App::conf('routes') as $namespace => $routes) {
            foreach ($routes as $left => $right) {
                $left = ltrim($left, '/');
                $pattern = preg_replace('~(<([a-z0-9_]+):(.*)>)~Ui', '(?P<$2>$3)', $left);
                $pattern = "~^$pattern$~Uiu";

                if (preg_match($pattern, $url, $matches)) {
                    break 2;
                }
            }
        }
//dd($matches);//DBG
        if (!$matches) {
            return;
        }

        $params = [];
        $right = ltrim($right, '/');
        foreach ($matches as $k => $m) {
            if (is_string($k)) {
                if (strpos($right, "<$k>") !== false) {
                    $right = str_replace("<$k>", $m, $right);
                } else {
                    $params[$k] = $m;
                }
            }
        }

//echo "{$namespace}{$right}";
        $_GET = array_merge($params, $_GET);

        $right = explode('/', $right);
        return [
            $namespace . $right[0],            //FQN controller
            isset($right[1]) ? $right[1] : '', //action
            $params                            //params
        ];
    }

    /**
     * Из FQN имени класса получаем полный путь к файлу. Если он существует, создаем класс контроллера. Иначе - 404.
     *
     * @param $ctrl
     * @return Controller|null
     */
    private function _createController($ctrl)
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
     * @param int $code код HTTP-статуса
     * @throws \Exception
     */
    private function _notFound($code = 404)
    {
        http_response_code($code);
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
    private function _redirect($uri)
    {
        $scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];

        if ($_SERVER['QUERY_STRING']) {
            $uri .= '?' . $_SERVER['QUERY_STRING'];
        }

        header("location:{$scheme}://{$host}/{$uri}", true, 301);
        exit;
    }

    /**
     * Построение URL по описанию, используя карту роутов.
     *
     * Перебираем роуты в заданном пространстве имен. Сравниваем указанные контроллер/действие с правой частью роута.
     * Если нашли совпадение справа (будет массив), анализируем правило слева, выбирая необходимые подстановки -
     * "required params". Если сможем их обеспечить из массива совпадения и/или переданных параметров - роут найден.
     * Иначе продолжаем поиск.
     *
     * Все параметры, которые не пойдут в подстановки, допишутся в URL в качестве строки запроса. Если у элемента
     * не задано значение, а есть только ключ, типа ['p' => null], он попадет в URL без значения.
     *
     * Прим: поиск роута не зависит от регистра. Это немного упрощает требования к описанию параметров функции.
     *
     * Если ничего не найдем, проброс ошибки. Вообще ситуация некритичная, но иначе можно прозевать исчезновение роута
     * и получить битую ссылку.
     *
     * @param mixed $route  массив 2-х элементов ["пространство имен", "правая часть из описания роута"]
     * @param array $params доп.параметры для передачи в адрес. Ассоциативный массив ['имя параметра' => 'значение']
     * @return string готовый <b>относительный</b> URL
     * @throw RangeException
     */
    public function url($route, array $params = [])
    {
        list($ns, $ctrl) = $route;
        $ctrl = trim($ctrl, '/');

        $routes = App::conf('routes');
        if (!isset($routes[$ns])) {
            throw new \RangeException("Нет карты роутов для пространства имен '$ns'");
        }

        foreach ($routes[$ns] as $left => $right) {
            $left = ltrim($left, '/');
            $right = ltrim($right, '/');
            $pattern = preg_replace('~(<(.+)>)~U', '(?P<$2>[^/]+)', $right);
            $pattern = "~^$pattern$~Ui";

            if (preg_match($pattern, $ctrl, $match)) {
                if (preg_match_all('~<([a-z0-9_]+):.+>~U', $left, $requiredParams)) {

                    $requiredParams = array_flip($requiredParams[1]);

                    foreach ($requiredParams as $k => &$v) {
                        if (isset($match[$k])) {
                            $v = $match[$k];
                        } else if (isset($params[$k])) {
                            $v = $params[$k];
                            unset($params[$k]); //удаляем из параметров то, что будет использовано в подстановке
                        } else {
                            continue 2;
                        }
                    }
                    unset($v);
                } else {
                    $requiredParams = [];
                }

                break;
            }
        }
//dd($left, $match);//DBG
        if ($match) {
            if ($requiredParams) {
                $placeholders = [];
                foreach ($requiredParams as $key => $v) {
                    $placeholders[] = "~(<$key:.+>)~U";
                }
                $left = preg_replace($placeholders, $requiredParams, $left);
            }

            if (preg_match('~[^/a-z0-9_-]~i', $left)) {
                $arr = explode('/', $left);
                $arr = array_map('urlencode', $arr);
                $left = implode('/', $arr);
            }

            $url = '/' . $left;

            if ($params) {
                foreach ($params as $k => &$v) {
                    $v = urlencode($k) . ($v ? '=' . urlencode($v) : '');
                }
                $url .= '?' . implode('&', $params);
            }

            return $url;
        } else {
            $strParams = count($params) ? 'параметры ' . Dumper::dumpAsString($params, 1, false) : 'без параметров';
            throw new \RangeException("не могу построить URL по заданным значениям: array('$ns', '$ctrl'), $strParams");
        }
    }
}
