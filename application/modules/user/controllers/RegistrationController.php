<?php
/**
 * Контроллер регистрации/подтверждения регистрации юзера
 */

namespace app\modules\user\controllers;

class RegistrationController extends \app\controllers\FrontController
{
    protected $title = 'Регистрация нового пользователя';

    /**
     * Регистрация нового юзера
     */
    public function index()
    {
        if ($this->userId) {
            $this->redirect('/profile');
        }

        $params = [
            'minPass' => App::conf('minPass'),
            'minComb' => App::conf('minComb'),
            'imgCnst' => App::conf('avatar'),
        ];

        //форму создаем всегда, чтоб не нагружать шаблон кучей проверок isset($_POST[...])
        $form = new RegistrationForm();

        if (!empty($_POST) && (new RegistrationService)->newUser($form)) {
            $this->redirect('/profile');
        }

        $params['form'] = $form; //результат работы сервиса с моделью формы ИЛИ пустая модель

        $this->render('registration', $params);
    }

    /**
     * Шлем письмо с ссылкой "Подтверждение учетки"
     */
    public function sendconfirm()
    {
        if (!isset($_GET['m']) || !isset($_GET['c'])) {
            Session::addFlash('errConfirm', 'Неверные параметры запроса');
        }
        RegistrationService::sendConfirm($_GET['m'], $_GET['c']);
        $this->redirect('/profile');
    }

    /**
     * Собственно подтверждение учетки
     */
    public function confirm()
    {
        if ((new RegistrationService)->confirm()) {
            $this->redirect('/profile');
        }

        $lang = App::detectLang();
        $this->render("/i18n/{$lang}/confirmErr");
    }

    /**
     * (ajax)GET
     * Проверка логина или почты на занятость
     * @return string |null либо текст про "занято" либо ничего в любых остальных случаях
     */
    public function check()
    {
        header('Content-type: text/html; charset=utf-8');
        if (isset($_GET['p'])) {
            $p = $_GET['p'];
            $method = strpos($p, '@') ? 'isMailTaken' : 'isLoginTaken';
            echo (new RegistrationService)->$method($p);
        }
    }
}
