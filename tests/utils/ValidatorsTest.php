<?php
use PHPUnit\Framework\TestCase;
use kira\utils\Validators;

/**
 * Тестируем валидаторы
 */
class ValidatorsTest extends TestCase
{
    public function test_date()
    {
        $result = Validators::date('22.10.2016');
        $this->assertEquals(['value' => '2016-10-22'], $result, 'Валидная дата');

        $result = Validators::date('2.10.2016');
        $this->assertEquals(1, count($result['error']), 'Неполная дата');

        $result = Validators::date('10.22.2016');
        $this->assertEquals(1, count($result['error']), 'Нереальная дата');
    }
}
