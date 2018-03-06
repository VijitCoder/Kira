<?php
namespace kira\db\specifications;

use kira\core\Dto;
use kira\db\RowIterator;

/**
 * Результат запроса, выполненного с пагинацией (спецификация метода модели)
 */
class PaginateSpec extends Dto
{
    /**
     * Записи результата запроса
     *
     * @var RowIterator
     */
    public $rows;

    /**
     * Сколько записей найдено на заданную страницу
     *
     * @var int
     */
    public $pageRowsCount;

    /**
     * Сколько записей вообще есть по заданному запросу
     *
     * @var int
     */
    public $allRowsCount;
}
