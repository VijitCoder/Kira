<?php
namespace kira\db;

use kira\exceptions\DbException;

/**
 * Итератор для результата запроса в БД
 *
 * Взял из движка ClickBlocks. Исправил ошибку с подстановкой параметров в методе rewind().
 *
 * @copyright  2007-2010 SARITASA LLC <info@saritasa.com>
 * @link       http://www.saritasa.com
 */
class RowIterator implements \Iterator
{
    /**
     * @var \PDOStatement
     */
    private $sth;

    /**
     * Связываемые параметры. Как раз те значения, что будут подставляться в "prepared statement".
     * @var array
     */
    protected $params;

    /**
     * В каком стиле выдать результат, константы \PDO::FETCH_*
     * @var int|null
     */
    private $fetchStyle = null;

    /**
     * Ключе текущего элемента в массиве итератора
     * @var int
     */
    private $key = 0;

    /**
     * Значение текущего элемента в массиве итератора
     * @var mixed|null
     */
    private $current = null;

    /**
     * Запоминаем объект и параметры запроса.
     *
     * Сразу пробуем получить первую запись запроса. Тогда обращение к current() вне цикла будет обеспечено данными.
     *
     * Если тут явно не указать стиль результата, во внимание будут приняты настройки подключения к БД.
     *
     * @param \PDOStatement $statement
     * @param array         $params     значения для подстановки в запрос, если они имеются
     * @param int           $fetchStyle в каком стиле выдать результат, см. \PDO::FETCH_*
     */
    public function __construct(\PDOStatement $statement, array $params = [], int $fetchStyle = null)
    {
        $this->sth = $statement;
        $this->params = $params;
        $this->fetchStyle = $fetchStyle;
        $this->current = $this->sth->fetch($this->fetchStyle);
    }

    /**
     * Перематываем итератор
     *
     * При этом будет выполнен повторный запрос в базу, иначе нельзя получить результат запроса. Т.о. использование
     * перемотки в принципе является плохой идеей. Но для полноты картины метод реализован.
     *
     * @return $this
     * @throws DbException
     */
    public function rewind()
    {
        try {
            $this->sth->execute($this->params);
        } catch (\PDOException $e) {
            throw new DbException('Ошибка запроса при перемотке в итераторе', DbException::QUERY, $e);
        }
        $this->key = 0;
        $this->current = $this->sth->fetch($this->fetchStyle);
        return $this;

    }

    public function key()
    {
        return $this->key;
    }

    /**
     * Получаем следующую запись результата запроса
     */
    public function next()
    {
        $this->current = $this->sth->fetch($this->fetchStyle);
        $this->key++;
    }

    public function current()
    {
        return $this->current;
    }

    public function valid()
    {
        return $this->current !== FALSE;
    }
}
