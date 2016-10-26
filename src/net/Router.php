<?php
namespace kira\net;

use kira\core\App;
use kira\interfaces\IRouter;
use kira\exceptions\ConfigException;
use kira\exceptions\RouteException;

/**
 * Маршрутизатор запросов
 */
class Router implements IRouter
{
    /**
     * Названия контроллера, который вызвал роутер после парсинга запроса
     * @var string
     */
    private $controller = '';

    /**
     * Названия действия, который вызвал роутер после парсинга запроса.
     *
     * Прим: если вызывается действие по умолчанию, информацию об этом действии нельзя получить из роутера
     * в конструкторе контроллера. Т.е. будет пустая строка. Это связано с тем, что сначала отрабатывает конструктор,
     * и только потом в объекте контроллера можно прочитать $defaultAction.
     *
     * @var string
     */
    private $action = '';

    /**
     * Парсинг URL и вызов action-метода в соответствующем контроллере.
     *
     * Когда сервер Apache передает ошибки >=401 (см. Apache::ErrorDocument), он добавляет свой заголовок REDIRECT_URL.
     * Там указан адрес, назначенный сервером для обработки таких ошибок. В таком случае обрабатываем именно этот адрес,
     * а не REQUEST_URI.
     *
     * @internal
     * @return null
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
            return null;
        }

        $tmp = rtrim($url, '/');
        if ($tmp !== $url) {
            $this->redirect($tmp);
        }

        if (!$set = $this->findRouteFor($url)) {
            $this->notFound();
        }

        list($ctrlName, $action, $params) = $set;
        $this->controller = $ctrlName;
        $this->action = $action;
//dd($set);//DBG

        if (!App::composer()->findFile($ctrlName)) {
            $this->notFound();
        }

        $controller = new $ctrlName;

        if (!$action) {
            $action = $controller->defaultAction;
            $this->action = $action;
        }

        if (method_exists($controller, $action)) {
            if ($params) {
                $ref_method = new \ReflectionMethod($ctrlName, $action);
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
            $this->notFound();
        }
    }

    /**
     * Ищем в карте роутов подходящий.
     *
     * Каждый роут принадлежит группе пространства имени. Левая часть роута - заготовка регулярки, правая часть -
     * указание на контроллер/метод. В обеих частях возможны подстановки в угловых скобках вида <var:regexp>.
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
     * @internal Метод объявлен публичным только для возможности unit-тестирования. Он не должен вызываться вне класса
     * @param string $url запрос к серверу, без ведущего слеша и без GET-параметров.
     * @return array|null
     */
    public function findRouteFor($url)
    {
//echo $url;
        $matches = null;
        foreach (App::conf('router.routes') as $namespace => $routes) {
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
            return null;
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

//echo "{$right} in {$namespace}";
        $_GET = array_merge($params, $_GET);

        $right = explode('/', $right);
        return [
            $namespace . '\\' . $right[0],     //FQN controller
            isset($right[1]) ? $right[1] : '', //action
            $params                            //params
        ];
    }

    /**
     * Контроллер или роут не найден, нужно ответить юзеру страницей 404.
     *
     * В конфиге должен быть прописан контроллер, который будет обрабатывать 404 ошибку. Если он не задан (пустая
     * строка), возвращаем 404-й заголовок и простой текст.
     *
     * Предохранитель для кодера: допустим, веб-сервер Apache и задан ErrorDocument. Но указанный адрес роутеру
     * неизвестен. Тогда роутер попытается ответить 404 (т.е. сюда попадет). А мы при этом реальный ответ веб-сервера
     * не подменяем и возвращаем текст по его коду.
     *
     * @throws ConfigException из parseHandler()
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
            Response::send($msg);
            App::end();
        }

        http_response_code(404);

        if ($handler = App::conf('router.404_handler', false)) {
            list($controller, $action) = $this->parseHandler($handler);
            $controller->$action();
        } else {
            Response::send('Неизвестный URL');
            App::end();
        }
    }

    /**
     * Разбираем контроллер и метод, прописанные в конфиге.
     *
     * Ожидаем FQN имя класса + действие. Или будет выбрано действие, заданное в контроллер по умолчанию.
     *
     * Прим: тут не проверяем существование скрипта в соответствии с классом. Позволяем возникнуть ошибке. Тогда кодер
     * сможет сразу понять, что его конфиг описан неверно, и не придется гадать, почему он видит 404 вместо главной,
     * например. Более того, неправильно описанный контроллер для 404-й с проверкой привел бы с бесконечному редиректу.
     *
     * @param string $handler FQN имя класса [+ действие].
     * @return array
     * @throws ConfigException
     */
    private function parseHandler($handler)
    {
        $handlerArr = explode('->', $handler);

        $ctrlName = $handlerArr[0];
        $this->controller = $ctrlName;
        if (!App::composer()->findFile($ctrlName)) {
            throw new ConfigException(
                "Контроллер не найден. Неверный хендлер '{$handler}' прописан в конфигурации приложения");
        }
        $controller = new $ctrlName;

        $action = $handlerArr[1] ?? $controller->defaultAction;
        $this->action = $action;

        return [$controller, $action];
    }

    /**
     * Редирект. Только для нужд роутера.
     * Если задана настройка "router.log_redirects", логируем такие редиректы.
     * @param string $url новый относительный адрес. Всегда без слеша слева, таков тут мой код.
     * @return void
     */
    private function redirect($url)
    {
        if ($_SERVER['QUERY_STRING']) {
            $url .= '?' . $_SERVER['QUERY_STRING'];
        }

        if (App::conf('router.log_redirects', false)) {
            App::logger()->addTyped('pедирект на /' . $url, 'router redirect');
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
     * не задано значение, а есть только ключ, типа ['p' => null], он попадет в URL без значения. Параметры могут быть
     * двумерным массивом, при этом можно получить строку типа 'label[]=3&label[]=43&q=...'
     *
     * Прим: поиск роута не зависит от регистра. Это немного упрощает требования к описанию параметров функции.
     *
     * Если ничего не найдем, проброс ошибки. Вообще ситуация некритичная, но иначе можно прозевать исчезновение роута
     * и получить битую ссылку.
     *
     * @param mixed $route  <b>неассоциативный</b> массив 2-х элементов ["пространство имен", "контроллер/действие"]
     * @param array $params доп.параметры для передачи в адрес. Ассоциативный массив ['имя параметра' => 'значение']
     * @return string готовый <b>относительный</b> URL
     * @throws RouteException
     */
    public function url($route, array $params = [])
    {
        list($ns, $ctrlAct) = $route;
        $ctrlAct = trim($ctrlAct, '/');

        $routes = App::conf("router.routes.{$ns}", false);
        if (!$routes) {
            throw new RouteException("Нет карты роутов для пространства имен '$ns'");
        }

        $match = null;
        $paramsBackup = $params;
        foreach ($routes as $left => $right) {
            $left = ltrim($left, '/');
            $right = ltrim($right, '/');
            $pattern = preg_replace('~(<(.+)>)~U', '(?P<$2>[^/]+)', $right);
            $pattern = "~^$pattern$~Ui";

            if (preg_match($pattern, $ctrlAct, $matchCtrlAct)) {
                if (preg_match_all('~<([a-z0-9_]+):.+>~U', $left, $requiredParams)) {

                    $requiredParams = array_flip($requiredParams[1]);

                    foreach ($requiredParams as $k => &$v) {
                        if (isset($matchCtrlAct[$k])) {
                            $v = $matchCtrlAct[$k];
                        } else if (isset($params[$k])) {
                            $v = $params[$k];
                            // удаляем из параметров то, что будет использовано в подстановке
                            unset($params[$k]);
                        } else {
                            $params = $paramsBackup;
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
        if ($matchCtrlAct) {
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

            return '/' . $left . $this->makeQueryString($params);

        } else {
            $strParams = [];
            foreach ($params as $k => $v) {
                $strParams[] = "$k => $v";
            }

            $strParams = count($strParams) ? 'параметры [' . implode(', ', $strParams) . ']' : 'без параметров';
            throw new RouteException("не могу построить URL по заданным значениям: ['$ns', '$ctrlAct'], $strParams");
        }
    }

    /**
     * Собираем из массива GET-строку запроса, которая пишется в URL после знака вопроса
     *
     * Каждая пара ключ=значение кодируется для безопасного использования в URL-ах. Поддерживаются ключи-массивы,
     * т.е. возможно собрать строку вида: label[]=3&label[]=43&q=...
     *
     * @param array $params исходные параметры. Максимум двумерный массив
     * @return string строка типа p=34&str=%23%35%30&arr[]=4&arr[]=qwerty
     * @throws RouteException
     */
    public function makeQueryString(array $params)
    {
        if (!$params) {
            return '';
        }

        foreach ($params as $k => &$v) {
            if (is_array($v)) {
                foreach ($v as &$subValue) {
                    if (is_array($subValue)) {
                        throw new RouteException('Слишком большая вложенность массива. Максимум двумерный массив');
                    }
                    $subValue = urlencode($k) . '[]' . ($subValue ? '=' . urlencode($subValue) : '');
                }
                $v = implode('&', $v);
            } else {
                $v = urlencode($k) . ($v ? '=' . urlencode($v) : '');
            }
        }
        return '?' . implode('&', $params);
    }

    /**
     * Названия контроллера, к которому обратился роутер после парсинга запроса
     * @return string
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * Названия метода-действия, которое вызвал роутер после парсинга запроса
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }
}
