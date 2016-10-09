<?php
namespace kira\db;

use kira\exceptions\DbException;

/**
 * Итератор для результата запроса в БД
 *
 * Итераторы удобны тем, что в память выгружаются не все данные, а только один элемент массива. Применительно к запросу
 * в базу: не все записи, а только одна. Экономия памяти в случае большого результата запроса. При этом, реализуя
 * интерфейс \Iterator, получаем возможность обхода всех записей через цикл.
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
     * В каком стиле выдать результат, см. \PDO::FETCH_*
     * @var int|null
     */
    private $fetchStyle = null;

    private $key = 0;

    private $current = null;

    /**
     * Конструктор
     *
     * Сразу пробуем получить первую запись запроса. Тогда обращение к current() вне цикла будет обеспечено данными.
     *
     * @param \PDOStatement $statement
     * @param array         $params     значения для подстановки в запрос, если они имеются
     * @param int           $fetchStyle в каком стиле выдать результат, см. \PDO::FETCH_*
     */
    public function __construct(\PDOStatement $statement, array $params = [], int $fetchStyle = \PDO::FETCH_ASSOC)
    {
        $this->sth = $statement;
        $this->params = $params;
        $this->fetchStyle = $fetchStyle;
        $this->current = $this->sth->fetch($this->fetchStyle);
    }

    public function rewind()
    {
        try {
            $this->sth->execute($this->params);
        } catch (\PDOException $e) {
            throw new DbException('Ошибка запроса при перемотке в итераторе', DbException::QUERY, $e);
        }
        $this->key = 0;
        $this->current = $this->sth->fetch($this->fetchStyle);
    }

    public function key()
    {
        return $this->key;
    }

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
