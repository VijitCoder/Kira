<?php
namespace kira\utils;

use kira\exceptions\EngineException;

/**
 * Свои манипуляции с массивами. В PHP их много, но иногда чего-то не хватает :)
 *
 * См. документацию, "Утилиты"
 */
class Arrays
{
    /**
     * Оборачиваем массив любой вложенности в класс ArrayObject для доступа к элементам, как к свойствам класса.
     *
     * Рекурсия
     *
     * Одно ограничение: не принимаются массивы со смешанными ключами. Каждый вложенный массив либо полностью
     * ассоциативный, либо полностью неассоциативный. Числовые ключи, обернутые в строки, распознаются и массив
     * приравнивается к неассоциативному.
     *
     * @param array $data массив любого типа и вложенности
     * @return \ArrayObject|mixed
     */
    public static function arrObject($data)
    {
        if (is_array($data)) {
            $data = array_map([__CLASS__, 'arrObject'], $data);
            if (!is_numeric(key($data)) && count($data)) {
                $data = new \ArrayObject($data, \ArrayObject::ARRAY_AS_PROPS);
            }
        }

        return $data;
    }

    /**
     * Фильтрация многомерного массива. Рекурсия.
     *
     * Функция по типу array_filter() {@link http://php.net/manual/ru/function.array-filter.php}, только работает
     * с многомерными массивами.
     *
     * Если не задана callback-функция, из массива убираются элементы, приравниваемые к FALSE (как в мануале), в том
     * числе пустые массивы.
     *
     * Если фильтрация подмассива вернет пустой массив, он не попадет в результат.
     *
     * Флаг ARRAY_FILTER_USE_BOTH для подмассива неоднозначен. Поэтому в callback-функцию будут переданы сначала ключ
     * и его подмассив. Если функция одобрит, то дальше будет передано содержимое подмассива с ключами.
     *
     * @param array    $array    исходный массив
     * @param callable $callback функция для фильтрации
     * @param int      $flag     фильтровать по значениям или по ключам. см. ARRAY_FILTER_USE_* в справке PHP
     * @return array
     * @throws EngineException
     */
    public static function filterRecursive(array $array, callable $callback = null, $flag = 0): array
    {
        $result = [];
        $viaFunc = (bool)$callback;

        if (!$viaFunc && in_array($flag, [ARRAY_FILTER_USE_KEY, ARRAY_FILTER_USE_BOTH])) {
            throw new EngineException(
                'Не задана callback-функция. Фильтрация по ключам или "ключ + значение" бессмысленна',
                EngineException::LOGIC_ERROR
            );
        }

        foreach ($array as $k => $v) {
            if ($flag == ARRAY_FILTER_USE_KEY && !$callback($k)) {
                continue;
            }

            if ($flag == ARRAY_FILTER_USE_BOTH && !$callback($v, $k)) {
                continue;
            }

            if (!$viaFunc && !$v) {
                continue;
            }

            if (is_array($v)) {
                if ($v = self::filterRecursive($v, $callback, $flag)) {
                    $result[$k] = $v;
                }
                continue;
            }

            if ($flag == 0) { // фильтр только по значениям
                if ($viaFunc && $callback($v)) {
                    $result[$k] = $v;
                    continue;
                }

                if (!$viaFunc && $v) {
                    $result[$k] = $v;
                }
            } else {
                $result[$k] = $v;
            }
        }
        return $result;
    }

    /**
     * Объединение <b>двух</b> многомерных массивов. Рекурсия.
     *
     * По мотивам {@link http://php.net/manual/ru/function.array-merge-recursive.php#92195}
     *
     * Логика объединения:
     * <ul>
     * <li>элементы со строковыми ключами перезаписываются. Если у обоих массивов элементы окажутся подмассивами, тогда
     * они объединяются в рекурсивном вызове этой функции.</li>
     * <li>элементы с числовыми ключами всегда добавляются в конец, независимо от самих значений. Важно понимать, что
     * при этом числовые ключи не сохраняются, т.е. происходит <b>произвольная</b> переиндексация массива.</li>
     * <li>есть опциональный флаг, который позволит обработать числовые ключи, как строковые. Т.е. заменять значения
     * числовых ключей, а не дописывать в конец результирующего массива.</li>
     * </ul>
     *
     * @param array $array1         первый массив для слияния
     * @param array $array2         второй массив для слияния
     * @param bool  $numKeyAsString TRUE - обрабатывать числовые ключи аналогично строковым, FALSE - дописывать значения
     *                              в конец, с новыми числовыми ключами.
     * @return array
     */
    public static function mergeRecursive(array $array1, array $array2, bool $numKeyAsString = false): array
    {
        $merged = $array1;

        foreach ($array2 as $key => &$value) {
            if (is_int($key) && !$numKeyAsString) {
                $merged[] = $value;
            } elseif (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = self::mergeRecursive($merged[$key], $value, $numKeyAsString);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Слияние многомерного массива в строку. Рекурсия.
     *
     * Это похоже на использование php::array_reduce(), только запилено под строковый результат. Перед каждым
     * подмассивом пишется значение из $eol. Так можно получить многострочный текст, отражающий многомерный массив.
     *
     * @param array  $arr  массив для склейки в строку
     * @param string $glue клей между соседними элементами одного массива
     * @param string $eol  клей между соседними подмассивами
     * @return string
     */
    public static function implodeRecursive(array $arr, string $glue = ' ', string $eol = ''): string
    {
        $result = '';
        foreach ($arr as $v) {
            if (is_null($v)) {
                continue;
            }
            if (is_array($v)) {
                $result .= $eol;
                $v = self::implodeRecursive($v, $glue, $eol);
            }
            $result .= $glue . $v;
        }

        return ltrim($result, $glue);
    }

    /**
     * Получение значения из массива по заданной цепочке ключей. Рекурсия.
     *
     * Пример цепочки: ['key1' => ['key2' => 'key4']]
     *
     * @param array $arr
     * @param mixed $chain массив ключей или один ключ
     * @return mixed
     */
    public static function getValue(array $arr, $chain)
    {
        if (is_array($chain)) {
            $key = key($chain);
            return isset($arr[$key]) ? self::getValue($arr[$key], $chain[$key]) : null;
        }
        return isset($arr[$chain]) ? $arr[$chain] : null;
    }

    /**
     * Получить значение из массива по его ключу. Удалить соостветствующий элемент массива.
     *
     * Поведение похоже на array_pop(), только элемент извлекается не с конца массива, а из любого места по заданному
     * ключу.
     *
     * @param array      $array массив источник
     * @param string|int $key   ключ элемента для извлечения
     * @return mixed
     */
    public static function extractValue(array &$array, $key)
    {
        if (array_key_exists($key, $array)) {
            $value = $array[$key];
            unset($array[$key]);
        } else {
            $value = null;
        }
        return $value;
    }

    /**
     * Рекурсивно сбросить все значения массива.
     *
     * Опционально можно задать значение, которое нужно установить каждому ключу массива. По умолчанию - NULL.
     *
     * В случае вложенных массивов, они не заменяются на NULL, но рассматриваются как отдельные цели для замены
     * значений внутри них.
     *
     * В результате получится многомерный массив с сохраненной иерархией ключей, но с пустыми значениями элементов.
     *
     * @param array $target целевой массив для обнуления
     * @param mixed $replacement значение подстановки в каждый элемент обнуленного массива
     * @return array
     */
    public static function resetValues(array $target, $replacement = null): array
    {
        $result = [];
        foreach ($target as $key => $value) {
            $result[$key] = is_array($value)
                ? static::resetValues($value, $replacement)
                : $replacement;
        }

        return $result;
    }


    /**
     * Построение иерархического дерева из одномерного массива
     *
     * На вход принимаем произвольный массив с данными. Если задана callback функция получения родительского id, в нее
     * будет передаваться каждый ключ и элемент этого массива, в двух параметрах. Из функции ожидаем родительский id.
     *
     * Если  callback функция не задана, то каждый элемент исходного массива должен содержать 'parentId'. Иначе такой
     * элемент размещается в корне иерархии.
     *
     * В результате получим многомерный массив, где в ключе 'children' каждой ветки перечислены ее потомки.
     *
     * @copyright  2007-2010 SARITASA LLC <info@saritasa.com>
     * @link       http://www.saritasa.com
     *
     * @param array         $nodes       исходный массив
     * @param callable|null $getParentId функция получения id родителя
     * @return array
     */
    public static function buildTree(array $nodes, callable $getParentId = null)
    {
        $tree = [];
        foreach ($nodes as $id => &$node) {
            $parentId = $getParentId ? $getParentId($id, $node) : ($node['parentId'] ?? null);
            if ($parentId === null) {
                $tree[$id] = &$node;
            } else {
                $nodes[$parentId]['children'][$id] = &$node;
            }
        }
        return $tree;
    }

    /**
     * Рекурсивная проверка массива на ассоциативность
     *
     * Числовые ключи в строках или ключи логического типа приравниваются к неассоциативному массиву. Все это согласно
     * справки {@link http://php.net/manual/ru/language.types.array.php PHP. Массивы}. Пустой массив считается
     * неассоциативным.
     *
     * Если хотя бы один ключ текстовый и не приводится к числу, то весь массив считается ассоциативным.
     *
     * Идея {@link http://thinkofdev.com/check-if-an-array-is-associative-or-sequentialindexed-in-php/ отсюда}.
     *
     * @param array $array проверяемый массив
     * @return bool
     */
    public static function isAssociative(array $array): bool
    {
        foreach ($array as $key => $value) {
            if (!is_numeric($key)) {
                return true;
            }

            if (is_array($value)) {
                return self::isAssociative($value);
            }
        }
        return false;
    }

    /**
     * Стабильная сортировка массива через callback-функцию
     *
     * Все PHP `*sort()` функции нестабильны. Они могут перемешать результ, если в массиве больше 16 элементов И если
     * некоторые из них равны. Эта проблема известна годами, но до сих пор присутствует в PHP 7.1
     *
     * Этот метод решает проблему.
     *
     * @link https://bugs.php.net/bug.php?id=53341 баг репорт
     * @link http://php.net/manual/ru/function.uasort.php#114535 обходное решение
     *
     * @param array    $array               массив для сортировки
     * @param callable $comparisionFunction callback-функция для сравнения элементов
     */
    public static function stableSort(array &$array, callable $comparisionFunction): void
    {
        if (count($array) < 2) {
            return;
        }
        $halfway = count($array) / 2;
        $array1 = array_slice($array, 0, $halfway, true);
        $array2 = array_slice($array, $halfway, null, true);

        self::stableSort($array1, $comparisionFunction);
        self::stableSort($array2, $comparisionFunction);
        if (call_user_func($comparisionFunction, end($array1), reset($array2)) < 1) {
            $array = $array1 + $array2;
            return;
        }
        $array = [];
        reset($array1);
        reset($array2);
        while (current($array1) && current($array2)) {
            if (call_user_func($comparisionFunction, current($array1), current($array2)) < 1) {
                $array[key($array1)] = current($array1);
                next($array1);
            } else {
                $array[key($array2)] = current($array2);
                next($array2);
            }
        }
        while (current($array1)) {
            $array[key($array1)] = current($array1);
            next($array1);
        }
        while (current($array2)) {
            $array[key($array2)] = current($array2);
            next($array2);
        }
        return;
    }

    /**
     * Алиас для Arrays::filterRecursive()
     *
     * @deprecated Будет удалена в v.3
     *
     * @param array         $array
     * @param callable|null $callback
     * @param int           $flag
     * @return mixed
     */
    public static function array_filter_recursive(array $array, callable $callback = null, $flag = 0): array
    {
        return self::filterRecursive($array, $callback, $flag);
    }

    /**
     * Алиас для Arrays::mergeRecursive()
     *
     * @deprecated Будет удалена в v.3
     *
     * @param array $array1
     * @param array $array2
     * @param bool  $numKeyAsString
     * @return array
     */
    public static function merge_recursive(array $array1, array $array2, bool $numKeyAsString = false): array
    {
        return self::mergeRecursive($array1, $array2, $numKeyAsString);
    }

    /**
     * Алиас для Arrays::implodeRecursive()
     *
     * @deprecated Будет удалена в v.3
     *
     * @param array  $arr
     * @param string $glue
     * @param string $eol
     * @return string
     */
    public static function implode_recursive(array $arr, string $glue = ' ', string $eol = ''): string
    {
        return self::implodeRecursive($arr, $glue, $eol);
    }

    /**
     * Алиас для Arrays::extractValue()
     *
     * @deprecated Будет удалена в v.3
     *
     * @param array $array
     * @param       $key
     * @return mixed
     */
    public static function value_extract(array &$array, $key)
    {
        return self::extractValue($array, $key);
    }
}
