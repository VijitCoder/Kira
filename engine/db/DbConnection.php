<?php
namespace engine\db;

use PDO;
use engine\App;

/**
 * Подключение к базе.
 *
 * Синглтон. Конфигураций БД может быть несколько, класс хранит одно подключение по каждому конфигу.
 *
 * Конфигурация подключения в настройках приложения:
 * <pre>
 * 'db' => [
 *    'dsn'            => string,
 *    'user'           => string,
 *    'password'       => string,
 *    'options'        => array | []
 *    'mysql_timezone' => string | '+00:00'
 * ]
 * </pre>
 *
 * Ключ 'db' - ожидаемый по умолчанию. Почти все параметры соответствуют параметрам конструктора класса PDO,
 * {@link http://php.net/manual/en/pdo.construct.php}.
 *
 * Про 'options' можно почитать в PDO::setAttribute() {@link http://php.net/manual/ru/pdo.setattribute.php}
 *
 * Если указан часовой пояс в 'mysql_timezone', то после установки соединения отправляется запрос на установку часового
 * пояса сессии. Подробнее см. в доке "DB.md"
 *
 * Больше класс ничего не делает, только хранит подключения к базам.
 */
class DbConnection
{
    /** @var PDO объекты подключения к БД. Одна БД + юзер = один объект */
    private static $cons = [];

    /**
     * Соединяемся с базой или получаем дескриптор подключения.
     *
     * Запоминаем подключения по $confKey. Полагаем, что в настройках приложения для каждого ключа описано <i>уникальное
     * сочетание</i> базы/пользователя, поскольку идентичный конфиг не имеет смысла.
     *
     * Опционально ('mysql_timezone'): после установки соединения отправляется запрос на установку часового пояса сессии.
     * Подробнее см. в доке "DB.md"
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
        if (isset(self::$cons[$confKey])) {
            return self::$cons[$confKey];
        }

        $conf = array_merge(['options' => null, 'mysql_timezone' => '+00:00'], App::conf($confKey));

        try {
            $dbh = new PDO($conf['dsn'], $conf['user'], $conf['password'], $conf['options']);

            if ($tz = $conf['mysql_timezone']) {
                $sql = 'SET time_zone = ?';
                if (false === $dbh->prepare($sql)->execute([$tz])) {
                    throw new \PDOException('Ошибка установки часового пояса MySQL-сессии.' . PHP_EOL
                        . 'Запрос: ' . str_replace('?', "'$tz'", $sql));
                }
            }

            self::$cons[$confKey] = $dbh;
        } catch (\PDOException $e) {
            if (DEBUG) {
                throw $e;
            }

            $msg = $e->getMessage();
            $trace = $e->getTrace();
            if (isset($trace[2])) {
                $msg .= PHP_EOL . 'Инициатор подключения ' . str_replace(ROOT_PATH, '', $trace[2]['file'])
                    . '(' . $trace[1]['line'] . ') ';
            }

            if (isset($trace[3])) {
                $msg .= $trace[3]['function'] . '(...)';
            }

            App::log()->add(['message' => $msg, 'type' => \engine\Log::DB_CONNECT, 'file_force' => true]);

            throw new \Exception($e->getMessage(), 0, $e);
        }

        return self::$cons[$confKey];
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
        unset(self::$cons[$confKey]);
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
