<?php
/**
 * Базовый класс контроллеров
 */

namespace core;

use Exception,
    core\utils\EnvServer;

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
     * Заполняем шаблон, отдаем результат в ответ браузеру или возвращаем, как результат функции.
     *
     * @param string $view   шаблон для отрисовки
     * @param array  $data   параметры в шаблон
     * @param bool   $output флаг "выводить в браузер"
     * @return string
     * @throws Exception
     */
    protected function render($view, $data = array(), $output = true)
    {
        $content = $this->_renderFile($view, $data);

        ob_start();
        require VIEWS_PATH . $this->layout . $this->viewExt;
        $result = ob_get_clean();

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
     * @throws Exception
     */
    protected function renderPartial($view, $data = array(), $output = true)
    {
        $result = $this->_renderFile($view, $data);
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
        require VIEWS_PATH . $_view123 . $this->viewExt;
        return ob_get_clean();
    }

    /**
     * Редирект
     *
     * Прим.: указание абсолютного URI - это требование спецификации HTTP/1.1, {@see http://php.net/manual/ru/function.header.php}
     * Быстрая справка по кодам с редиктом {@see http://php.net/manual/ru/function.header.php#78470}
     *
     * Ситуация со схемой на самом деле может быть куда сложнее. Пока не усложняю, нет необходимости.
     *
     * @param string $url  новый относительный адрес. Возможно со слешем слева
     * @param int    $code код ответа HTTP
     * @return void
     */
    public function redirect($url, $code = 302)
    {
        $url = EnvServer::domainWithScheme() . ltrim($url, '/');
        header("location:{$url}", true, $code);
        App::end();
    }
}
