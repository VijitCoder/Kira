<?php
/**
 * Сервис личного кабинета юзера
 */
class ProfileService {
    /**
     * Получаем данные по юзеру для вывода в профиль
     * @param int $uid id юзера
     * @return array
     */
    public static function getDatas($uid)
    {
        $d = (new ProfileModel)->getProfile($uid);

        if ($d['birth_date'] == '0000-00-00') {
            $d['birth_date'] = '';
        } else {
            //в отдельном поле заказал дату в unix timestamp. Теперь подменяем значение. Имхо, так быстрее.
            $d['birth_date'] = date('d.m.Y', $d['b_date']);
        }

        $d['sex'] = str_replace(['none', 'female', 'male'], ['не задан', 'женский', 'мужской'], $d['sex']);

        if ($d['status'] == 'new') {
            $d['confirmUrl'] = WEB_ROOT . "registration/sendconfirm?m={$d['mail']}&c={$d['salt']}";

            //Достаем юзера флешками, пока не подтвердит учетку
            if (!Session::readFlash('infoConfirm', false)) {
                Session::addFlash('warnConfirm', 'Пожалуйста, подтвердите вашу учетную записть через email.');
                Session::addFlash('warnConfirmMail',
                    App::t('<a href="URL">Отправить себе письмо с подтверждением</a>', ['URL' => $d['confirmUrl']]));
            }
        } elseif ($d['status'] == 'banned') {
            Session::addFlash('errBanned', 'Ваша учетная запись заблокирована.');
        }

        return $d;
    }
}
