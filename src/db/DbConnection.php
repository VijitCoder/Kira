<?php
namespace kira\db;

use kira\core\App;
use kira\core\Singleton;
use kira\exceptions\DbException;

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
 */
final class DbConnection
{
    use Singleton;

    /**
     * Объекты подключения к БД. Одна БД + юзер = один объект
     * @var \PDO[]
     */
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
     * Единственный источник исключения PDOException тут - это сам конструктор PDO. В трейсе он будет занимать первую
     * позицию, потом будет модель, вызвавшая этот метод и только потом возможно одна/две позиции по клиентскому коду.
     * Поэтому такая глубина разбора трассировки.
     *
     * @param string $confKey ключ в настройках, по которому хранится массив с кофигурацией подключения к БД
     * @return \PDO объект подключения к БД
     * @throws DbException
     */
    public static function connect(string $confKey)
    {
        if (isset(self::$cons[$confKey])) {
            return self::$cons[$confKey];
        }

        $conf = array_merge(['options' => null, 'mysql_timezone' => '+00:00'], App::conf($confKey));

        try {
            $dbh = new \PDO($conf['dsn'], $conf['user'], $conf['password'], $conf['options']);

            if ($tz = $conf['mysql_timezone']) {
                $sql = 'SET time_zone = ?';
                if (false === $dbh->prepare($sql)->execute([$tz])) {
                    throw new DbException('Ошибка установки часового пояса MySQL-сессии.' . PHP_EOL
                        . 'Запрос: ' . str_replace('?', "'$tz'", $sql));
                }
            }

            self::$cons[$confKey] = $dbh;
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            $trace = $e->getTrace();
            if (isset($trace[2])) {
                $msg .= PHP_EOL . 'Инициатор подключения ' . str_replace(ROOT_PATH, '', $trace[2]['file'])
                    . '(' . $trace[2]['line'] . ')';
            }

            if (isset($trace[3])) {
                $msg .= ' функция ' . $trace[3]['function'] . '(...)';
            }

            throw new DbException($msg, DbException::CONNECT, $e);
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
    public static function disconnect(string $confKey)
    {
        unset(self::$cons[$confKey]);
    }
}
