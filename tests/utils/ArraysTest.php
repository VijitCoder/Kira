<?php
use PHPUnit\Framework\TestCase;
use kira\utils\Arrays;

/**
 * Тестируем утилиту по работе с массивами
 */
class ArraysTest extends TestCase
{
    public function test_arrObject(): void
    {
        $data = [
            [
                'prop1' => 234,
                'prop2' => 'val',
            ],
            [14, 46, 63],
        ];

        $result = Arrays::arrObject($data);

        $this->assertCount(2, $result, 'Корневой массив не сохранил количество элементов');
        $this->assertInstanceOf(ArrayObject::class, $result[0], 'Ассоциативный массив не превращен в объект');
        $this->assertEquals($data[1], $result[1], 'Неассоциативный массив изменился');
        $this->assertObjectHasAttribute('prop1', $result[0], 'Не найдено свойство "prop1"');
        $this->assertEquals($result[0]->prop1, 234, 'prop1 не равно ожидаемому значению');
    }

    /**
     * Рекурсивная фильтрация пустых значений в массиве
     */
    public function test_filter_empty(): void
    {
        $data = [
            [11, 0, 13],
            [15, 16, null],
            [],
            ['stay', '', 'stay1', []],
        ];

        $expected = [
            [11, 2 => 13,],
            [15, 16],
            3 => ['stay', 2 => 'stay1'],
        ];

        $actual = Arrays::filterRecursive($data);

        $this->assertEquals($expected, $actual, 'Не верная рекурсивная фильтрация пустых значений в массиве');
    }

    /**
     * Рекурсивная фильтрация значений массива через callback-функцию.
     *
     * Функция проверяет значение на четность. Останутся только нечетные значения.
     */
    public function test_filter_value(): void
    {
        $data = [
            [11, 12, 13, 14],
            [15, 16, 17, 18],
            [2, 4, 6], // этот массив должен полностью исчезнуть
            [19, 20,],
        ];

        $expected = [
            [11, 2 => 13],
            [15, 2 => 17],
            3 => [19],
        ];

        $actual = Arrays::filterRecursive(
            $data,
            static function ($val) {
                return $val & 1;
            }
        );

        $this->assertEquals(
            $expected,
            $actual,
            'Не верная рекурсивная фильтрация значений массива через callback-функцию'
        );
    }

    /**
     * Рекурсивная фильтрация ключей массива через callback-функцию.
     *
     * Функция проверяет значение на четность. Останутся только нечетные элементы.
     */
    public function test_filter_key(): void
    {
        $data = [
            [11, 12, 13, 14],
            [15, 16, 17, 18],
            [19, 20,],
        ];

        $expected = [
            1 => [1 => 16, 3 => 18],
        ];

        $actual = Arrays::filterRecursive(
            $data,
            static function ($val) {
                return $val & 1;
            },
            ARRAY_FILTER_USE_KEY
        );

        $this->assertEquals(
            $expected,
            $actual,
            'Не верная рекурсивная фильтрация ключей массива через callback-функцию'
        );
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
    public function test_filter_both(): void
    {
        $data = [
            [11, 12, 13, 14],
            [15, 16, 17, 18],
            [2, 4, 6],
            [19, 20,],
        ];

        $expected = [
            1 => [
                0 => 15,
                2 => 17,
            ],
            3 => [19],
        ];

        $actual = Arrays::filterRecursive(
            $data,
            static function ($val, $key) {
                return is_array($val) ? $key & 1 : $val & 1;
            },
            ARRAY_FILTER_USE_BOTH
        );

        $this->assertEquals(
            $expected,
            $actual,
            'Не верная рекурсивная фильтрация ключей и значений массива через callback-функцию'
        );
    }

    public function test_merge(): void
    {
        $arr1 = [
            'key1' => 'will be rewrite to array',
            'key2' => ['will be', 'rewrite to', 'k2' => 'string',],
            'key3' => ['k3' => 'sub', 'k4' => 'array', 1, 2,],
            'nums' => [1 => 14, 3 => 32, 5 => 53,],
            'key4' => 'unique value',
        ];

        $arr2 = [
            'key1' => ['str', 'rewrite with', 'new value'],
            'key2' => 'array rewrite to a new string',
            'key3' => ['k3' => 'new sub', 'k5' => 'here', 3, 4],
            'nums' => [0 => 4, 2 => 27, 4 => 46],
        ];

        $expected = [
            'key1' => ['str', 'rewrite with', 'new value',],
            'key2' => 'array rewrite to a new string',
            'key3' => ['k3' => 'new sub', 'k4' => 'array', 0 => 1, 1 => 2, 'k5' => 'here', 2 => 3, 3 => 4,],
            'nums' => [
                1 => 14, // Эти ключи сохранятся просто потому, что первый массив копируется в результат.
                3 => 32,
                5 => 53,
                6 => 4,  // От этого ключа все изменится, т.к. числовые ключи не сохраняются и нумерация
                7 => 27, // определяется внутренним механизмом PHP.
                8 => 46,
            ],
            'key4' => 'unique value',
        ];
        $actual = Arrays::mergeRecursive($arr1, $arr2, false);

        $this->assertEquals($expected, $actual, 'Рекурсивное объединение массивов. Числовые ключи не сбросились.');

        $expected = [
            'key1' => ['str', 'rewrite with', 'new value',],
            'key2' => 'array rewrite to a new string',
            // Числовые ключи рассматривались как текстовые. Два элемента переписаны, т.к. было совпадение ключей.
            'key3' => ['k3' => 'new sub', 'k4' => 'array', 0 => 3, 1 => 4, 'k5' => 'here'],
            'nums' => [
                1 => 14, // Все ключи сохранились, т.к. рассматривались как текстовые
                3 => 32,
                5 => 53,
                0 => 4,
                2 => 27,
                4 => 46,
            ],
            'key4' => 'unique value',
        ];

        $actual = Arrays::mergeRecursive($arr1, $arr2, true);

        $this->assertEquals($expected, $actual, 'Рекурсивное объединение массивов. Не сохранились числовые ключи.');
    }

    public function test_implode(): void
    {
        $arr = [
            'string 1',
            ['string 2', 'string 3',],
            ['sub' => ['string 4', 'string 5',]],
        ];

        $expected = 'string 1 rn  + string 2 + string 3 rn  + rn  + string 4 + string 5';
        $actual = Arrays::implodeRecursive($arr, ' + ', ' rn ');
        $this->assertEquals($expected, $actual, 'Ошибка слияния многомерного массива в строку');
    }

    public function test_getValue(): void
    {
        $arr = ['path' => ['app' => ['level1' => '/home', 'level2' => '/www',],],];
        $expect = Arrays::getValue($arr, ['path' => ['app' => 'level2']]);
        $this->assertEquals($expect, '/www', 'Получено неверное значение массива по заданной цепочке ключей');
    }

    /**
     * Извлечение элемента массива по заданному ключу
     */
    public function test_extractValue(): void
    {
        $data = [
            'one' => 'раз',
            'two' => 'два',
            'three' => 'три',
        ];

        $value = Arrays::extractValue($data, 'two');
        $this->assertEquals('два', $value, 'Извлечено неверное значение массива');
        $this->assertEquals(
            [
                'one' => 'раз',
                'three' => 'три',
            ],
            $data,
            'Массив без извлеченного значения не соответствует ожиданиям'
        );

        $notExistValue = Arrays::extractValue($data, 'four');
        $this->assertNull($notExistValue, 'Успешная попытка добыть несуществующий элемент');
    }

    /**
     * Тест: рекурсивно сбросить все значения массива.
     *
     * @dataProvider resetValuesData
     * @param array $target
     * @param mixed $replacement
     * @param array $expected
     */
    public function test_resetValues(array $target, $replacement, array $expected): void
    {
        $actual = Arrays::resetValues($target, $replacement);
        $this->assertEquals($expected, $actual, 'Массив обнулился неверно');
    }

    /**
     * Данные для теста: рекурсивно сбросить все значения массива.
     *
     * @return array
     */
    public function resetValuesData(): array
    {
        $target = [
            'k1' => 'text',
            'sub' => [
                'sk1' => 'test',
                'sk2' => 'more',
                'three',
                4,
            ],
        ];

        return [
            'многомерный, NULL' => [
                'target' => $target,
                'replacement' => null,
                'expected' => [
                    'k1' => null,
                    'sub' => [
                        'sk1' => null,
                        'sk2' => null,
                        null,
                        null,
                    ],
                ],
            ],

            'многомерный, подстановка' => [
                'target' => $target,
                'replacement' => 'bang',
                'expected' => [
                    'k1' => 'bang',
                    'sub' => [
                        'sk1' => 'bang',
                        'sk2' => 'bang',
                        'bang',
                        'bang',
                    ],
                ],
            ],

            'одномерный, нули' => [
                'target' => $target['sub'],
                'replacement' => 0,
                'expected' => [
                    'sk1' => 0,
                    'sk2' => 0,
                    0,
                    0,
                ],
            ],
        ];
    }

    /**
     * Построение иерархического дерева из одномерного массива без использования callback-функции
     */
    public function test_buildTree(): void
    {
        $source = [
            'lvl0' => [
                'pos' => 'корень дерева. Уровень 0',
            ],
            'lvl1.1' => [
                'parentId' => 'lvl0',
                'pos' => 'уровень 1 ветка 1',
            ],
            'lvl1.2' => [
                'parentId' => 'lvl0',
                'pos' => 'уровень 1 ветка 2',
            ],
            'lvl2.1' => [
                'parentId' => 'lvl1.2',
                'pos' => 'уровень 2 ветка 1',
            ],
            'lvl3.1' => [
                'parentId' => 'lvl2.1',
                'pos' => 'уровень 3 ветка 1',
            ],
            'lvl2.2' => [
                'parentId' => 'lvl1.2',
                'pos' => 'уровень 2 ветка 2',
            ],
        ];

        $expected = [
            'lvl0' => [
                'pos' => 'корень дерева. Уровень 0',
                'children' => [
                    'lvl1.1' => [
                        'parentId' => 'lvl0',
                        'pos' => 'уровень 1 ветка 1',
                    ],
                    'lvl1.2' => [
                        'parentId' => 'lvl0',
                        'pos' => 'уровень 1 ветка 2',
                        'children' => [
                            'lvl2.1' => [
                                'parentId' => 'lvl1.2',
                                'pos' => 'уровень 2 ветка 1',
                                'children' => [
                                    'lvl3.1' => [
                                        'parentId' => 'lvl2.1',
                                        'pos' => 'уровень 3 ветка 1',
                                    ],
                                ],
                            ],
                            'lvl2.2' => [
                                'parentId' => 'lvl1.2',
                                'pos' => 'уровень 2 ветка 2',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $tree = Arrays::buildTree($source);

        $this->assertEquals($expected, $tree, 'Ошибка построения иерахического массива');
    }

    /**
     * Построение иерархического дерева из одномерного массива c использованием callback-функции
     */
    public function test_buildTree_callback(): void
    {
        $data = [
            'lvl0' => [
                'pos' => 'корень дерева. Уровень 0',
            ],
            'lvl1.1' => [
                'bindTo' => 'lvl0',
                'pos' => 'уровень 1 ветка 1',
            ],
            'lvl1.2' => [
                'bindTo' => 'lvl0',
                'pos' => 'уровень 1 ветка 2',
            ],
            'lvl2.1' => [
                'bindTo' => 'lvl1.2',
                'pos' => 'уровень 2 ветка 1',
            ],
        ];

        $getParentId = static function ($key, &$item) {
            $parentId = $item['bindTo'] ?? null;
            unset($item['bindTo']);
            return $parentId;
        };

        $expected = [
            'lvl0' => [
                'pos' => 'корень дерева. Уровень 0',
                'children' => [
                    'lvl1.1' => [
                        'pos' => 'уровень 1 ветка 1',
                    ],
                    'lvl1.2' => [
                        'pos' => 'уровень 1 ветка 2',
                        'children' => [
                            'lvl2.1' => [
                                'pos' => 'уровень 2 ветка 1',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $tree = Arrays::buildTree($data, $getParentId);

        $this->assertEquals($expected, $tree, 'Иерахический массив через callback-функцию получен неправильно');
    }

    /**
     * Тест: рекурсивная проверка массива на ассоциативность
     *
     * @dataProvider isAssociativeData
     * @param array $array
     * @param bool $isAssoc
     */
    public function test_isAssociative(array $array, bool $isAssoc): void
    {
        $this->assertEquals($isAssoc, Arrays::isAssociative($array));
    }

    public function isAssociativeData(): array
    {
        return [
            'ассоциативный массив' => [
                'array' => [5, 7, '10 ten' => 3],
                'is assoc?' => true,
            ],
            'ассоциативный многомерный массив' => [
                'array' => [5, 7, ['10 ten' => 3], 45],
                'is assoc?' => true,
            ],
            'пустой массив' => [
                'array' => [],
                'is assoc?' => false,
            ],
            'числовой массив' => [
                'array' => [2, true => 5, '12' => 7, '10.3' => 3],
                'is assoc?' => false,
            ],
            'числовой многомерный массив' => [
                'array' => [2, [5, 20], 7, ['10.3' => 3]],
                'is assoc?' => false,
            ],
        ];
    }

    /**
     * Тест: стабильная сортировка массива через callback-функцию
     *
     * Суть: если элементов больше 16-ти, сортировка с сохранением ключей перемешает элементы с равными значениями.
     * Обычно это некритично, но если в массиве был задан дефолтный порядок и его нужно поддерживать, то нативная PHP
     * сортировка поломает все нафиг. Тестируемый метод ничего не ломает.
     *
     * Есть вероятность, что в будущих версиях PHP изменят логику *sort-функций и этот тест будет падать. Для PHP 7.1
     * работает.
     */
    public function test_stableSort(): void
    {
        /**
         * Сортировка по возрастанию значений массива
         *
         * @param $a
         * @param $b
         * @return int
         */
        function compare($a, $b)
        {
            if ($a === $b) {
                return 0;
            }
            return ($a > $b) ? 1 : -1;
        }

        $a = $b = [
            'key 1' => 2,
            'key 2' => 1,
            'key 3' => 2,
            'key 4' => 1,
            'key 5' => 1,
            'key 6' => 1,
            'key 7' => 1,
            'key 8' => 1,
            'key 9' => 1,
            'key 10' => 1,
            'key 11' => 1,
            'key 12' => 1,
            'key 13' => 1,
            'key 14' => 1,
            'key 15' => 1,
            'key 16' => 1,
            'key 17' => 1,
        ];

        uasort($a, 'compare');
        $phpSortKeys = array_keys($a);

        Arrays::stableSort($b, 'compare');
        $stableSortKeys = array_keys($b);

        $this->assertNotEquals(
            $phpSortKeys,
            $stableSortKeys,
            'Нативная PHP сортировка не перемешала массив. Видимо исправили'
        );

        $expectedKeys = [
            'key 2',
            'key 4',
            'key 5',
            'key 6',
            'key 7',
            'key 8',
            'key 9',
            'key 10',
            'key 11',
            'key 12',
            'key 13',
            'key 14',
            'key 15',
            'key 16',
            'key 17',
            'key 1',
            'key 3',
        ];

        $this->assertEquals($stableSortKeys, $expectedKeys, 'Стабильная сортировка перемешала массив');
    }
}
