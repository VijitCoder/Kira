<?php
namespace kira\web;

use kira\net\Response;

/**
 * Базовый класс контроллеров
 */
class Controller
{
    /** @var string Действие контроллера по умолчанию */
    public $defaultAction = 'index';

    /** @var string заголовок страницы */
    protected $title = '';

    /** @var string расширение файлов представлений */
    protected $viewExt = '.htm';

    /** @var string макет. Относительный путь в каталоге VIEWS_PATH + имя файла без расширения. */
    protected $layout = 'layout';

    /**
     * Очень простой шаблонизатор.
     *
     * Заполняем шаблон, отдаем результат в ответ браузеру или возвращаем, как результат функции.
     *
     * Зарезервированная переменная: $CONTENT. В ней текст отрисованного шаблона для вставки в макет. Верхний регистр
     * применен умышленно во избежание случайных совпадений, не принято писать переменные большими буквами.
     *
     * @param string $view   шаблон для отрисовки
     * @param array  $data   параметры в шаблон
     * @param bool   $output флаг "выводить в браузер"
     * @return string
     * @throws \Exception
     */
    protected function render($view, $data = [], $output = true)
    {
        $this->beforeRender($view, $data);

        $CONTENT = $this->renderFile($view, $data);

        ob_start();
        require VIEWS_PATH . $this->layout . $this->viewExt;
        $result = ob_get_clean();

        $this->afterRender($view);

        if ($output) {
            echo $result;
        } else {
            return $result;
        }
    }

    /**
     * Отрисовка части шаблона, без макета.
     *
     * @param string $view   шаблон для отрисовки
     * @param array  $data   параметры в шаблон
     * @param bool   $output флаг "выводить в браузер"
     * @return string
     * @throws \Exception
     */
    protected function renderPartial($view, $data = [], $output = true)
    {
        $result = $this->renderFile($view, $data);
        if ($output) {
            echo $result;
        } else {
            return $result;
        }
    }

    /**
     * Собственно функция отрисовки. Включаем буферизацию, заполняем шаблон и возвращаем ответ.
     *
     * @param string $_view123 шаблон для отрисовки. Имя переменной выбрано специально для уменьшения
     *                         вероятности совпадения с параметрами шаблона
     * @param array  $data     параметры в шаблон
     * @return string
     * @throws \InvalidArgumentException
     */
    private function renderFile($_view123, $data)
    {
        if (isset($data['_view123'])) {
            throw new \InvalidArgumentException ('Недопустимый параметр "_view123". ' . __FILE__ . ':' . __LINE__);
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Ожидается массив параметров');
        }

        extract($data);

        //Отрисованный шаблон буферизируем, в него уйдут распакованные параметры
        ob_start();
        require VIEWS_PATH . $_view123 . $this->viewExt;
        return ob_get_clean();
    }

    /**
     * Редирект из контроллера.
     *
     * Функция введена для удобства чтения кода.
     *
     * @param string $url  новый относительный адрес, с ведущим слешем
     * @param int    $code код ответа HTTP
     * @return void
     */
    public function redirect($url, $code = 302)
    {
        Response::redirect($url, $code);
    }

    /**
     * Действие непосредственно перед отрисовкой шаблона с макетом.
     * @param string $view шаблон для отрисовки
     * @param array  $data параметры в шаблон
     * @return void
     */
    public function beforeRender($view, &$data = [])
    {
    }

    /**
     * Действие сразу после отрисовки шаблона с макетом.
     * @param string $view шаблон для отрисовки
     * @return void
     */
    public function afterRender($view = '')
    {
    }
}
