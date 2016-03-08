<?php
/**
 * Подключение к базе.
 *
 * Синглтон. Конфигураций БД может быть несколько, класс хранит одно подключение по каждому конфигу.
 *
 * Конфигурация подключения в настройках приложения:
 * <pre>
 * 'db' => [
 *    'dsn' => string,
 *    'user' => string,
 *    'password' => string,
 *    'options' => array  //может отсутствовать.
 * ]
 * </pre>
 *
 * Ключ 'db' - ожидаемый по умолчанию. Все параметры соответствуют параметрам конструктора класса PDO,
 * {@see http://php.net/manual/en/pdo.construct.php}.
 *
 * Про 'options' можно почитать в PDO::setAttribute() {@link http://php.net/manual/ru/pdo.setattribute.php}
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
     * Запоминаем подключения по $confKey. Полагаем, что в настройках приложения для каждого ключа описано <i>уникальное
     * сочетание</i> базы/пользователя, поскольку идентичный конфиг не имеет смысла.
     *
     * Логика исключений: если включен DEBUG, пробрасываем дальше PDOException, иначе логируем ошибку и пробрасываем
     * обычный Exception с ссылкой на предыдущее исключение. Поймав такое исключение и проверив "есть предыдущее
     * исключение" можно определить, что писать еще раз в лог не нужно. Проще говоря: на проде все полезное делаем тут,
     * на деве - передаем ситуацию разработчику как есть.
     *
     * @param string $confKey ключ в настройках, по которому хранится массив с кофигурацией подключения к БД
     * @return PDO объект подключения к БД
     * @throws \PDOException
     * @throws \Exception
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

        try {
            self::$_cons[$confKey] = new PDO($conf['dsn'], $conf['user'], $conf['password'], $conf['options']);
        } catch (\PDOException $e) {
            if (DEBUG) {
                throw $e;
            }

            $msg = $e->getMessage();
            $trace = $e->getTrace();
            if (isset($trace[2])) {
                $msg .= "\nИнициатор подключения " . str_replace(ROOT_PATH, '', $trace[2]['file'])
                    . '(' . $trace[1]['line'] . ') ';
            }

            if (isset($trace[3])) {
                $msg .= $trace[3]['function'] . '(...)';
            }

            App::log()->add(['message' => $msg, 'type' => \engine\Log::DB_CONNECT, 'file_force' => true]);

            throw new \Exception($e->getMessage(), 0, $e);
        }

        return self::$_cons[$confKey];
    }

    /**
     * Запрещаем любое размножение объекта. Установка private доступа к этим методам не позволит выполнить
     * соответствующие действия над объектом.
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
