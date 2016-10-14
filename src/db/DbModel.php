<?php
namespace kira\db;

use kira\exceptions\DbException;

/**
 * Супер-класс моделей. Подключение и методы работы с БД.
 *
 * Обертки методов PDO не поддерживают доп.параметры, которые могут быть в исходных методах. Если требуется точное
 * использование какого-то PDO-метода, в классе есть два геттера для получения объектов PDO и PDOStatement.
 *
 * По вопросам PDO {@link http://phpfaq.ru/pdo} Очень полезная статья.
 *
 * Одна из проблем PDO: поддержка IN()-выражений. Реализацию см. в self::prepareIN()
 */
class DbModel
{
    /**
     * Имя таблицы, сразу в обратных кавычках.
     *
     * Если явно не задано, вычисляем от FQN имени класса. Суффикс "Model" будет отброшен, регистр букв сохраняется.
     *
     * @var string
     */
    protected $table;

    /**
     * Первичный ключ. Составной описывать, как массив. Или строкой? Пока не знаю.
     * @var string|array
     */
    protected $pk = 'id';

    /**
     * @var \PDO дескриптор соединения с БД
     */
    private $dbh;

    /**
     * Объект хранит результирующий набор, соответствующий выполненному запросу.
     * @var \PDOStatement
     */
    private $sth = null;

    /**
     * Тест запроса, с плейсходерами
     * @var string
     */
    private $sqlText = '';

    /**
     * Связываемые параметры. Как раз те значения, что будут подставляться в "prepared statement".
     * @var array
     */
    private $bindingParams = [];

    /**
     * Конструктор.
     *
     * Определяем имя таблицы, если оно не задано в свойствах модели-наследника. Подключаемся к базе по указанной
     * конфигурации. Манипуляции с конфигами позволяют подключать модели к разным базам и/или с разными учетками.
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

        $this->dbh = DbConnection::connect($confKey);
    }

    /**
     * Возвращает дескриптор соединения с БД
     * @return \PDO
     * @throws DbException
     */
    public function getConnection()
    {
        if (!$this->dbh) {
            throw new DbException('Нет соединения с БД');
        }
        return $this->dbh;
    }

    /**
     * Возвращает объект \PDOStatement для непосредственного обращения к методам класса
     * @return \PDOStatement
     * @throws \LogicException
     */
    public function getStatement()
    {
        if (!$this->sth) {
            throw new \LogicException('Сначала нужно выполнить запрос, см. метод DBModel::query()');
        }
        return $this->sth;
    }

    /**
     * Геттер: имя таблицы, которой соответствует текущая модель
     * @return string
     */
    public function getTableName()
    {
        return $this->table;
    }

    /**
     * Подключить модель к другой базе
     *
     * @param string $confKey ключ конфига, описывающий подключение к БД
     * @return $this указатель на себя же
     * @throws DbException через connect()
     */
    public function switchConnection($confKey)
    {
        $this->dbh = DbConnection::connect($confKey);
        return $this;
    }

    /**
     * Отправляем запрос в БД.
     *
     * Подготовка запроса и его выполнение. Перехват ошибок, сбор информации для отладки.
     *
     * Логика исключений повторяет DBConnection::connect(). Ну почти повторяет :) Тут источником кипиша могут быть
     * какие-то PDO классы, они займут первую позицию в трейсе. Сразу за ними - возможная инфа о клиентском коде,
     * которую и пытаемся добыть для дополнения сообщения.
     *
     * @param string $sql    текст запроса
     * @param array  $params значения для подстановки в запрос
     * @return $this
     * @throws \LogicException
     * @throws DbException
     */
    public function query($sql, $params = [])
    {
        if (!$sql) {
            throw new \LogicException('Не указан текст запроса');
        }

        $this->sqlText = $sql;
        $this->bindingParams = $params;

        try {
            $this->sth = $this->dbh->prepare($sql);
            //$this->sth->debugDumpParams(); exit;//DBG
            $this->sth->execute($params);
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            $trace = $e->getTrace();
            if (isset($trace[1])) {
                $msg .= PHP_EOL . 'Запрос отправлен из ' . str_replace(ROOT_PATH, '', $trace[1]['file'])
                    . '(' . $trace[1]['line'] . ') ';
            }

            if (isset($trace[2])) {
                $msg .= 'функция ' . $trace[2]['function'] . '(...)';
            }

            throw new DbException($msg, DbException::QUERY, $e);
        }

        return $this;
    }

    /**
     * Получить один ряд из результата запроса.
     *
     * Обертка для \PDOStatement::fetch(), но с меньшими возможностями.
     *
     * Константы PDO::FETCH_* {@link http://php.net/manual/ru/pdostatement.fetch.php}
     *
     * @param int $style в каком стиле выдать результат
     * @return mixed
     * @throws \LogicException
     */
    public function fetch($style = \PDO::FETCH_ASSOC)
    {
        return $this->getStatement()->fetch($style);
    }

    /**
     * Получить весь результат запроса.
     *
     * Обертка для \PDOStatement::fetchAll(), но с меньшими возможностями.
     *
     * Константы PDO::FETCH_* {@link http://php.net/manual/ru/pdostatement.fetch.php}
     *
     * @param int $style в каком стиле выдать результат
     * @return mixed
     * @throws \LogicException
     */
    public function fetchAll($style = \PDO::FETCH_ASSOC)
    {
        return $this->getStatement()->fetchAll($style);
    }

    /**
     * Получить значение одного поля из одного ряда запроса.
     *
     * Если запросом ничего не получено, следовательно нет данных поля. Возвращаем FALSE. Так можно отличить реальный
     * результат запроса от "нет данных". Т.е. из БД не может прийти FALSE, а все остальное - запросто.
     *
     * @param int $field имя поля
     * @return mixed|FALSE
     * @throws \LogicException
     */
    public function fetchField($field)
    {
        $row = $this->getStatement()->fetch(\PDO::FETCH_ASSOC);
        return $row[$field] ?? false;
    }

    /**
     * Получить итератор для обхода результата запроса.
     *
     * Константы PDO::FETCH_* {@link http://php.net/manual/ru/pdostatement.fetch.php}
     *
     * @param int $style в каком стиле выдать результат
     * @return RowIterator
     * @throws \LogicException
     */
    public function getIterator($style = \PDO::FETCH_ASSOC)
    {
        return new RowIterator($this->getStatement(), $this->bindingParams, $style);
    }

    /**
     * Возвращает количество строк, модифицированных последним SQL запросом
     * @return int
     */
    public function effect()
    {
        return $this->getStatement()->rowCount();
    }

    /**
     * Поиск записи по заданному полю.
     *
     * Например, найти юзера по id или логину.. или мылу :)
     *
     * Доп. параметры, передаваемые в $ops, описываются массивом:
     * <pre>
     * [
     *   'select'  => string | '*',  поля для выбора. Без экранирования, просто через запятую.
     *   'one_row' => bool | true,   ожидаем одну запись?
     *   'cond'    => string | '',   доп.условия. Прям так и писать, 'AND|OR ...'.
     * ]
     * </pre>
     *
     * @param string $field по какому полю искать
     * @param string $value значение поля для подстановки в запрос
     * @param array  $ops   доп.параметры
     * @return mixed
     */
    public function findByField($field, $value, $ops = [])
    {
        // @see http://php.net/manual/ru/migration70.new-features.php#migration70.new-features.null-coalesce-op
        $select = $ops['select'] ?? '*';
        $one_row = $ops['one_row'] ?? true;
        $cond = $ops['cond'] ?? '';

        if ($select !== '*') {
            $select = '`' . preg_replace('~,\s*~', '`, `', $select) . '`';
        }

        $cond = "`{$field}` = ? " . $cond;

        $sql = "SELECT {$select} FROM {$this->table} WHERE {$cond}" . ($one_row ? ' LIMIT 1' : '');
        $params = [$value]; // параметры подстановки должны быть в массиве

        $result = $this->query($sql, $params);
        return $one_row ? $result->fetch() : $result->fetchAll();
    }

    /**
     * Возвращает ID последней вставленной строки.
     *
     * Имеет смысл только в MySQL. С другими СУБД результат не гарантирован.
     *
     * Прим.: если используете транзакцию, вызов этого метода должен быть до коммита, иначе получите 0.
     * Источник {@link http://php.net/manual/ru/pdo.lastinsertid.php#107622}
     *
     * @return int
     * @throws DBException
     */
    public function getLastId()
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Инициализация транзакции
     * @throws DBException
     */
    public function beginTransaction()
    {
        $this->getConnection()->beginTransaction();
    }

    /**
     * Проверка: транзакция в процессе
     * @return bool
     * @throws DBException
     */
    public function inTransaction()
    {
        return $this->getConnection()->inTransaction();
    }

    /**
     * Завершение транзакции
     * @throws DBException
     */
    public function commit()
    {
        $this->getConnection()->commit();
    }

    /**
     * Откат транзакции
     * @throws DBException
     */
    public function rollBack()
    {
        $this->getConnection()->rollBack();
    }

    /**
     * Поддержка подстановок в IN()-выражения.
     *
     * Функция в роли модификатора, меняет текст запроса и параметры по ссылкам. Возможен вызов по цепочке. Пример
     * использования:
     *
     * $sql = 'SELECT * FROM users WHERE id IN(:ids) AND status IN (:statuses) AND role = :role';
     * $params = [':ids' => [1, 4, 56], ':statuses' => ['active', 'new'], ':role' => 'user'];
     * $query = (new UserModel)->prepareIN($sql, $params)->query(compact('sql', 'params'));
     *
     * Суть: экранируем строковые значения, объединяем все через запятую и заменяем плейсхолдер прям в sql-запросе на
     * полученный результат. Убираем из параметров задействованные подстановки. Один вызов метода готовит сразу все
     * IN-подстановки.
     *
     * Подстановка возможна только для именованного плейсходлера, т.к. заменить какой-то из кучи безымянных (помеченных
     * знаком вопроса) - нетривиальная задача. Проще тогда написать свой парсер целиком.
     *
     * Текстовые значения будут экранированы, другие значения останутся без изменений. Содержимое всего массива
     * определяется по его первому элементу, т.е. считается что в нем однотипные данные.
     *
     * Для экранирования строк используется PDO::quote() {@see http://php.net/manual/en/pdo.quote.php}
     * У него тоже есть ограничения, но это лучше, чем ничего.
     *
     * @param string &$sql    текст запроса с плейсходерами
     * @param array  &$params массив замен. Если элемент сам является массивом, обрабатываем. Иначе оставляем, как есть.
     * @return $this
     * @throws \LogicException
     * @throws DBException из getConnection()
     */
    public function prepareIN(&$sql, &$params)
    {
        $replaces = [];

        foreach ($params as $name => $value) {
            if (is_array($value)) {
                if (is_int($name)) {
                    throw new \LogicException('Подстановка массива возможна только для именованного плейсходера.');
                }

                if (is_string(current($value))) {
                    $dbh = $this->getConnection();
                    $value = array_map([$dbh, 'quote'], $value);
                }

                $replaces[$name] = implode(',', $value);

                unset($params[$name]);
            }
        }

        if ($replaces) {
            $sql = str_replace(array_keys($replaces), $replaces, $sql);
        }

        return $this;
    }

    /**
     * Готовим ассоциативный массив данных для вставки в PDO запрос.
     *
     * Метод сокращает количество писанины, когда нужно к каждому ключу ассоциативного массива данных приписать
     * двоеточие для передачи его в качестве массива подстановок.
     *
     * @var array $keys массив заготовок ключей, без ":" в начале.
     * @var array $data массив данных. По заготовкам ключей в нем ищем подходящие данные
     * @return array
     */
    protected function valueSet($keys, $data)
    {
        $values = array();
        foreach ($keys as $k) {
            $values[':' . $k] = isset($data[$k]) ? $data[$k] : null;
        }
        return $values;
    }

    /**
     * Текст последнего запроса с подставленными в него значениями.
     *
     * Функция только для отладки, не использовать в реальном обращении к серверу БД. Экранирование строк полагается
     * на функцию PDO::quote().
     *
     * @return string
     */
    public function getLastQuery()
    {
        if (!DEBUG) {
            return 'Заполненный текст запроса доступен только в DEBUG-режиме.';
        }

        if (!$sql = $this->sqlText) {
            return 'Модель еще не выполнила ни одного запроса. Нечего показать.';
        }

        if ($this->bindingParams) {
            foreach ($this->bindingParams as $placeholder => $value) {
                if (is_null($value)) {
                    $value = 'NULL';
                } else if (is_string($value)) {
                    $value = $this->dbh->quote($value);
                }

                if (is_int($placeholder)) {
                    $placeholder = '\?';
                }

                $placeholder = "/$placeholder/";
                $sql = preg_replace($placeholder, $value, $sql, 1);
            }
        }

        return $sql;
    }
}