<?php
/**
 * Маршрутизатор запросов
 */

namespace engine\net;

use engine\App;

class Router implements \engine\IRouter
{
    /**
     * Парсинг URL и вызов action-метода в соответствующем контроллере.
     *
     * Когда сервер Apache передает ошибки >=401 (см. Apache::ErrorDocument), он добавляет свой заголовок REDIRECT_URL.
     * Там указан адрес, назначенный сервером для обработки таких ошибок. В таком случае обрабатываем именно этот адрес,
     * а не REQUEST_URI.
     */
    public function callAction()
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return $this->notFound();
        }

        if (http_response_code() > 400 && isset($_SERVER['REDIRECT_URL'])) {
            $url = $_SERVER['REDIRECT_URL'];
        } else {
            $url = explode('?', $_SERVER['REQUEST_URI'])[0];
        }

        $url = urldecode(ltrim($url, '/'));

        if (!$url) {
            $handler = App::conf('indexHandler');
            list($controller, $action) = $this->parseHandler($handler);
            $controller->$action();
            return;
        }

        $tmp = rtrim($url, '/');
        if ($tmp !== $url) {
            $this->redirect($tmp);
        }

        if (!$set = $this->findRouteFor($url)) {
            return $this->notFound();
        }

        list($ctrlName, $action, $params) = $set;
//dd($set);//DBG

        if (!$controller = $this->createController($ctrlName)) {
            return $this->notFound();
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
            return $this->notFound();
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
    private function findRouteFor($url)
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

//echo "{$namespace}\\{$right}";
        $_GET = array_merge($params, $_GET);

        $right = explode('/', $right);
        return [
            $namespace . '\\' . $right[0],     //FQN controller
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
    private function createController($ctrl)
    {
        $pattern = '~^' . str_replace('\\', '/', APP_NAMESPACE) . '~';
        $str = str_replace('\\', '/', $ctrl);
        $file = preg_replace($pattern, APP_PATH, $str, 1) . '.php';

        return file_exists($file) ? new $ctrl : null;
    }


    /**
     * Контроллер не найден, нужно ответить юзеру страницей 404.
     *
     * В конфиге должен быть прописан контроллер, который будет обрабатывать 404 ошибку. Если он не задан (пустая
     * строка), возвращаем 404-й заголовок и простой текст.
     *
     * Предохранитель для кодера: допустим, веб-сервер Apache и задан ErrorDocument. Но указанный адрес роутеру
     * неизвестен. Тогда роутер попытается ответить 404 (т.е. сюда попадет). А мы при этом реальный ответ веб-сервера
     * не подменяем и возвращаем текст по его коду.
     *
     * @throws \Exception
     */
    private function notFound()
    {
        // Предохранитель
        $code = http_response_code();
        if ($code > 400) {
            $msg = $code . ': ' . Response::textOf($code);
            if (isset($_SERVER['REDIRECT_URL'])) {
                $msg .= '<br>URL: ' . $_SERVER['REDIRECT_URL'];
            }
            return Response::send($msg);
        }

        http_response_code(404);

        if ($handler = App::conf('errorHandler', false)) {
            list($controller, $action) = $this->parseHandler($handler);
            $controller->$action();
        } else {
            Response::send('Неизвестный URL');
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
    private function parseHandler($handler)
    {
        $handler = explode('->', $handler);
        $controller = (new $handler[0]);
        $action = isset($handler[1]) ? $handler[1] : $controller->defaultAction;
        return [$controller, $action];
    }

    /**
     * Редирект. Только для нужд роутера.
     *
     * @TODO Можно добавить логирование таких редиректов для анализа ошибок, неправильных URL-в на страницах
     * сайта и т.п. И это должно быть опционально. Подумать, как сделать и где именно.
     *
     * @param string $url новый относительный адрес. Всегда без слеша слева, таков тут мой код.
     */
    private function redirect($url)
    {
        if ($_SERVER['QUERY_STRING']) {
            $url .= '?' . $_SERVER['QUERY_STRING'];
        }

        Response::redirect('/' . $url, 301);
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
     * @throw \RangeException
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
                            // удаляем из параметров то, что будет использовано в подстановке
                            unset($params[$k]);
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
            $strParams = [];
            foreach($params as $k => $v) {
                $strParams[] = "$k => $v";
            }

            $strParams = count($strParams) ? 'параметры [' . implode(', ', $strParams) . ']' : 'без параметров';
            throw new \RangeException("не могу построить URL по заданным значениям: ['$ns', '$ctrl'], $strParams");
        }
    }
}
