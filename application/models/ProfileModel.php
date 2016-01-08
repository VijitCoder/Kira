<?php
/**
 * Модель профиля юзера
 */

namespace app\models;

class ProfileModel extends \core\Db
{
    use AddUserTrait;

    /** @var string имя таблицы, сразу в обратных кавычках. Если явно не задано, вычисляем от FQN имени класса */
    protected $table = '`user_profile`';

    /**
     * Пишем профиль нового юзера
     * @param array $data валидированные данные
     * @return bool
     */
    public function addProfile($data)
    {
        $fields = ['id', 'firstname', 'secondname', 'sex', 'birth_date', 'town', 'avatar'];
        return $this->_add($fields, $data) === 1;
    }

    /**
     * Получаем данные профиля юзера
     * @param int $uid id юзера
     * @return array
     */
    public function getProfile($uid)
    {
        $q = '
            SELECT u.login, u.mail, u.`status`, u.salt, up.*, UNIX_TIMESTAMP(up.birth_date) AS b_date
            FROM user u
            LEFT JOIN user_profile up USING (id)
            WHERE u.id = ?';
        return $this->query(['q' => $q, 'p' => [$uid], 'one' => true]);
    }
}
