<?php
/**
 * Супер-класс моделей. Подключение и методы работы с БД
 * По вопросам PDO {@see http://phpfaq.ru/pdo} Очень полезная статья.
 */

namespace core;

use PDO;

class Db
{
    /** @var PDO объект подключения к БД. Если равен false, значит подключение не удалось. */
    private static $_dbh = null;

    /**
     * @var string имя таблицы, сразу в обратных кавычках. Если явно не задано, вычисляем от FQN имени класса.
     * Суффикс "Model" будет отброшен, регистр букв сохраняется.
     */
    protected $table;

    /**
     * @var string|array первичный ключ. Составной описывать, как массив. Или строкой? Пока не знаю.
     * По умолчанию - 'id'
     */
    protected $pk = 'id';

    /**
     * Конструктор
     */
    public function __construct()
    {
        if (!$this->table) {
            $reflect = new \ReflectionClass($this);
            $name = $reflect->getShortName();
            $this->table = '`' . preg_replace('~Model$~i', '', $name) . '`';
        }
    }

    /**
     * Соединяемся с базой или получаем дескриптор подключения
     * прим: дескриптор соединения может понадобиться в сервисах при запуске транзакции. Поэтому публичный
     * статичный метод.
     * @return PDO объект подключения к БД
     */
    public static function connect()
    {
        if (!is_null(self::$_dbh)) {
            return self::$_dbh;
        }

        $conf = App::conf('db');

        //Тут можно ловить исключение. Сейчас исключения ловит перехватчик в App.php
        self::$_dbh = new PDO($conf['dsn'], $conf['user'], $conf['password'], $conf['options']);

        return self::$_dbh;
    }

    /**
     * Выполняем запрос. Пока ни каких наворотов с биндингом и т.д. Соединились, поготовили запрос, выполнили.
     * Результат вернули клиенту. Все.
     *
     * Это вспомогательная функция моделей, для простых запросов. Хочешь что-то особенное? Нивапрос! Пиши метод
     * в своей модели, вызывай connect() и дальше в ней все сам.
     *
     * Обязательные параметры:
     *  q = query - текст запроса, прямым текстом с плейсхолдерами, если нужно.
     *
     * Необязательные параметры:
     *  p = params - массив параметров для PDOStatement
     *  fs = fetch style - в каком стиле выдать результат при SELECT
     *  one = fetch one row - (bool) ожидаем только один ряд. В результате будет меньшая вложенность массива.
     *  guess - bool|string - тип запроса в нотации CRUD. Либо определим по первому глаголу (наугад) либо явно
     *  указать, какой запрос. По факт функция вернет выборку или количество рядов. Fallback-ситация: вернет
     *  количество рядов.
     *
     * @param $ops
     * @return mixed
     * @throws Exception
     */
    public function query($ops)
    {
        // параметры по умолчанию
        $default = array(
            'q'     => null,
            'p'     => array(),
            'fs'    => PDO::FETCH_ASSOC,
            'one'   => false,
            'guess' => true,
        );
        $ops = array_merge($default, $ops);
        extract($ops); //теперь у нас есть все переменные, включая не переданные в параметрах функции

        if (!$q) {
            throw new Exception('Не указан текст запроса');
        }

        $sth = $this->connect()->prepare($q);
        //$sth->debugDumpParams(); exit;//DBG

        /*
        Тут можно ловить ошибки PDOException и делать полезные штуки :) Откат транзакции, например, если она открыта
        через запрос. PDO-транзакция откатывается автоматически, {@see http://php.net/manual/ru/pdo.transactions.php}
        Сейчас исключения ловит перехватчик в App.php
        */
        $sth->execute($p);

        if ($guess === true) {
            if (preg_match('~select|update|insert|delete~i', $q, $m)) {
                $guess = $m[0];
            }
        }

        return ($guess && strtolower($guess) == 'select')
            ? ($one ? $sth->fetch($fs) : $sth->fetchAll($fs))
            : $sth->rowCount();
    }

    /**
     * Готовим ассоциативный массив данных для вставки в PDO запрос.
     * @var array $кеуs массив заготовок ключей, без ":" в начале.
     * @var array $data массив данных. По заготовкам ключей в нем ищем подходящие данные
     * @return array
     */
    protected function valueSet($кеуs, $data)
    {
        $values = array();
        foreach ($кеуs as $k) {
            $values[':' . $k] = isset($data[$k]) ? $data[$k] : null;
        }
        return $values;
    }

    /**
     * Поиск записи по заданному полю. Например найти юзера по id или логину.. или мылу :)
     * Или всех забанненых юзеров, всех женщин, всех в Томске, ну и т.д., применений - масса.
     * @param string $f   по какому полю искать
     * @param string $p   параметры для подстановки в запрос
     * @param array  $ops доп.параметры,  см. $dummy[] в коде
     * @return array
     */
    public function findByField($f, $p, $ops = array())
    {
        $dummy = [
            'select' => '*', //поля для выбора. Без экранирования, просто через запятую.
            'one'    => true,   //ожидаем одну запись?
            'cond'   => '',    //доп.условия. Прям так и писать, 'AND|OR ...'.
        ];
        $ops = array_merge($dummy, $ops);
        extract($ops);
        if ($select !== '*') {
            $select = '`' . preg_replace('~,\s?~', '`, `', $select) . '`';
        }
        $p = [$p]; //параметры подстановки должны быть в массиве

        $cond = "`{$f}` = ? " . $cond;

        $q = "SELECT {$select} FROM {$this->table} WHERE {$cond}" . ($one ? ' LIMIT 1' : '');
        return $this->query(compact('q', 'p', 'one'));
    }
}
