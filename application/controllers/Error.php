<?php
/**
 * Контроллер ошибок http-протокола (401-404, 500)
 */

namespace app\controllers;

use engine\App,
    engine\Env,
    engine\net\Request,
    engine\net\Response;

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
        $log = App::log();
        $data = [
            'domain' => Env::domainName(),
            'index'  => Env::indexPage(),
        ];
        switch ($code) {
            case 401:
                $this->title = '401 Для доступа к запрашиваемому ресурсу требуется аутентификация';
                break;
            case 403:
                $this->title = '403 В доступе отказано';
                $log->addTyped('403 В доступе отказано', $log::HTTP_ERROR);
                break;
            case 404:
                $this->title = '404 Страница не найдена';
                $data['request'] = Request::absoluteURL();
                $log->addTyped('404 Страница не найдена', $log::HTTP_ERROR);
                break;
            case 500:
                // TODO может не надо на мыло? Ежедневного cron-отчета недостаточно будет?
                $this->title = '500 Внутренняя ошибка сервера';
                $log->add(['message' => '500 Внутренняя ошибка сервера', 'type' => $log::HTTP_ERROR, 'notify' => true]);
                break;
            default:
                $msg = "Ошибка. $code " . Response::textOf($code);
                echo $msg;
                $log->addTyped($msg, $log::HTTP_ERROR);
                return;
        }

        $this->render('errors/' . $code, $data);
    }
}
