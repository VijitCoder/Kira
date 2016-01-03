<?php
/**
 * Часть функционала по сборке INSERT-а. Подключать к моделям.
 */
trait AddUserTrait  {
    /**
     * Пишем в БД инфу про нового юзера. Она разносится на две таблицы, логика записи одинаковая.
     *
     * Прим: функция обезличена, можно вообще где угодно использовать для INSERT-a в БД. Но это пока неясно,
     * пусть остается в трейте.
     *
     * @param array $fields по каким полям писать
     * @param array $data что писать
     * @return int | false
     */
    private function _add($fields, $data)
    {
        $values = $this->valueSet($fields, $data);

        //Это только для ProfileModel, данных может не быть вообще. Для UserModel - некорректно, но данные
        //там обеспечены валидацией. Так что используем условие, как общее и не паримся.
//        if (!$values) return 1;

        $fields = '`' . implode('`,`', $fields) . '`';
        $ph = implode(',', array_keys($values));//ph = placeholders

        $q = "INSERT INTO {$this->table} ({$fields}) VALUES ({$ph})";

        return $this->query(['q' => $q, 'p' => $values]);
    }
}