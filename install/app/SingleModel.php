<?php
namespace install\app;

/**
 * Единственная модель приложения.
 *
 * Прим: мы не можем использовать классы движка из engine\db\, потому что там параметры подключения читаются из
 * конфига приложения, а у нас его еще нет. Поэтому в этой модели своя обертка для PDO.
 *
 * TODO Можно ли это улучшить:
 * Явное неудобство в том, что нужно отдельно вызывать метод соединиения с базой и передавать в него конфиг соединения.
 * Другие методы модели полагают, что соединение есть. Так же из-за этого клиентский код выглядит коряво (получаем
 * соединение в переменную, но вызываем отдельные методы модели). Для текущей задачи не требуется сложнее, поэтому
 * не усложняю :)
 */
class SingleModel
{
    /**
     * Подключение к базе данных. NULL - не быпо попытки подключения, FALSE - подключение неудалось.
     * @var null|false|resource
     */
    private static $dbh = null;

    /**
     * Последняя ошибка, возникшая в модели
     * @var string
     */
    private static $lastError;

    /**
     * Соединяемся с базой.
     * В конфигурации нам нужны ['dsn', 'user', 'password'].
     * @param array $conf массив конфигурации для подключения к базе
     * @return mixed
     */
    public static function dbConnect(&$conf)
    {
        if (self::$dbh) {
            return self::$dbh;
        }

        try {
            $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,];
            self::$dbh = new \PDO($conf['dsn'], $conf['user'], $conf['password'], $options);
        } catch (\PDOException $e) {
            self::$lastError = $e->getMessage();
            self::$dbh = false;
        }

        return self::$dbh;
    }

    /**
     * Получаем текущее соединение в том виде, как оно есть.
     * @return false|null|resource
     */
    public static function getConnection()
    {
        return self::$dbh;
    }

    /**
     * Получить последнюю ошибку.
     * @return string
     */
    public static function getLastError()
    {
        return self::$lastError;
    }

    /**
     * Существует ли в текущей базе данных указанная таблица.
     * Метод работает на валидатор логера.
     * @param string $table имя проверяемой таблицы
     * @return bool|null возвращаем NULL в случае ошибки запроса.
     */
    public static function isTableExists($table)
    {
        try {
            $sth = self::$dbh->prepare('SHOW TABLES LIKE ?');
            $sth->execute([$table]);
            return (bool)$sth->fetch(\PDO::FETCH_NUM);
        } catch (\PDOException $e) {
            self::$lastError = $e->getMessage();
            return null;
        }
    }

    /**
     * Создаем таблицу логера.
     * Прим: кодировку не задаем явно. Будет та же, что у всей базы. Но движок таблицы все равно определяем - MyISAM.
     * @param string $table имя таблицы лога
     * @return bool
     */
    public static function createLogTable($table)
    {
        $result = self::$dbh->exec("
            CREATE TABLE `$table` (
                `id` int(12) unsigned NOT NULL AUTO_INCREMENT,
                `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата/время события',
                `timezone` char(10) NOT NULL COMMENT 'Часовой пояс, которому соответствует указанное время события',
                `logType` varchar(20) NOT NULL COMMENT 'Тип сообщения',
                `message` text NOT NULL COMMENT 'Сообщение',
                `userIP` char(15) DEFAULT '' COMMENT 'IPv4, адрес юзера, когда удалось его определить',
                `request` varchar(255) DEFAULT '' COMMENT 'URL запроса, в ходе обработки которого пишем лог',
                `source` varchar(100) DEFAULT '' COMMENT 'источник сообщения (функция, скрипт, какая-то пометка кодера)',
                PRIMARY KEY (`id`),
                KEY `ts` (`ts`),
                KEY `logType` (`logType`)
            ) ENGINE=MyISAM
        ");

        if ($result === false) {
            self::$lastError = implode(' ', self::$dbh->errorInfo());
        } else {
            $result = true;
        }

        return $result;
    }

    /**
     * Удалить таблицу логера
     * @param string $table имя таблицы лога
     * @return bool
     */
    public static function dropLogTable($table)
    {
        $result = self::$dbh->exec("DROP TABLE IF EXISTS `$table`");
        if ($result === false) {
            self::$lastError = implode(' ', self::$dbh->errorInfo());
        } else {
            $result = true;
        }
        return $result;
    }
}
