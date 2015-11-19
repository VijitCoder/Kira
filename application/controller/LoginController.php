<?php
/**
 * Контроллер входа. Восстановление пароля тоже через него
 */
class LoginController extends FrontController
{
    protected $title = 'Вход на сайт';

    /**
     * Страница входа на сайт
     */
    public function index()
    {
        if ($this->userId) {
            $this->redirect('/profile');
        }

        $error = '';
        if (!empty($_POST) && !$error = AuthService::checkLoginAction()) {
            $this->redirect('/profile');
        }

        $this->render('login', ['error' => $error]);
    }

    /**
     * Выход из защищенной зоны
     */
    public function out()
    {
        Session::delete('auth');
        $this->redirect('/login');
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
            $this->redirect('/login');
        }
        $this->title = App::t('напомнить пароль');
        $this->render('recover', ['error' => $error]);
    }
}
