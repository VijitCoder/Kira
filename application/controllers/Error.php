<?php
/**
 * Контроллер ошибок http-протокола (401-404, 500)
 */

namespace app\controllers;

use utils\Env,
    utils\Request;

class Error extends \app\controllers\Front
{
    /**
     * Возвращаем юзеру страницу с описанием ошибки.
     *
     * Отсюда заголовки не шлем! Этим занимается либо web-сервер, у которого текущий метод прописан обработчиком
     * 40x ошибок, либо клиентский код. Т.о. тут только рисуем страницу и выполняем фоновую работу (логирование,
     * уведомление на мыло).
     */
    public function index()
    {
        $code = http_response_code();
        $data = [
            'domain' => Env::domain(),
            'index'  => Env::indexPage(),
        ];
        switch ($code) {
            case 401:
                $this->title = '401 Для доступа к запрашиваемому ресурсу требуется аутентификация';
                break;
            case 403:
                $this->title = '403 В доступе отказано';
                //...
                //@TODO Запись в лог, уведомление админу
                break;
            case 404:
                $this->title = '404 Страница не найдена';
                $data['request'] = Request::absoluteURL();
                //...
                //@TODO запись в лог
                break;
            case 500:
                $this->title = '500 Внутренняя ошибка сервера';
                //...
                //@TODO уведомление админу
                break;
            default:
                return;
        }

        $this->render('errors/' . $code, $data);
    }
}
