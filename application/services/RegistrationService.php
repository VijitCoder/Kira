<?php
/**
 * Сервис по управлению процессом регистрации юзера.
 * Регистрация проходит в два этапа: заполнение формы, подтверждение учетки.
 */

namespace app\services;

use \engine\App,
    \engine\net\Session,
    \engine\Env,
    \app\models\UserModel,
    \app\models\ProfileModel,
    \app\forms\RegistrationForm,
    \app\helpers\MailHelper;

class RegistrationService
{
    /** @var UserModel объект модели */
    private $_userModel;

    /**
     * Конструктор. Класс используется при обычных и ajax-запросах. Во втором случае запросы раздельные,
     * нужно хранить объект модели, чтоб не плодить лишнее.
     */
    public function __construct()
    {
        $this->_userModel = new UserModel;
    }

    /**
     * Регистрация нового пользователя
     *
     * @param RegistrationForm $form объект класса, пока пустой
     * @return bool
     */
    public function newUser(RegistrationForm $form)
    {
        //$_POST['login'] = $_POST['mail'] = AuthService::salt(10); //DBG
        $result = $form->load($_POST)->validate();
        $imgService = new ImageFileService;

        # Аватарка

        //Нельзя исключить эту часть из передачи формы, но аватар - необязательное поле.
        //Поэтому проверяем на ошибку #4 "Не выбран файл для загрузки" и игнорируем такую передачу.
        //Так же проверяем, что файл пришел один. Это на случай хакерского вмешательства в шаблон.
        if (isset($_FILES['avatar'])
            && !is_array($_FILES['avatar']['name'])
            && $_FILES['avatar']['error'] != 4
        ) {
            $fn = $imgService->loadImage($_FILES['avatar'], App::conf('avatar'));
            if ($fn) {
                $form->setValue('avatar', $fn);
            } else {
                $form->addError(['avatar' => $imgService->getLastError()]);
                $result = false;
            }
        }

        $fields = $form->getValues();

        # Проверки доступности логина/почты

        if ($msg = $this->isLoginTaken($fields['login'])) {
            $form->addError(['login' => $msg]);
            $result = false;
        }

        if ($msg = $this->isMailTaken($fields['mail'])) {
            $form->addError(['mail' => $msg]);
            $result = false;
        }

        if (!$result) {
            return false;
        }

        # Запись в БД

        $fields = array_merge($fields, AuthService::encodePassword($fields['password']));

        $model = $this->_userModel;
        $con = $model->getConnection();
        $con->beginTransaction();

        if ($result = $model->addUser($fields)) {
            $fields['id'] = $result;

            if ($fields['avatar'] && $fn = $imgService->moveToShard($fields['avatar'], $result)) {
                if ($fn) {
                    $fields['avatar'] = $fn;
                    $result = (new ProfileModel)->addProfile($fields);
                } else {
                    $form->addError(['avatar' => $imgService->getLastError()]);
                    $result = false;
                }
            }
        }

        # Завершение процесса регистрации

        if ($result) {
            $con->commit();
            self::sendConfirm($fields['mail'], $fields['salt']);
            Session::write('auth', $fields['id']);
        } else {
            $con->rollBack();
            //@TODO лог, письмо админам. Можно так же данные в сообщение добавить, через var_export()
            Session::addFlash('errSql', 'Ошибка записи в БД. Пожалуйста повторите попытку позже.');
        }

        return (bool)$result;
    }

    /**
     * Проверка "Логин занят"
     * @param string $login
     * @return string | null
     */
    public function isLoginTaken($login)
    {
        if ($this->_userModel->findByField('login', $login, ['select' => 'id'])) {
            return App::t('Логин уже занят. Пожалуйста выберите другой.');
        }
    }

    /**
     * Проверка "Почта занята"
     * @param string $mail
     * @return string | null
     */
    public function isMailTaken($mail)
    {
        if ($this->_userModel->findByField('mail', $mail, ['select' => 'id'])) {
            return App::t('Ящик уже есть в нашей базе. Пожалуйста укажите другой');
        }
    }

    /**
     * Отправляем письмо для подтверждения учетки
     * @return bool
     */
    public static function sendConfirm($mail, $salt)
    {
        $url = rtrim(Env::indexPage(), '/');
        $url .= App::router()->url(
            ['app\controllers', 'registration/confirm'],
            ['m' => $mail, 'c' => md5($salt)]
        );

        $from = App::conf('admin_mail');
        $mailto = sprintf('<a href="mailto:%s?subject=это_письмо_получено_ошибочно">%s</a>', $from, $from);

        $html = '<p>Вы получили это письмо, потому что ваш email был указан при регистрации на сайте '
            . sprintf('<a href="%s">%s</a>', Env::indexPage(), Env::domainName()) . '</p>'
            . '<p>Для подтверждения регистрации пожалуйста перейдите по ссылке '
            . sprintf('<a href="%s">%s</a>', $url, $url) . '</p>'
            . '<p>Если вы считаете, что получили это письмо ошибочно, приносим свои извинения. Не отвечайте '
            . 'на него или перешлите по адресу ' . $mailto . '. Спасибо.</p>';

        if ($res = MailHelper::sendHtml($mail, 'Подтверждение учетной записи', $html)) {
            Session::addFlash('infoConfirm', 'На указанный ящик отправлено письмо с ссылкой для подтверждения '
                . 'учетной записи. Пожалуйста, проверьте почту :)');
        } else {
            Session::addFlash('errConfirm', 'Попытка отправить письмо не удалась. Повторите позже, пожалуйста.');
            //@TODO лог, письмо админам в случае, когда отправка поломалась.
        }

        return $res;
    }

    /**
     * Подтверждение учетки
     * @return bool
     */
    public function confirm()
    {
        if (!isset($_GET['m']) || !isset($_GET['c'])) {
            return false;
        }
        $m = $this->_userModel;
        $d = $m->findByField('mail', $_GET['m'], ['select' => 'id, salt', 'cond' => 'AND `status`="new"']);

        if (!$d || md5($d['salt']) !== $_GET['c']) {
            return false;
        }

        $m->setStatus($d['id'], UserModel::S_ACTIVE);
        Session::addFlash('infoConfirmed', 'Учетная запись подтверждена. Спасибо.');
        Session::write('auth', $d['id']);
        return true;
    }
}
