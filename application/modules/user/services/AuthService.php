<?php
/**
 * Сервис по работе с паролями и аутентификацией юзера. Это две вещи, но они тесно связаны, поэтому
 * в одном классе.
 */

namespace app\modules\user\services;

use utils\Session;

class AuthService {
    /**
     * Шифруем пароль. Если соль не указана, значит шифрование для нового юзера.
     * @param string $pass открытый пароль
     * @param string $salt соль, для усиления шифрования
     * @return array | string
     */
    public static function encodePassword($pass, $salt = null)
    {
        $new = !(bool)$salt;
        if (!$salt) {
            $salt = self::salt(10);
        }
        $password = md5($pass.$salt);
        return $new ? ['password' => $password, 'salt' => $salt] : $password;
    }

    /**
     * Получение соли - случайной последовательности для усиления шифрования. Используется так же
     * при продленной аутентификации, подтверждении учетки т.д.
     * @param int $len нужная длина последовательности. До 32 символов
     * @return string
     */
    public static function salt($len)
    {
        return substr(md5(rand(1000,9999)), 0, $len);
    }

    /**
     * Проверка разрешений доступа юзера в защищенную зону
     * @return int id юзера, если он залогинен. False, если нет.
     */
    public static function checkAccess()
    {
        //@TODO поддержка продленной аутентификации
        return (int)Session::read('auth');
    }

    /**
     * Проверка попытки входа
     *
     * Не будем валидировать. Просто через PDOStatement прогоним и все.
     *
     * Если не найден ящик/логин - сообщаем об этом. Если неправильный пароль - другое сообщение.
     * Почему не скрываем от гостя факт, что логин/почта правильные? Потому что хакер сможет проверить почту
     * через форму восстановления пароля. Там-то по-любому сообщить придется, что почта не найдена.
     *
     * @return null | string сообщение об ошибках или ничего
     */
    public static function checkLoginAction()
    {
        $errMsg = App::t('Неверные логин и/или пароль');
        if (!isset($_POST['login']) || !isset($_POST['password'])) {
            return $errMsg;
        }

        //Поиск в БД по мылу/логину, расчет хеша от введеного пароля + соль. Результат (только ошибки).

        $field = (strpos($_POST['login'], '@') === false) ? 'login' : 'mail';
        $row = (new UserModel)->findByField($field, $_POST['login'], ['select' => 'id, password, salt']);

        if ($row) {
            if ($row['password'] == self::encodePassword($_POST['password'], $row['salt'])) {
                Session::write('auth', $row['id']);
                return;
            } else {
                return App::t('Неверный пароль');
            }
        } else {
            $errMsg = $field == 'login'
                ? App::t('Пользователь с указанным логином не найден')
                : App::t('Пользователь с указанной почтой не найден');
            return $errMsg;
        }
    }

    /**
     * Восстановление пароля
     * Болванка метода, в задаче не требуется, поэтому нет реализации.
     */
    public static function recover()
    {
        //проверяем существование мыла
        //isset($_GET['mail']) ...
        //return App::t('Пользователь с указанной почтой не найден');

        //Генерим ссылку, позволяем юзеру одноразовый вход в ЛК. Шлем письмо. По ссылке юзер сможет один раз
        //зайти в ЛК, где будет висеть предупреждение о необходимости сменить пароль. При появлении юзера в ЛК
        //признак одноразового входа убираем. В письме так же приводим последовательность действий.
        //...

        Session::newFlash('infoRecover', 'На указанный email высланы инструкции по восстановлению пароля.');
    }
}