<?php
/**
 * Супер-класс моделей. Подключение и методы работы с БД
 * По вопросам PDO {@see http://phpfaq.ru/pdo} Очень полезная статья.
 */

namespace engine\db;

use \engine\App;

class Model
{
    /** @var PDO дескриптор соединения с БД */
    private $_dbh;

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
     * Конструктор.
     *
     * Манипуляции с конфигами позволяют подключать модели к разным базам и/или с разными учетками.
     *
     * @param string $confKey ключ конфига, описывающий подключение к БД
     */
    public function __construct($confKey = 'db')
    {
        if (!$this->table) {
            $reflect = new \ReflectionClass($this);
            $name = $reflect->getShortName();
            $this->table = '`' . preg_replace('~Model$~i', '', $name) . '`';
        }

        $this->_dbh = DbConnection::connect($confKey);
    }

    /**
     * Возвращает дескриптор соединения с БД. Он нужен, например, для запуска транзакции.
     *
     * @return PDO
     */
    public function getConnection()
    {
        return $this->_dbh;
    }

    /**
     * Подключить модель к другой базе
     *
     * @param string $confKey ключ конфига, описывающий подключение к БД
     * @return object указатель на себя же
     */
    public function switchConnection($confKey)
    {
        $this->_dbh = DbConnection::connect($confKey);
        return $this;
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
     * Выполняем запрос.
     *
     * Соединились, подготовили запрос, выполнили. Результат вернули.
     *
     * Это основная функция моделей, для запросов прямым текстом (с подстановками). Хочется чего-то особенного?
     * Нивапрос! Пишите метод в своей модели, вызывайте connect() и дальше в ней все самостоятельно.
     *
     * Обязательные параметры:
     *
     *  q = query - текст запроса, прямым текстом с плейсхолдерами, если нужно.
     *
     * Необязательные параметры:
     * <ul>
     * <li>p = params - массив параметров для PDOStatement</li>
     * <li>fs = fetch style - в каком стиле выдать результат SELECT, см. константы тут
     * {@see http://php.net/manual/ru/pdostatement.fetch.php}</li>
     * <li>one = fetch one row - (bool) ожидаем только один ряд. В результате будет меньшая вложенность массива.</li>
     * <li>guess - bool|string - тип запроса в нотации CRUD. Либо определим по первому глаголу (наугад) либо явно
     * указать, какой запрос. По факт функция вернет выборку или количество рядов. Fallback-ситация: вернет
     * количество рядов.</li>
     * </ul>
     *
     * Логика исключений повторяет DBConnection::connect()
     *
     * @param $ops
     * @return mixed
     * @throws \PDOException
     * @throws \Exception
     */
    public function query($ops)
    {
        $default = array(
            'q'     => null,
            'p'     => array(),
            'fs'    => \PDO::FETCH_ASSOC,
            'one'   => false,
            'guess' => true,
        );
        $ops = array_merge($default, $ops);
        extract($ops);

        if (!$q) {
            throw new \Exception('Не указан текст запроса');
        }

        $sth = $this->_dbh->prepare($q);
        //$sth->debugDumpParams(); exit;//DBG

        try {
            $sth->execute($p);
        } catch (\PDOException $e) {
            if (DEBUG) {
                throw $e;
            }

            $msg = $e->getMessage();
            $trace = $e->getTrace();
            if (isset($trace[1])) {
                $msg .= "\nЗапрос отправлен из " . str_replace(ROOT_PATH, '', $trace[1]['file'])
                    . '(' . $trace[1]['line'] .') ';
            }

            if (isset($trace[2])) {
                $msg .= $trace[2]['function'] . '(...)';
            }

            App::log()->addTyped($msg, \engine\Log::DB_QUERY);

            throw new \Exception($e->getMessage(), 0, $e);
        }

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
