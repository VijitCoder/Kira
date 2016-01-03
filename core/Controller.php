<?php
/**
 * Базовый класс контроллеров
 */

namespace core;

class Controller
{
    /** @var string Действие контроллера по умолчанию */
    public $defaultAction = 'index';

    /** @var string заголовок страницы */
    protected $title = '';

    /** @var string макет по умолчанию */
    protected $layout='layout';

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
        require VIEWS_PATH . $this->layout . '.htm';
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
     *                         вероятности совпадения с параметрами шаблона
     * @param array  $data     параметры в шаблон
     * @return string
     * @throws Exception
     */
    private function _renderFile($_view123, $data)
    {
        if (isset($data['_view123'])) {
            throw new Exception ('Недопустимый параметр "_view123". ' . __FILE__ . ':' . __LINE__);
        }

        extract($data);

        //Отрисованный шаблон буферизируем, в него уйдут распакованные параметры
        ob_start();
        require VIEWS_PATH . $_view123 . '.htm';
        return ob_get_clean();
    }

    /**
     * Редирект
     *
     * Прим.: указание абсолютного URI - это требование спецификации HTTP/1.1, {@see http://php.net/manual/ru/function.header.php}
     * Быстрая справка по кодам с редиктом {@see http://php.net/manual/ru/function.header.php#78470}
     *
     * @param string $uri новый относительный адрес. Возможно со слешем слева
     * @param int $code код ответа HTTP
     * @return void
     */
    public function redirect($uri, $code = 302)
    {
        //ситуация со схемой на самом деле может быть куда сложнее. Пока не усложняю, нет необходимости.
        $scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];

        $uri =  ltrim($uri, '/'); //устраняем неясность

        header("location:{$scheme}://" . ROOT_URL . $uri, true, $code); //для теста.
        //header("location:{$scheme}://{$host}/{$uri}", true, $code); //по-хорошему
        App::end();
    }

    /**
     * Заглушка 404-й, для тестовой задачи
     * @TODO выделить отдельный контороллер с поддержкой 403, 404, 500. Настроить Apache на эти страницы.
     */
    public static function error404()
    {
        //@see http://php.net/manual/ru/function.header.php#92305
        header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
        $obj = new self;
        $obj->title = 'Тест. Страница не найдена (404)';
        $obj->render('404', [
            'domain' => App::conf('domain'),
            'index' => App::conf('indexPage'),
            'request' => 'http://' . App::conf('domain') . $_SERVER['REQUEST_URI'],
        ]);
    }
}
