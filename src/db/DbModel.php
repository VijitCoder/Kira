<?php
namespace kira\db;

use kira\db\specifications\PaginateSpec;
use kira\exceptions\DbException;
use kira\exceptions\DtoException;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Супер-класс моделей. Подключение и методы работы с БД.
 *
 * Обертки методов PDO не поддерживают доп.параметры, которые могут быть в исходных методах. Если требуется точное
 * использование какого-то PDO-метода, в классе есть два геттера для получения объектов PDO и PDOStatement.
 *
 * Одна из проблем PDO: поддержка IN()-выражений. Реализацию см. в self::prepareIN()
 *
 * См. документацию, "DB".
 */
class DbModel
{
    /**
     * Первичный ключ
     *
     * TODO Составной описывать, как массив. Или строкой? Пока не знаю.
     *
     * @var string|array
     */
    protected $pk = 'id';

    /**
     * @var PDO дескриптор соединения с БД
     */
    private $dbh;

    /**
     * Объект хранит результирующий набор, соответствующий выполненному запросу.
     *
     * @var PDOStatement
     */
    private $sth = null;

    /**
     * Связываемые параметры. Как раз те значения, что будут подставляться в "prepared statement".
     *
     * @var array
     */
    private $bindingParams = [];

    /**
     * Определяем имя таблицы, если оно не задано в свойствах модели-наследника. Подключаемся к базе по указанной
     * конфигурации.
     *
     * @param string $confKey ключ конфига, описывающий подключение к БД
     * @throws DbException
     */
    public function __construct(string $confKey = 'db')
    {
        $this->dbh = DbConnection::connect($confKey);
    }

    /**
     * Возвращает дескриптор соединения с БД
     *
     * @return PDO
     * @throws DbException
     */
    public function getConnection()
    {
        if (!$this->dbh) {
            throw new DbException('Нет соединения с БД', DbException::CONNECT);
        }
        return $this->dbh;
    }

    /**
     * Возвращает объект \PDOStatement для непосредственного обращения к методам класса
     *
     * @return PDOStatement
     * @throws DbException
     */
    public function getStatement()
    {
        if (!$this->sth) {
            throw new DbException('Сначала нужно выполнить запрос, см. метод DBModel::query()', DbException::LOGIC);
        }
        return $this->sth;
    }

    /**
     * Подключить модель к другой базе
     *
     * @param string $confKey ключ конфига, описывающий подключение к БД
     * @return $this
     * @throws DbException через connect()
     */
    public function switchConnection(string $confKey)
    {
        $this->dbh = DbConnection::connect($confKey);
        return $this;
    }

    /**
     * Отправляем запрос в БД
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
     * @throws DbException
     */
    public function query(string $sql, array $params = [])
    {
        if (!$sql) {
            throw new DbException('Не указан текст запроса', DbException::LOGIC);
        }

        $this->bindingParams = $params;

        try {
            $this->sth = $this->dbh->prepare($sql);
            //$this->sth->debugDumpParams(); exit;//DBG
            $this->sth->execute($params);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            $trace = $e->getTrace();
            if (isset($trace[1])) {
                $msg .= PHP_EOL . 'Запрос отправлен из ' . str_replace(KIRA_ROOT_PATH, '', $trace[1]['file'])
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
     * Получить один ряд из результата запроса
     *
     * Обертка для \PDOStatement::fetch(), но с меньшими возможностями.
     *
     * Если тут явно не указать стиль результата, во внимание будут приняты настройки подключения к БД.
     *
     * @param int $style в каком стиле выдать результат, константы \PDO::FETCH_*
     * @return mixed
     * @throws DbException
     */
    public function fetch(int $style = null)
    {
        return $this->getStatement()->fetch($style);
    }

    /**
     * Получить весь результат запроса
     *
     * Обертка для \PDOStatement::fetchAll(), но с меньшими возможностями.
     *
     * Если тут явно не указать стиль результата, во внимание будут приняты настройки подключения к БД.
     *
     * @param int $style в каком стиле выдать результат, константы \PDO::FETCH_*
     * @return mixed
     * @throws DbException
     */
    public function fetchAll(int $style = null)
    {
        return $this->getStatement()->fetchAll($style);
    }

    /**
     * Получить значение одного поля из одного ряда запроса
     *
     * Если запросом ничего не получено, следовательно нет данных поля. Возвращаем FALSE. Так можно отличить реальный
     * результат запроса от "нет данных". Т.е. из БД не может прийти FALSE, а все остальное - запросто.
     *
     * @param string $field имя поля
     * @return mixed|FALSE
     * @throws DbException
     */
    public function fetchValue(string $field)
    {
        $row = $this->getStatement()->fetch(PDO::FETCH_ASSOC);
        return $row && array_key_exists($field, $row) ? $row[$field] : false;
    }

    /**
     * Получить колонку (значение одного поля) из всех рядов запроса
     *
     * @param string $field имя поля
     * @return array
     * @throws DbException
     */
    public function fetchColumn(string $field)
    {
        $iterator = $this->getIterator(PDO::FETCH_ASSOC);
        if (!$current = $iterator->current()) {
            return [];
        }
        if (!isset($current[$field])) {
            throw new DbException("В результате запроса нет поля '{$field}'", DbException::QUERY);
        }
        $result = [];
        foreach ($iterator as $row) {
            $result[] = $row[$field];
        }
        return $result;
    }

    /**
     * Получить итератор для обхода результата запроса
     *
     * Если тут явно не указать стиль результата, во внимание будут приняты настройки подключения к БД.
     *
     * @param int $style в каком стиле выдать результат, константы \PDO::FETCH_*
     * @return RowIterator
     * @throws DbException
     */
    public function getIterator(int $style = null)
    {
        return new RowIterator($this->getStatement(), $this->bindingParams, $style);
    }

    /**
     * Возвращает количество строк, модифицированных последним SQL запросом
     *
     * @return int
     * @throws DbException
     */
    public function effect()
    {
        return $this->getStatement()->rowCount();
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
     *
     * @throws DBException
     */
    public function beginTransaction()
    {
        $this->getConnection()->beginTransaction();
    }

    /**
     * Проверка: транзакция в процессе
     *
     * @return bool
     * @throws DBException
     */
    public function inTransaction()
    {
        return $this->getConnection()->inTransaction();
    }

    /**
     * Завершение транзакции
     *
     * @throws DBException
     */
    public function commit()
    {
        $this->getConnection()->commit();
    }

    /**
     * Откат транзакции
     *
     * @throws DBException
     */
    public function rollBack()
    {
        $this->getConnection()->rollBack();
    }

    /**
     * Поддержка подстановок в IN()-выражения.
     *
     * Функция в роли модификатора, меняет текст запроса и параметры по ссылкам. Возможен вызов по цепочке.
     *
     * Экранируем строковые значения, объединяем все через запятую и заменяем плейсхолдер прям в sql-запросе на
     * полученный результат. Убираем из параметров задействованные подстановки. Один вызов метода готовит сразу все
     * IN-подстановки.
     *
     * Подстановка возможна только для именованного плейсходлера.
     *
     * Текстовые значения будут экранированы, другие значения останутся без изменений. Для экранирования строк
     * используется PDO::quote(). Содержимое всего массива определяется по его первому элементу, т.е. считается что
     * в нем однотипные данные.
     *
     * @param string &$sql    текст запроса с плейсходерами
     * @param array  &$params массив замен. Если элемент сам является массивом, обрабатываем. Иначе оставляем, как есть.
     * @return $this
     * @throws DBException
     */
    public function prepareIN(string &$sql, array &$params)
    {
        $replaces = [];

        foreach ($params as $name => $value) {
            if (!is_array($value)) {
                continue;
            }

            if (is_int($name)) {
                throw new DbException(
                    'Подстановка массива возможна только для именованного плейсходера.',
                    DbException::LOGIC
                );
            }

            if (is_string(current($value))) {
                $dbh = $this->getConnection();
                $value = array_map([$dbh, 'quote'], $value);
            }

            $key = $name[0] == ':' ? $name : ":{$name}";
            $replaces[$key] = implode(',', $value);

            unset($params[$name]);
        }

        if ($replaces) {
            $sql = str_replace(array_keys($replaces), $replaces, $sql);
        }

        return $this;
    }

    /**
     * Экранирование в строке всех вхождений спец.символов LIKE. Таких символов два: % и _.
     *
     * @param string $value исходное выражение
     * @return string
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\%', '\_'], $value);
    }

    /**
     * Готовим ассоциативный массив данных для вставки в PDO запрос.
     *
     * Метод сокращает количество писанины, когда нужно к каждому ключу ассоциативного массива данных приписать
     * двоеточие для передачи его в качестве массива подстановок.
     *
     * @var array $data ассоциативный массив данных. Ключи будут переименованы в такие же, но с преффиксом ":"
     * @return array
     */
    protected function valueSet(array $data): array
    {
        $values = [];
        foreach ($data as $k => $v) {
            $values[':' . $k] = $v;
        }
        return $values;
    }

    /**
     * Симуляция запроса
     *
     * Получаем текст запроса с подставленными в него значениями. Тест обернут в парный тег [pre] для удобства просмотра
     * и копипасты.
     *
     * Функция только для отладки, не использовать в реальном обращении к серверу БД. Экранирование строк полагается
     * на функцию PDO::quote().
     *
     * @param string $sql           тест запроса, возможно с подстановками
     * @param array  $bindingParams массив подстановок
     * @return string
     * @throws DbException
     */
    public function simulate(string $sql, array $bindingParams = [])
    {
        if (!KIRA_DEBUG) {
            throw new DbException(
                'Нельзя использовать этот метод в боевом режиме сайта. Он только для отладки.',
                DbException::LOGIC
            );
        }

        if ($bindingParams) {

            $this->prepareIN($sql, $bindingParams);

            foreach ($bindingParams as $placeholder => $value) {
                if (is_null($value)) {
                    $value = 'NULL';
                } else if (is_string($value)) {
                    $value = $this->dbh->quote($value);
                }

                if (is_int($placeholder)) {
                    $pattern = '/\?/';
                    $sql = preg_replace($pattern, $value, $sql, 1);
                } else {
                    $placeholder = ltrim($placeholder, ':');
                    $pattern = "/:$placeholder/";
                    $sql = preg_replace($pattern, $value, $sql);
                }
            }
        }

        return "<pre>$sql</pre>";
    }

    /**
     * Выполнение запроса с пагинацией
     *
     * Запрос выполняется с подстановкой параметров и подготовкой IN условий, даже если они не нужны. Запрос дополняется
     * нужными инструкциями для получения среза данных и вычисления общего количества записей.
     *
     * @param string $sql    текст запроса, без выражения LIMIT
     * @param array  $params параметры подстановки в запрос
     * @param int    $page   номер страницы в выдаче результата, начиная с единицы
     * @param int    $limit  количество записей на страницу
     * @return PaginateSpec
     * @throws DbException
     */
    protected function paginate(string $sql, array $params, int $page, int $limit): PaginateSpec
    {
        if ($page < 1 || $limit < 1) {
            throw new DbException('Неправильные параметры пагнинации. Ожидаются целые положительные числа');
        }

        $sql = preg_replace('/SELECT/i', 'SELECT SQL_CALC_FOUND_ROWS', $sql, 1);

        $offset = ($page - 1) * $limit;
        $sql .= " LIMIT {$offset}, {$limit}";

        try {
            $result = new PaginateSpec;
        } catch (DtoException $e) {
            // Никакой реакции, т.к. не будет исключения при текущем создании DTO. Но чтобы не светить отсюда двумя
            // исключениями, сделана пустая ловушка.
        }

        $result->rows = $this
            ->prepareIn($sql, $params)
            ->query($sql, $params)
            ->getIterator();

        $result->pageRowsCount = $this->effect();

        $result->allRowsCount = (int)$this->query('SELECT FOUND_ROWS() AS all_rows')->fetchValue('all_rows');

        return $result;
    }
}
