<?php
namespace engine\db;

use \engine\App;

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
     * @var \PDO дескриптор соединения с БД
     */
    private $dbh;

    /**
     * Объект хранит результирующий набор, соответствующий выполненному запросу.
     * @var \PDOStatement
     */
    private $sth = null;

    /**
     * Тест запроса, с плейсходерами. Только для отладки.
     * @var string
     */
    private $sql = '';

    /**
     * Связываемые параметры. Как раз те значения, что будут подставляться в запрос. Только для отладки.
     * @var array
     */
    protected $binds = [];

    /**
     * Имя таблицы, сразу в обратных кавычках. Если явно не задано, вычисляем от FQN имени класса. Суффикс "Model"
     * будет отброшен, регистр букв сохраняется.
     * @var string
     */
    protected $table;

    /**
     * Первичный ключ. Составной описывать, как массив. Или строкой? Пока не знаю.
     * @var string|array
     */
    protected $pk = 'id';

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
     * @throws \Exception
     */
    public function getConnection()
    {
        if (!$this->dbh) {
            throw new \Exception ('Нет соединения с БД');
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
            throw new \LogicException ('Сначала нужно выполнить запрос, см. метод DBModel::query()');
        }
        return $this->sth;
    }

    /**
     * Подключить модель к другой базе
     *
     * @param string $confKey ключ конфига, описывающий подключение к БД
     * @return object указатель на себя же
     */
    public function switchConnection($confKey)
    {
        $this->_confKey = $confKey;
        $this->dbh = DbConnection::connect($confKey);
        return $this;
    }

    /**
     * Отправляем запрос в БД.
     *
     * Подготовка запроса и его выполнение. Перехват ошибок, сбор информации для отладки.
     *
     * Логика исключений повторяет DBConnection::connect(). Ну почти повторяет :)
     *
     * @param string $sql    текст запроса
     * @param array  $params значения для подстановки в запрос
     * @return $this
     * @throws \LogicException
     * @throws \PDOException
     * @throws \Exception
     */
    public function query($sql, $params = [])
    {
        if (!$sql) {
            throw new \LogicException('Не указан текст запроса');
        }

        if (DEBUG) {
            $this->sql = $sql;
            $this->binds = $params;
        }

        $this->sth = $this->dbh->prepare($sql);
        //$this->sth->debugDumpParams(); exit;//DBG

        try {
            $this->sth->execute($params);
        } catch (\PDOException $e) {
            if (DEBUG) {
                throw $e;
            }

            $msg = $e->getMessage();
            $trace = $e->getTrace();
            if (isset($trace[1])) {
                $msg .= PHP_EOL . 'Запрос отправлен из ' . str_replace(ROOT_PATH, '', $trace[1]['file'])
                    . '(' . $trace[1]['line'] . ') ';
            }

            if (isset($trace[2])) {
                $msg .= $trace[2]['function'] . '(...)';
            }

            App::log()->addTyped($msg, \engine\Log::DB_QUERY);

            throw new \Exception($e->getMessage(), 0, $e);
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
        return new RowIterator($this->getStatement(), $style);
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
        $default = [
            'select'  => '*',
            'one_row' => true,
            'cond'    => '',
        ];
        $ops = array_merge($default, $ops);
        extract($ops);
        if ($select !== '*') {
            $select = '`' . preg_replace('~,\s*~', '`, `', $select) . '`';
        }

        $cond = "`{$field}` = ? " . $cond;

        $sql = "SELECT {$select} FROM {$this->table} WHERE {$cond}" . ($one_row ? ' LIMIT 1' : '');
        $params = [$value]; //параметры подстановки должны быть в массиве

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
     * @throws \Exception
     */
    public function getLastId()
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Инициализация транзакции
     * @throws \Exception
     */
    public function beginTransaction()
    {
        $this->getConnection()->beginTransaction();
    }

    /**
     * Проверка: транзакция в процессе
     * @return bool
     * @throws \Exception
     */
    public function inTransaction()
    {
        return $this->getConnection()->inTransaction();
    }

    /**
     * Завершение транзакции
     * @throws \Exception
     */
    public function commit()
    {
        $this->getConnection()->commit();
    }

    /**
     * Откат транзакции
     * @throws \Exception
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
     * @throws \Exception из getConnection()
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
     * После подстановок сохраняем итоговый запрос и очищаем массив параметров за ненадобностью.
     *
     * @return string
     */
    public function getLastQuery()
    {
        if (!DEBUG) {
            return 'Заполненный текст запроса доступен только в DEBUG-режиме.';
        }

        if (!$sql = &$this->sql) {
            return 'Модель еще не выполнила ни одного запроса. Нечего показать.';
        }

        if ($binds = &$this->binds) {
            $replaces = [];
            foreach ($binds as $placeholder => $value) {
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

            $binds = [];
        }

        return $sql;
    }
}
