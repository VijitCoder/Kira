<?php
/**
 * Модель учетки юзера
 */

namespace app\models;

class UserModel extends \engine\db\Model
{
    use AddUserTrait;

    //статусы учетки юзера
    const S_NEW = 'new';
    const S_ACTIVE = 'active';
    const S_BANNED = 'banned';

    /** @var string имя таблицы, сразу в обратных кавычках. Если явно не задано, вычисляем от FQN имени класса */
    protected $table = '`user`';

    /**
     * Пишем нового юзера
     * @param array $data валидированные данные
     * @return int | false
     */
    public function addUser($data)
    {
        $fields = ['login', 'mail', 'password', 'salt'];
        if ($this->_add($fields, $data) !== 1) {
            return false;
        } else {
            return $this->connect()->lastInsertId();
        }
    }

    /**
     * Установка статуса учетки
     * @param int $id кому ставим
     * @param string статус см. константы этого класса
     * @return int | false
     */
    public function setStatus($id, $status)
    {
        //действуем внутри движка, можно доверять значениям
        $q = "UPDATE {$this->table} SET `status` = '{$status}' WHERE `id` = {$id}";
        return $this->query(['q' => $q]);
    }
}
