<?php
use PHPUnit\Framework\TestCase;
use kira\utils\Arrays;
use kira\exceptions\WriteException;
use kira\exceptions\ReadException;

/**
 * Тестируем утилиту Arrays
 */
class ArraysTest extends TestCase
{
    public function test_arrObject()
    {
        $arr = [
            [
                'prop1' => 234,
                'prop2' => 'val',
            ],
            [14, 46, 63],
        ];

        $result = Arrays::arrObject($arr);

        $this->assertCount(2, $result, 'Корневой массив сохранил количество элементов');
        $this->assertInstanceOf(\ArrayObject::class, $result[0], 'Ассоциативный массив превращен в объект');
        $this->assertArraySubset([1 => [14, 46, 63]], $result, 'Неассоциативный массив не изменился');
        $this->assertObjectHasAttribute('prop1', $result[0], 'Найдено свойство "prop1"');
        $this->assertEquals($result[0]->prop1, 234, 'prop1 равно ожидаемому значению');
    }


    /**
     * Рекурсивная фильтрация пустых значений в массиве
     */
    public function test_array_filter_recursive_empty()
    {
        $arr = [
            [11, 0, 13],
            [15, 16, null],
            [],
            ['stay', '', 'stay1', []],
        ];

        $arr = Arrays::array_filter_recursive($arr);

        $expect = [
            [11, 2 => 13,],
            [15, 16],
            3 => ['stay', 2 => 'stay1'],
        ];
        $this->assertEquals($expect, $arr, 'Рекурсивная фильтрация пустых значений в массиве');
    }

    /**
     * Рекурсивная фильтрация значений массива через callback-функцию.
     *
     * Функция проверяет значение на четность. Останутся только нечетные значения.
     */
    public function test_array_filter_recursive_value()
    {
        $arr = [
            [11, 12, 13, 14],
            [15, 16, 17, 18],
            [2, 4, 6], // этот массив должен полностью исчезнуть
            [19, 20,],
        ];

        $arr = Arrays::array_filter_recursive(
            $arr,
            function ($val) {
                return $val & 1;
            }
        );

        $expect = [
            [11, 2 => 13],
            [15, 2 => 17],
            3 => [19],
        ];

        $this->assertEquals($expect, $arr, 'Рекурсивная фильтрация значений массива через callback-функцию');
    }

    /**
     * Рекурсивная фильтрация ключей массива через callback-функцию.
     *
     * Функция проверяет значение на четность. Останутся только нечетные элементы.
     */
    public function test_array_filter_recursive_key()
    {
        $arr = [
            [11, 12, 13, 14],
            [15, 16, 17, 18],
            [19, 20,],
        ];

        $arr = Arrays::array_filter_recursive(
            $arr,
            function ($val) {
                return $val & 1;
            },
            ARRAY_FILTER_USE_KEY
        );

        $expect = [
            1 => [1 => 16, 3 => 18],
        ];

        $this->assertEquals($expect, $arr, 'Рекурсивная фильтрация ключей массива через callback-функцию');
    }

    /**
     * Рекурсивная фильтрация ключей и значений массива через callback-функцию.
     *
     * Функция проверяет значение на четность. Останутся только нечетные элементы.
     *
     * Поскольку Флаг ARRAY_FILTER_USE_BOTH для подмассива неоднозначен, определим такое поведение callback-функции:
     * если полученное значение - массив, тогда проверим на четность ключ, к которому этот подмассив назначен. Иначе
     * проверяем на четность элемент. Вообще мне трудно придумать какую-то вразумительную ситуацию, когда могла бы
     * потребоваться фильтрация многомерного массива и по ключам и по значениям.
     */
    public function test_array_filter_recursive_both()
    {
        $arr = [
            [11, 12, 13, 14],
            [15, 16, 17, 18],
            [2, 4, 6],
            [19, 20,],
        ];

        $arr = Arrays::array_filter_recursive(
            $arr,
            function ($val, $key) {
                return is_array($val) ? $key & 1 : $val & 1;
            },
            ARRAY_FILTER_USE_BOTH
        );

        $expect = [
            1 => [
                0 => 15,
                2 => 17,
            ],
            3 => [19]];

        $this->assertEquals($expect, $arr, 'Рекурсивная фильтрация ключей и значений массива через callback-функцию');
    }
}