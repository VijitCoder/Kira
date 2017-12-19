<?php
namespace kira\db;

use kira\core\App;
use kira\core\Singleton;
use kira\exceptions\ConfigException;
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
 * См. документацию, "DB".
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
     * Запоминаем дескриптор подключения во внутреннем кеше.
     *
     * Опционально ('mysql_timezone'): после установки соединения отправляется запрос на установку часового пояса
     * сессии.
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

        try {
            $conf = array_merge(['options' => null, 'mysql_timezone' => '+00:00'], App::conf($confKey));

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
                $msg .= PHP_EOL . 'Инициатор подключения ' . str_replace(KIRA_ROOT_PATH, '', $trace[2]['file'])
                    . '(' . $trace[2]['line'] . ')';
            }

            if (isset($trace[3])) {
                $msg .= ' функция ' . $trace[3]['function'] . '(...)';
            }

            throw new DbException($msg, DbException::CONNECT, $e);
        } catch (ConfigException $e) {
            throw new DbException($e->getMessage(), DbException::LOGIC, $e);
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
