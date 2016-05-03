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
 *    'options' => array | []
 *    'set_timezone' => bool | TRUE
 * ]
 * </pre>
 *
 * Ключ 'db' - ожидаемый по умолчанию. Почти все параметры соответствуют параметрам конструктора класса PDO,
 * {@link http://php.net/manual/en/pdo.construct.php}.
 *
 * Про 'options' можно почитать в PDO::setAttribute() {@link http://php.net/manual/ru/pdo.setattribute.php}
 *
 * Если поднят флаг 'set_timezone', то после установки соединения отправляется запрос на установку часового пояса сессии
 * в соответствии с поясом, заданном для PHP-скриптов. Это гарантирует работу БД и PHP в одном времени. По теме часовых
 * поясов в MySQL хорошо расписано тут
 * {@link http://stackoverflow.com/questions/19023978/should-mysql-have-its-timezone-set-to-utc/19075291#19075291}
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
     * Опционально ('set_timezone'): после установки соединения отправляется запрос на установку часового пояса сессии
     * в соответствии с поясом, заданном для PHP-скриптов. В запросе используется числовое представление пояса,
     * т.к. не всегда на MySQL-сервер загружена таблица с названиями часовых поясов.
     *
     * Логика исключений: если включен DEBUG, пробрасываем дальше PDOException, иначе логируем ошибку и пробрасываем
     * обычный Exception с ссылкой на предыдущее исключение. Поймав такое исключение и проверив "есть предыдущее
     * исключение" можно определить, что писать еще раз в лог не нужно. Проще говоря: на проде все полезное делаем тут,
     * на деве - передаем ситуацию разработчику как есть.
     *
     * @param string $confKey ключ в настройках, по которому хранится массив с кофигурацией подключения к БД
     * @return PDO объект подключения к БД
     * @throws \Exception
     */
    public static function connect($confKey)
    {
        if (isset(self::$_cons[$confKey])) {
            return self::$_cons[$confKey];
        }

        $conf = array_merge(['options' => null, 'set_timezone' => true], App::conf($confKey));

        try {
            $dbh = new PDO($conf['dsn'], $conf['user'], $conf['password'], $conf['options']);

            if ($conf['set_timezone']) {
                $sql = 'SET SESSION time_zone = "' . date('P') . '"';
                if ($dbh->exec($sql) === false) {
                    throw new \PDOException("Ошибка установки часового пояса MySQL-сессии.\nЗапрос: $sql");
                }
            }
            self::$_cons[$confKey] = $dbh;
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
     * Закрыть соединение с базой.
     *
     * Ожидаем на входе имя db-настройки приложения, по которой было выполнено соединение.
     *
     * Метод используется для принудительного (явного) отключения от базы. В обычном режиме соединение будет закрыто
     * при завершении работы приложения, что вызовет разрушение всех PDO-объектов.
     *
     * @param string $confKey ключ во массиве соединений
     */
    public static function disconnect($confKey)
    {
        unset(self::$_cons[$confKey]);
    }

    /**
     * Запрещаем любое размножение объекта. Установка private доступа к этим методам не позволит выполнить
     * соответствующие действия над объектом.
     *
     * @throws \Exception
     */
    private function __construct()
    {
        throw new \Exception('Создание объекта запрещено. Только статичное использование.');
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
