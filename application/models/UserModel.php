<?php
/**
 * Модель учетки юзера
 */

require_once __DIR__ . '/AddUserTrait.php';

class UserModel extends Db
{
    //статусы учетки юзера
    const S_NEW = 'new';
    const S_ACTIVE = 'active';
    const S_BANNED = 'banned';

    use AddUserTrait;

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
