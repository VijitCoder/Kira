<?php
/**
 * Супер-класс моделей. Подключение и методы работы с БД.
 *
 * По вопросам PDO {@link http://phpfaq.ru/pdo} Очень полезная статья.
 *
 * Одна из проблем PDO: поддержка IN()-выражений. Реализацию см. в self::prepareIN()
 */

namespace engine\db;

use \engine\App;

class Model
{
    /** @var PDO дескриптор соединения с БД */
    private $dbh;

    /**
     * @var string тест запроса, с плейсходерами. Только для отладки.
     */
    private $sql = '';

    /**
     * Связываемые параметры. Как раз те значения, что будут подставляться в запрос. Только для отладки.
     *
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
     * @var string|array первичный ключ. Составной описывать, как массив. Или строкой? Пока не знаю.
     * По умолчанию - 'id'
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
     * Возвращает дескриптор соединения с БД. Он нужен, например, для запуска транзакции.
     *
     * @return PDO
     */
    public function getConnection()
    {
        return $this->dbh;
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
     * Выполняем запрос.
     *
     * Это основная функция моделей, для запросов прямым текстом (с подстановками). Все действия в одном месте:
     * соединились, подготовили запрос, выполнили. Результат вернули.
     *
     * Параметрами метода может быть строка или ассоциативный массив. В первом случае ожидаем только текст запроса, без
     * подстановок. Полный массив параметров такой:
     * <pre>
     * [
     *   'sql'     => string
     *   'params'  => array | [],
     *   'style'   => const | \PDO::FETCH_ASSOC,
     *   'one_row' => bool | FALSE,
     *   'read'    => bool | NULL,
     * ]
     * </pre>
     *
     * <ul>
     * <li>sql - текст запроса, прямым текстом с плейсхолдерами, если нужно.</li>
     * <li>params - массив параметров для PDOStatement</li>
     * <li>style - в каком стиле выдать результат SELECT, см. константы тут
     * {@see http://php.net/manual/ru/pdostatement.fetch.php}</li>
     * <li>one_row - если ожидаем только один ряд, ставим TRUE. В результате будет меньшая вложенность массива.</li>
     * <li>read - bool - тип запроса в нотации CRUD. Запрос либо на чтение - TRUE, либо на изменение - FALSE. Если
     * параметр не задан, определим по первому глаголу (наугад). По факту функция вернет выборку или количество рядов.
     * Fallback-ситация: вернет количество рядов.</li>
     * </ul>
     *
     * Логика исключений повторяет DBConnection::connect(). Ну почти повторяет :)
     *
     * @param string|array $ops текст запроса ИЛИ детальные настройки предстоящего запроса
     * @return array | int
     * @throws \Exception
     * @throws \LogicException
     */
    public function query($ops)
    {
        $default = [
            'sql'     => null,
            'params'  => [],
            'style'   => \PDO::FETCH_ASSOC,
            'one_row' => false,
            'read'    => null,
        ];

        if (is_string($ops)) {
            $ops = ['sql' => $ops];
        }

        $ops = array_merge($default, $ops);
        extract($ops);

        if (!$sql) {
            throw new \Exception('Не указан текст запроса');
        }

        if (DEBUG) {
            $this->sql = $sql;
            $this->binds = $params;
        }

        $sth = $this->dbh->prepare($sql);
        //$sth->debugDumpParams(); exit;//DBG

        try {
            $sth->execute($params);
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

        if (is_null($read)) {
            if (preg_match('~select|update|insert|delete~i', $sql, $m)) {
                $read = strtolower($m[0]) == 'select';
            } else {
                $read = false;
            }
        }

        return $read
            ? ($one_row ? $sth->fetch($style) : $sth->fetchAll($style))
            : $sth->rowCount();
    }

    /**
     * Поддержка подстановок в IN()-выражения.
     *
     * Функция в роли модификатора, меняет текст запроса и параметры по ссылкам. Возможен вызов по цепочке. Пример
     * использования:
     *
     * $sql = 'SELECT * FROM table1 WHERE id IN(:ids) AND someType = :type';
     * $params = [':ids' => [id1, id2, ... idN], ':type' => 'First'];
     * $rows = (new Model)->prepareIN($sql, $params)->query(compact('sql', 'params'));
     *
     * Суть: экранируем строковые значения, объединяем все через запятую и заменяем плейсхолдер прям в sql-запросе на
     * полученный результат. Убираем из параметров задействованные подстановки.
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
     * @param &$sql    текст запроса с плейсходерами
     * @param &$params массив замен. Если элемент сам является массивом, обрабатываем. Иначе оставляем, как есть.
     * @return $this
     * @throws \LogicException
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
                    $value = array_map([$this->dbh, 'quote'], $value);
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
     * @param string $field  по какому полю искать
     * @param string $value  значение поля для подстановки в запрос
     * @param array  $ops    доп.параметры
     * @return array
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
        return $this->query(compact('sql', 'params', 'one_row'));
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
