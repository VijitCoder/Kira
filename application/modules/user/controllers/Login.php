<?php
/**
 * Контроллер входа. Восстановление пароля тоже через него
 */

namespace app\modules\user\controllers;

use core\App,
    core\Router,
    app\modules\user\services\AuthService,
    utils\Session;

class Login extends \app\controllers\Front
{
    protected $title = 'Вход на сайт';

    public function __construct()
    {
        parent::__construct();
        $this->urls['recover'] = Router::url([APP_NS_PREFIX . 'modules\user\controllers\\', 'login/recover']);
    }

    /**
     * Страница входа на сайт
     */
    public function index()
    {
        if ($this->userId) {
            $this->redirect($this->urls['profile']);
        }

        $error = '';
        if (!empty($_POST) && !$error = AuthService::checkLoginAction()) {
            $this->redirect($this->urls['profile']);
        }

        $this->render('login', ['error' => $error]);
    }

    /**
     * Выход из защищенной зоны
     */
    public function out()
    {
        Session::delete('auth');
        $this->redirect($this->urls['login']);
    }

    /**
     * Восстановление пароля
     * Не реализовано. Заглушка
     */
    public function recover()
    {
        $error = 'Внимание. Функционал не работает. Это заглушка для тестовой задачи.';
        if ($_POST) {
            AuthService::recover();
            $this->redirect($this->urls['login']);
        }
        $this->title = App::t('напомнить пароль');
        $this->render('recover', ['error' => $error]);
    }
}
