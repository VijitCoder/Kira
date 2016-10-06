<?php
namespace engine\DB;

/**
 * Итератор для результата запроса в БД
 *
 * Итераторы удобны тем, что в память выгружаются не все данные, а только один элемент массива. Применительно к запросу
 * в базу: не все записи, а только одна. Экономия памяти в случае большого результата запроса. При этом, реализуя
 * интерфейс \Iterator, получаем возможность обхода всех записей через цикл.

 * Взял из движка ClickBlocks
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

    private $fetchStyle = null;

    private $key = 0;

    private $current = null;

    /**
     * RowIterator constructor.
     * @param \PDOStatement $statement
     * @param int           $fetchStyle см. \PDO::FETCH_*
     */
    public function __construct($statement, $fetchStyle)
    {
        $this->sth = $statement;
        $this->fetchStyle = $fetchStyle;
    }

    public function rewind()
    {
        $this->sth->execute();
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
