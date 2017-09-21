<?php
use PHPUnit\Framework\TestCase;
use kira\utils\Arrays;

/**
 * Тестируем утилиту по работе с массивами
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

        $this->assertCount(2, $result, 'Корневой массив не сохранил количество элементов');
        $this->assertInstanceOf(\ArrayObject::class, $result[0], 'Ассоциативный массив не превращен в объект');
        $this->assertArraySubset([1 => [14, 46, 63]], $result, 'Неассоциативный массив изменился');
        $this->assertObjectHasAttribute('prop1', $result[0], 'Не найдено свойство "prop1"');
        $this->assertEquals($result[0]->prop1, 234, 'prop1 не равно ожидаемому значению');
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
        $this->assertEquals($expect, $arr, 'Не верная рекурсивная фильтрация пустых значений в массиве');
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
            function($val) {
                return $val & 1;
            }
        );

        $expect = [
            [11, 2 => 13],
            [15, 2 => 17],
            3 => [19],
        ];

        $this->assertEquals($expect, $arr, 'Не верная рекурсивная фильтрация значений массива через callback-функцию');
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
            function($val) {
                return $val & 1;
            },
            ARRAY_FILTER_USE_KEY
        );

        $expect = [
            1 => [1 => 16, 3 => 18],
        ];

        $this->assertEquals($expect, $arr, 'Не верная рекурсивная фильтрация ключей массива через callback-функцию');
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
            function($val, $key) {
                return is_array($val) ? $key & 1 : $val & 1;
            },
            ARRAY_FILTER_USE_BOTH
        );

        $expect = [
            1 => [
                0 => 15,
                2 => 17,
            ],
            3 => [19],
        ];

        $this->assertEquals($expect, $arr,
            'Не верная рекурсивная фильтрация ключей и значений массива через callback-функцию');
    }

    public function test_merge_recursive()
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

        $arr = Arrays::merge_recursive($arr1, $arr2, false);
        $expect = [
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

        $this->assertEquals($expect, $arr, 'Рекурсивное объединение массивов. Числовые ключи не сбросились.');

        $arr = Arrays::merge_recursive($arr1, $arr2, true);
        $expect = [
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
        $this->assertEquals($expect, $arr, 'Рекурсивное объединение массивов. Не сохранились числовые ключи.');
    }

    public function test_implode_recursive()
    {
        $arr = [
            'string 1',
            ['string 2', 'string 3',],
            ['sub' => ['string 4', 'string 5',]],
        ];

        $expect = Arrays::implode_recursive($arr, ' + ', ' rn ');
        $this->assertEquals($expect, 'string 1 rn  + string 2 + string 3 rn  + rn  + string 4 + string 5',
            'Ошибка слияния многомерного массива в строку');
    }

    public function test_getValue()
    {
        $arr = ['path' => ['app' => ['level1' => '/home', 'level2' => '/www',],],];
        $expect = Arrays::getValue($arr, ['path' => ['app' => 'level2']]);
        $this->assertEquals($expect, '/www', 'Получено неверное значение массива по заданной цепочке ключей');
    }

    /**
     * Построение иерархического дерева из одномерного массива без использования callback-функции
     */
    public function test_buildTree()
    {
        $source = [
            'lvl0'   => [
                'pos' => 'корень дерева. Уровень 0',
            ],
            'lvl1.1' => [
                'parentId' => 'lvl0',
                'pos'      => 'уровень 1 ветка 1',
            ],
            'lvl1.2' => [
                'parentId' => 'lvl0',
                'pos'      => 'уровень 1 ветка 2',
            ],
            'lvl2.1' => [
                'parentId' => 'lvl1.2',
                'pos'      => 'уровень 2 ветка 1',
            ],
            'lvl3.1' => [
                'parentId' => 'lvl2.1',
                'pos'      => 'уровень 3 ветка 1',
            ],
            'lvl2.2' => [
                'parentId' => 'lvl1.2',
                'pos'      => 'уровень 2 ветка 2',
            ],
        ];

        $expect = [
            'lvl0' => [
                'pos'      => 'корень дерева. Уровень 0',
                'children' => [
                    'lvl1.1' => [
                        'parentId' => 'lvl0',
                        'pos'      => 'уровень 1 ветка 1',
                    ],
                    'lvl1.2' => [
                        'parentId' => 'lvl0',
                        'pos'      => 'уровень 1 ветка 2',
                        'children' => [
                            'lvl2.1' => [
                                'parentId' => 'lvl1.2',
                                'pos'      => 'уровень 2 ветка 1',
                                'children' => [
                                    'lvl3.1' => [
                                        'parentId' => 'lvl2.1',
                                        'pos'      => 'уровень 3 ветка 1',
                                    ],
                                ],
                            ],
                            'lvl2.2' => [
                                'parentId' => 'lvl1.2',
                                'pos'      => 'уровень 2 ветка 2',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $tree = Arrays::buildTree($source);

        $this->assertEquals($expect, $tree, 'Ошибка построения иерахического массива');
    }

    /**
     * Построение иерархического дерева из одномерного массива c использованием callback-функции
     */
    public function test_buildTree_callback()
    {
        $array = [
            'lvl0'   => [
                'pos' => 'корень дерева. Уровень 0',
            ],
            'lvl1.1' => [
                'bindTo' => 'lvl0',
                'pos'    => 'уровень 1 ветка 1',
            ],
            'lvl1.2' => [
                'bindTo' => 'lvl0',
                'pos'    => 'уровень 1 ветка 2',
            ],
            'lvl2.1' => [
                'bindTo' => 'lvl1.2',
                'pos'    => 'уровень 2 ветка 1',
            ],
        ];

        $getParentId = function($key, &$item) {
            $parentId = $item['bindTo'] ?? null;
            unset($item['bindTo']);
            return $parentId;
        };

        $expect = [
            'lvl0' => [
                'pos'      => 'корень дерева. Уровень 0',
                'children' => [
                    'lvl1.1' => [
                        'pos' => 'уровень 1 ветка 1',
                    ],
                    'lvl1.2' => [
                        'pos'      => 'уровень 1 ветка 2',
                        'children' => [
                            'lvl2.1' => [
                                'pos' => 'уровень 2 ветка 1',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $tree = Arrays::buildTree($array, $getParentId);

        $this->assertEquals($expect, $tree, 'Иерахический массив через callback-функцию получен неправильно');
    }

    /**
     * Извлечение элемента массива по заданному ключу
     */
    public function test_value_extract()
    {
        $array = [
            'one'   => 'раз',
            'two'   => 'два',
            'three' => 'три',
        ];

        $value = Arrays::value_extract($array, 'two');
        $this->assertEquals('два', $value, 'Извлечено неверное значение массива');
        $this->assertEquals(
            [
                'one'   => 'раз',
                'three' => 'три',
            ],
            $array,
            'Массив без извлеченного значения не соответствует ожиданиям'
        );

        $notExistValue =  Arrays::value_extract($array, 'four');
        $this->assertNull($notExistValue, 'Успешная попытка добыть несуществующий элемент');
    }
}
