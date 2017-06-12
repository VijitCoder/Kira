<?php
use PHPUnit\Framework\TestCase;
use kira\utils\Typecast;

/**
 * Тестируем утилиту приведения типов данных
 */
class TypecastTest extends TestCase
{
    /**
     * Тест: приведение скалярного значения к строке
     * @dataProvider stringDataProvider
     * @param string|string $value
     * @param string|null   $expect
     */
    public function test_string($value, $expect)
    {
        $result = Typecast::string($value);
        $this->assertEquals($expect, $result);
    }

    /**
     * Данные: приведение скалярного значения к строке
     * @return array
     */
    public function stringDataProvider()
    {
        return [
            ['some', 'some'],
            [true, '1'],
            [234, '234'],
            [-235.5, '-235.5'],
            [[2, 4, 5], null],
            [new StdClass, null],
        ];
    }

    /**
     * Тест: приведение скалярного значения к булевому значению
     * @dataProvider boolDataProvider
     * @param bool|string $value
     * @param bool|null   $expect
     */
    public function test_bool($value, $expect)
    {
        $result = Typecast::bool($value);
        $this->assertEquals($expect, $result);
    }

    /**
     * Данные: приведение скалярного значения к булевому значению
     * @return array
     */
    public function boolDataProvider()
    {
        return [
            [true, true],
            [1, true],
            ['0', false],
            ['да', true],
            ['no', false],
            ['3word', null],
            [[2, 4, 5], null],
        ];
    }

    /**
     * Тест: приведение скалярного значения к целому числу
     * @dataProvider intDataProvider
     * @param int|string $value
     * @param int|null   $expect
     */
    public function test_int($value, $expect)
    {
        $result = Typecast::int($value);
        $this->assertEquals($expect, $result);
    }

    /**
     * Данные: приведение скалярного значения к целому числу
     * @return array
     */
    public function intDataProvider()
    {
        return [
            [-56, -56],
            [004, 4],
            ['0028', 28],
            [0.5, null],
            ['3word', null],
            [[2, 4, 5], null],
        ];
    }

    /**
     * Тест: приведение скалярного значения к рациональному числу
     * @dataProvider floatDataProvider
     * @param float|string $value
     * @param float|null   $expect
     */
    public function test_float($value, $expect)
    {
        $result = Typecast::float($value);
        $this->assertEquals($expect, $result);
    }

    /**
     * Данные: приведение скалярного значения к рациональному числу
     * @return array
     */
    public function floatDataProvider()
    {
        return [
            [-56.4, -56.4],
            [004, 4],
            ['0028.2', 28.2],
            [0.5, 0.5],
            ['-0.71', -0.71],
            ['21.', null],
            ['-.09', null],
            ['2.3word', null],
        ];
    }
}
