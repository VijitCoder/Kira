<?php
/**
 * Подключение к базе.
 *
 * Синглтон. Конфигураций БД может быть несколько, класс хранит одно подключение по каждому конфигу.
 *
 * Конфигурация подключения в настройках приложения:
 * 'db' => [
 *    'dsn' => string,
 *    'user' => string,
 *    'password' => string,
 *    'options' => array  //может отсутствовать.
 * ]
 *
 * Ключ 'db' - ожидаемый по умолчанию.
 *
 * Больше класс ничего не делает, только хранит подключения к базам.
 */

namespace engine\db;

use PDO,
    engine\App;

class DbConnection
{
    /** @var PDO объекты подключения к БД. Одна БД + юзер = один объект */
    private static $_cons = [];

    /**
     * Соединяемся с базой или получаем дескриптор подключения.
     *
     * Запоминаем подключения по $confKey. Считаем, что в настройках приложения для каждого ключа описана уникальное
     * сочетание базы/пользователя, т.к. идентичный конфиг не имеет смысла.
     *
     * @param string $confKey ключ в настройках, по которому хранится массив с кофигурацией подключения к БД
     *
     * @return PDO объект подключения к БД
     */
    public static function connect($confKey)
    {
        if (isset(self::$_cons[$confKey])) {
            return self::$_cons[$confKey];
        }

        $conf = App::conf($confKey);
        if (!isset($conf['options'])) {
            $conf['options'] = null;
        }

        //Тут можно ловить PDOException. Сейчас исключения ловит перехватчик в App.php
        self::$_cons[$confKey] = new PDO($conf['dsn'], $conf['user'], $conf['password'], $conf['options']);

        return self::$_cons[$confKey];
    }

    /**
     * Запрещаем любое размножение объекта. Установка private доступа к этим методам не позволит выполнить соответствующие
     * действия над объектом.
     */
    private function __construct()
    {
        throw new Exception('Создание объекта запрещено. Только статичное использование.');
    }

    private function __clone()
    {
        exit('клонирование запрещено.');
    }

    private function __sleep()
    {
    }

    private function __wakeup()
    {
    }
}
