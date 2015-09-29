<?php
/**
 * Базовый класс контроллеров
 */
class AppController
{
    /** @var string заголовок страницы */
    protected $title = '';

    /** @var string макет по умолчанию */
    protected $layout='layout';

    /** @var int|false id юзера, если он на сайте. По этому значению контроллеры и шаблоны меняют свое поведение */
    protected $userId;

    /**
     * Конструктор контроллера
     * Не забывай вызывать его в потомках ПЕРЕД их конструкторами.
     */
    public function __construct()
    {
        if ($this->title) {
            $this->title = App::t($this->title);
        }

        $this->userId = AuthService::checkAccess();
    }

    /**
     * Очень простой пример шаблонизации проекта. Заполняем шаблон, отдаем результат в ответ браузеру.
     *
     * @param string $_view шаблон для отрисовки
     * @param array $data параметры в шаблон
     * @return void
     */
    protected function render($view, $data = array())
    {
        $content = $this->_renderFile($view, $data);
        //выводим на единственном макете, который у нас есть :)
        require PARTH_VIEWS . $this->layout . '.htm';
    }

    /**
     * Отрисовка части шаблона, без макета
     *
     * @param string $_view шаблон для отрисовки
     * @param array $data параметры в шаблон
     * @return string
     */
    protected function renderPartial($view, $data = array())
    {
        echo $this->_renderFile($view, $data);
    }

    /**
     * Собственно функция отрисовки. Включаем буферизацию, заполняем шаблон и возвращаем ответ.
     *
     * @param string $_view123 шаблон для отрисовки. Имя переменной выбрано специально для уменьшения
     * вероятности совпадения с параметрами шаблона
     * @param array $data параметры в шаблон
     * @return string
     */
    private function _renderFile($_view123, $data)
    {
        if (isset($data['_view123'])) {
            throw new Exception ('Недопустимый параметр "_view123". ' . __FILE__ . ':' . __LINE__);
        }

        extract($data);

        //Отрисованный шаблон буферизируем, в него уйдут распакованные параметры
        ob_start();
        require PARTH_VIEWS . $_view123 . '.htm';
        return ob_get_clean();
    }

    /**
     * Редирект
     * @param string $url
     * @return void
     */
    public function redirect($url)
    {
        header('location:' . WEB_ROOT . ltrim($url, '/')); //для теста.
        //header('location:' . $url); //по-хорошему
        App::end();
    }

    /**
     * Заглушка 404-й, для тестовой задачи
     * @TODO выделить отдельный контороллер с поддержкой 403, 404, 500. Настроить Apache на эти страницы.
     */
    public static function error404()
    {
        $obj = new self;
        $obj->title = 'Тест. Страница не найдена (404)';
        $obj->render('404', [
            'domain' => App::conf('domain'),
            'index' => App::conf('indexPage'),
            'request' => 'http://' . App::conf('domain') . $_SERVER['REQUEST_URI'],
        ]);
    }
}
