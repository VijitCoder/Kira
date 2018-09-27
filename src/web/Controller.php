<?php
namespace kira\web;

use kira\exceptions\ControllerException;
use kira\net\Response;

/**
 * Базовый класс контроллеров
 */
class Controller
{
    /**
     * Действие контроллера по умолчанию
     *
     * @var string
     */
    public $defaultAction = 'index';

    /**
     * Макет. Относительный путь от KIRA_VIEWS_PATH + имя файла без расширения.
     *
     * @var string
     */
    protected $layout = 'layout';

    /**
     * Расширение файлов шаблонов и макетов
     *
     * @var string
     */
    protected $viewExt = '.htm';

    /**
     * Заголовок страницы. Предполагается использование с тегом <title></title>
     *
     * @var string
     */
    protected $title = '';

    /**
     * Объект класса ответа
     *
     * @var Response
     */
    protected $response;

    /**
     * Инициируем объект класса ответа
     */
    public function __construct()
    {
        $this->response = new Response;
    }

    /**
     * Заполняем шаблон, вписываем его в макет. Отдаем результат с заголовками или возвращаем как результат функции.
     *
     * Зарезервированная переменная: $CONTENT. В ней текст отрисованного шаблона для вставки в макет. Верхний регистр
     * применен умышленно во избежание случайных совпадений, не принято писать переменные большими буквами.
     *
     * @param string $view   шаблон для отрисовки, относительный путь от KIRA_VIEWS_PATH
     * @param array  $data   параметры в шаблон
     * @param bool   $output флаг "выводить в браузер"
     * @return string|null
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
            $this->answer($result);
        } else {
            return $result;
        }
    }

    /**
     * Отрисовка шаблона, хранящегося вне каталога KIRA_VIEWS_PATH
     *
     * Отличие от render() именно в возможности указать шаблон в любом месте файловой системы. Но при этом путь к нему
     * должен быть абсолютным.
     *
     * @param string $view   шаблон для отрисовки, абсолютный путь + имя файла + расширение
     * @param array  $data   параметры в шаблон
     * @param bool   $output флаг "выводить в браузер"
     * @return string
     */
    protected function renderExternal($view, $data = [], $output = true)
    {
        $result = $this->renderFile($view, $data);
        if ($output) {
            $this->answer($result);
        } else {
            return $result;
        }
    }

    /**
     * Отрисовка части шаблона, без макета. Не предполагается использование метода для самостоятельного ответа
     * на запрос, поэтому заголовки не отправляются.
     *
     * @param string $view   шаблон для отрисовки, относительный путь от KIRA_VIEWS_PATH
     * @param array  $data   параметры в шаблон
     * @param bool   $output флаг "выводить в браузер"
     * @return string
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
     * Отрисовка виджета
     *
     * @param string $class  FQN класса виджета
     * @param array  $params параметры для передачи в виджет
     * @param bool   $output флаг "выводить в браузер"
     * @return null|string
     * @throws ControllerException
     */
    public function widget(string $class, array $params = [], $output = true)
    {
        $widget = new $class($params);
        if (!$widget instanceof Widget) {
            throw new ControllerException(
                'Объект виджета не унаследован от ' . Widget::class . '. Это недопустимо.',
                ControllerException::LOGIC_ERROR
            );
        }
        $result = $this->renderExternal($widget->getView(), $widget->getData(), $output);
        if (!$output) {
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
     * @throws ControllerException
     */
    private function renderFile($_view123, $data)
    {
        if (isset($data['_view123'])) {
            throw new ControllerException(
                'Недопустимый параметр "_view123". ' . __FILE__ . ':' . __LINE__,
                ControllerException::INVALID_ARGUMENT
            );
        }

        if (!is_array($data)) {
            throw new ControllerException('Ожидается массив параметров', ControllerException::INVALID_ARGUMENT);
        }

        extract($data);

        //Отрисованный шаблон буферизируем, в него уйдут распакованные параметры
        ob_start();
        require $_view123;
        return ob_get_clean();
    }

    /**
     * Отправляем подготовленный ответ.
     *
     * Это конечная точка работы web-приложения. Все заголовки должны быть заданы через $this->response или прямо тут
     * в параметре, сам ответ может быть как отрисованным шаблоном, так и просто (json)строкой.
     *
     * @param string $message ответ
     * @param array  $headers заголовки
     * @throws ControllerException
     */
    public function answer(string $message = '', array $headers = []): void
    {
        if (!$this->response) {
            throw new ControllerException(
                'Объект $response не инициализирован. Забыл где-то вызвать констуктор контроллера-родителя?',
                ControllerException::RUNTIME_ERROR
            );
        }

        $this->response
            ->addHeaders($headers)
            ->send($message);
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
        $this->response->redirect($url, $code);
    }

    /**
     * Действие непосредственно перед отрисовкой шаблона с макетом
     *
     * @param string $view шаблон для отрисовки
     * @param array  $data параметры в шаблон
     * @return void
     */
    public function beforeRender($view, &$data = [])
    {
    }

    /**
     * Действие сразу после отрисовки шаблона с макетом
     *
     * @param string $view шаблон для отрисовки
     * @return void
     */
    public function afterRender($view = '')
    {
    }
}
