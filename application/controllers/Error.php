<?php
/**
 * Контроллер ошибок http-протокола (401-404, 500)
 */

namespace app\controllers;

use utils\VarServer;

class Error extends \app\controllers\Front
{
    //protected $layout = 'layouts/error';

    /**
     * Возвращаем юзеру страницу с описанием ошибки.
     *
     * Отсюда заголовки не шлем! Этим занимается либо web-сервер, у которого текущий метод прописан обработчиком
     * 40x ошибок, либо клиентский код. Т.о. тут только рисуем страницу и выполняем фоновую работу (логирование,
     * уведомление на мыло).
     *
     * @param $err
     */
    public function index()
    {
        $code = http_response_code();
        $data = [
            'domain' => VarServer::domain(),
            'index'  => VarServer::indexPage(),
        ];
        switch ($code) {
            case 401:
                $this->title = '401 Для доступа к запрашиваемому ресурсу требуется аутентификация';
                break;
            case 403:
                $this->title = '403 В доступе отказано';
                //...
                //Запись в лог, уведомление админу
                break;
            case 404:
                $this->title = '404 Страница не найдена';
                $data['request'] = VarServer::requestURL();
                //...
                //запись в лог
                break;
            case 500:
                $this->title = '500 Внутренняя ошибка сервера';
                //...
                //уведомление админу
                break;
            default:
                return;
        }

        $this->render('errors/' . $code, $data);
    }
}
