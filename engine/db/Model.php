<?php
/**
 * Супер-класс моделей. Подключение и методы работы с БД.
 *
 * По вопросам PDO {@link http://phpfaq.ru/pdo} Очень полезная статья.
 */

namespace engine\db;

use \engine\App;

class Model
{
    /** @var PDO дескриптор соединения с БД */
    private $_dbh;

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
        $this->_confKey = $confKey;
        $this->_dbh = DbConnection::connect($confKey);
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
     *   'one_row' => bool | false,
     *   'guess'   => bool | string | true,
     * ]
     * </pre>
     *
     * <ul>
     * <li>sql - текст запроса, прямым текстом с плейсхолдерами, если нужно.</li>
     * <li>params - массив параметров для PDOStatement</li>
     * <li>style - в каком стиле выдать результат SELECT, см. константы тут
     * {@see http://php.net/manual/ru/pdostatement.fetch.php}</li>
     * <li>one_row - TRUE = ожидаем только один ряд. В результате будет меньшая вложенность массива.</li>
     * <li>guess - bool|string - тип запроса в нотации CRUD. Либо определим по первому глаголу (наугад) либо явно
     * указать, какой запрос. По факт функция вернет выборку или количество рядов. Fallback-ситация: вернет
     * количество рядов. ПО умолчанию - TRUE.</li>
     * </ul>
     *
     * Логика исключений повторяет DBConnection::connect()
     *
     * @param string|array $ops текст запроса ИЛИ детальные настройки предстоящего запроса
     * @return array | int
     * @throws \Exception
     */
    public function query($ops)
    {
        $default = [
            'sql'     => null,
            'params'  => [],
            'style'   => \PDO::FETCH_ASSOC,
            'one_row' => false,
            'guess'   => true,
        ];

        if (is_string($ops)) {
            $ops = ['sql' => $ops];
        }

        $ops = array_merge($default, $ops);
        extract($ops);

        if (!$sql) {
            throw new \Exception('Не указан текст запроса');
        }

        $sth = $this->_dbh->prepare($sql);
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
                $msg .= "\nЗапрос отправлен из " . str_replace(ROOT_PATH, '', $trace[1]['file'])
                    . '(' . $trace[1]['line'] . ') ';
            }

            if (isset($trace[2])) {
                $msg .= $trace[2]['function'] . '(...)';
            }

            App::log()->addTyped($msg, \engine\Log::DB_QUERY);

            throw new \Exception($e->getMessage(), 0, $e);
        }

        if ($guess === true) {
            if (preg_match('~select|update|insert|delete~i', $sql, $m)) {
                $guess = $m[0];
            }
        }

        return ($guess && strtolower($guess) == 'select')
            ? ($one_row ? $sth->fetch($style) : $sth->fetchAll($style))
            : $sth->rowCount();
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
     * @param string $params параметры для подстановки в запрос
     * @param array  $ops    доп.параметры
     * @return array
     */
    public function findByField($field, $params, $ops = [])
    {
        $default = [
            'select'  => '*',
            'one_row' => true,
            'cond'    => '',
        ];
        $ops = array_merge($default, $ops);
        extract($ops);
        if ($select !== '*') {
            $select = '`' . preg_replace('~,\s?~', '`, `', $select) . '`';
        }

        $cond = "`{$field}` = ? " . $cond;

        $sql = "SELECT {$select} FROM {$this->table} WHERE {$cond}" . ($one_row ? ' LIMIT 1' : '');
        $params = [$params]; //параметры подстановки должны быть в массиве
        return $this->query(compact('sql', 'params', 'one_row'));
    }
}
