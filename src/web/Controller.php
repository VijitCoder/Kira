<?php
namespace kira\web;

use kira\net\Response;

/**
 * Базовый класс контроллеров
 */
class Controller
{
    /**
     * Действие контроллера по умолчанию
     * @var string
     */
    public $defaultAction = 'index';

    /**
     * Макет. Относительный путь от KIRA_VIEWS_PATH + имя файла без расширения.
     * @var string
     */
    protected $layout = 'layout';

    /**
     * Расширение файлов шаблонов и макетов
     * @var string
     */
    protected $viewExt = '.htm';

    /**
     * Заголовок страницы. Предполагается использование с тегом <title></title>
     * @var string
     */
    protected $title = '';

    /**
     * Дополнительные мета-теги, типа keywords, description и т.д. Предполагаются теги в нотации
     * <meta name='...' content='...'>
     * @var array [name => content]
     */
    public $meta = [];

    /**
     * Шаблонизатор
     *
     * Заполняем шаблон, вписываем его в макет. Отдаем результат в ответ браузеру или возвращаем, как результат функции.
     *
     * Зарезервированная переменная: $CONTENT. В ней текст отрисованного шаблона для вставки в макет. Верхний регистр
     * применен умышленно во избежание случайных совпадений, не принято писать переменные большими буквами.
     *
     * @param string $view   шаблон для отрисовки, относительный путь от KIRA_VIEWS_PATH
     * @param array  $data   параметры в шаблон
     * @param bool   $output флаг "выводить в браузер"
     * @return string|null
     * @throws \Exception
     */
    protected function render($view, $data = [], $output = true)
    {
        $this->beforeRender($view, $data);

        if ($view) {
            $CONTENT = $this->renderFile(KIRA_VIEWS_PATH . $view . $this->viewExt, $data);
        }

        ob_start();
        require KIRA_VIEWS_PATH . $this->layout . $this->viewExt;
        $result = ob_get_clean();

        $this->afterRender($view);

        if ($output) {
            echo $result;
        } else {
            return $result;
        }
    }

    /**
     * Отрисовка части шаблона, без макета
     * @param string $view   шаблон для отрисовки, относительный путь от KIRA_VIEWS_PATH
     * @param array  $data   параметры в шаблон
     * @param bool   $output флаг "выводить в браузер"
     * @return string
     * @throws \Exception
     */
    protected function renderPartial($view, $data = [], $output = true)
    {
        $result = $this->renderFile(KIRA_VIEWS_PATH . $view . $this->viewExt, $data);
        if ($output) {
            echo $result;
        } else {
            return $result;
        }
    }

    /**
     * Отрисовка шаблона, хранящегося вне каталога KIRA_VIEWS_PATH
     *
     * Отличие от других методов отрисовки именно в возможности указать шаблон в любом месте файловой системы. Но при
     * этом путь к нему должен быть абсолютным.
     *
     * @param string $view   шаблон для отрисовки, абсолютный путь + имя файла + расширение
     * @param array  $data   параметры в шаблон
     * @param bool   $output флаг "выводить в браузер"
     * @return string
     * @throws \Exception
     */
    protected function renderExternal($view, $data = [], $output = true)
    {
        $result = $this->renderFile($view, $data);
        if ($output) {
            echo $result;
        } else {
            return $result;
        }
    }

    /**
     * Отрисовка виджета
     * @param string $class  FQN класса виджета
     * @param array  $params параметры для передачи в виджет
     * @param bool   $output флаг "выводить в браузер"
     * @return null|string
     * @throws \LogicException
     */
    public function widget(string $class, array $params = [], $output = true)
    {
        $widget = new $class($params);
        if (!$widget instanceof Widget) {
            throw new \LogicException('Объект виджета не унаследован от ' . Widget::class . '. Это недопустимо.');
        }
        $result = $this->renderExternal($widget->getView(), $widget->getData(), $output);
        if ($output) {
            return $result;
        }
    }

    /**
     * Собственно функция отрисовки. Включаем буферизацию, заполняем шаблон и возвращаем ответ.
     *
     * Прим.: имя переменной $_view123 выбрано специально для уменьшения вероятности совпадения с параметрами шаблона.
     *
     * @param string $_view123 шаблон для отрисовки, абсолютный путь + файл + расширение
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
        require $_view123;
        return ob_get_clean();
    }

    /**
     * Редирект из контроллера
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
     * Действие непосредственно перед отрисовкой шаблона с макетом
     * @param string $view шаблон для отрисовки
     * @param array  $data параметры в шаблон
     * @return void
     */
    public function beforeRender($view, &$data = [])
    {
    }

    /**
     * Действие сразу после отрисовки шаблона с макетом
     * @param string $view шаблон для отрисовки
     * @return void
     */
    public function afterRender($view = '')
    {
    }
}
