<?php
namespace kira\utils;

/**
 * Свои манипуляции с массивами. В PHP их много, но иногда чего-то не хватает :)
 *
 * В PHP есть SPL с итераторами {@link http://php.net/manual/ru/spl.iterators.php}. Но как показывает практика например
 * тут {@link http://php.net/manual/ru/class.recursiveiteratoriterator.php#112713}, рекурсивные функции быстрее. Хотя
 * конечно для всех итераторов я тесты не видел.
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
     * @return ArrayObject|mixed
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
     * Фильтрация многомерного массива
     *
     * Функция по типу array_filter() {@link http://php.net/manual/ru/function.array-filter.php}, только работает
     * с многомерными массивами.
     *
     * Если не задана callback-функция, из массива убираются элементы, приравниваемые к FALSE (как в мануале), в том
     * числе пустые массивы.
     *
     * Если в фильтрация подмассива вернет пустой массив, он не попадет в результат.
     *
     * Флаг ARRAY_FILTER_USE_BOTH для подмассива неоднозначен. Поэтому в callback-функцию будут переданы сначала ключ
     * и его подмассив. Если функция одобрит, то дальше будет передано содержимое подмассива с ключами.
     *
     * @param array    $array    исходный массив
     * @param callable $callback функция для фильтрации
     * @param int      $flag     фильтровать по значениям или по ключам. см. ARRAY_FILTER_USE_* в справке PHP
     * @return array
     * @throws \LogicException
     */
    public static function array_filter_recursive(array $array, callable $callback = null, $flag = 0)
    {
        $result = [];
        $viaFunc = (bool)$callback;

        if (!$viaFunc && in_array($flag, [ARRAY_FILTER_USE_KEY, ARRAY_FILTER_USE_BOTH])) {
            throw new \LogicException(
                'Не задана callback-функция. Фильтрация по ключам или ключ + значение бессмысленна');
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
                if ($v = self::array_filter_recursive($v, $callback, $flag)) {
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
     * Рекурсивное объединение <b>двух</b> массивов.
     *
     * По мотивам {@link http://php.net/manual/ru/function.array-merge-recursive.php#92195}
     *
     * Это объединение массивов, как описано в справке, а не как на самом деле работает php::array_merge_recursive().
     *
     * Прим: в комментарии другое объединение, там числовые ключи заменяются так же, как и строковые.
     *
     * Логика объединения:
     * - элементы со строковыми ключами перезаписываются. Если у обоих массивов элементы окажутся подмассивами, тогда
     * они объединяются в рекурсивном вызове этой функции.
     * - элементы с числовыми ключами всегда добавляются в конец, независимо от самих значений. Важно понимать, что
     * при этом числовые ключи не сохраняются, т.е. происходит переиндексация массива. Добавлен опциональный флаг,
     * который позволит обработать числовые ключи, как строковые. Т.е. заменять значения числовых ключей,
     * а не дописывать в конец результирующего массива.
     *
     * Схематично (для строковых ключей):
     * <pre>
     *  value1 + array2 = array2
     *  array1 + value2 = value2
     *  array1 + array2 > recurse call
     * </pre>
     *
     * @param array $array1
     * @param array $array2
     * @param bool  $numKeyAsString TRUE = обрабатывать числовые ключи аналогично строковым, FALSE = дописывать значения
     *                              в конец, с новыми числовыми ключами.
     * @return array
     */
    public static function merge_recursive(array &$array1, array &$array2, bool $numKeyAsString = false)
    {
        $merged = $array1;

        foreach ($array2 as $key => &$value) {
            if (is_int($key) && !$numKeyAsString) {
                $merged[] = $value;
            } else if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = self::merge_recursive($merged[$key], $value, $numKeyAsString);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Собираем значения массива в строку. Рекурсия.
     *
     * Это похоже на использование php::array_reduce(), только запилено под строковый результат. Перед каждым
     * подмассивом пишется значение из $eol. Так можно получить многострочный текст, отражающий многомерный массив.
     *
     * @param array  $arr  массив для склейки в строку
     * @param string $glue клей между соседними элементами одного массива
     * @param string $eol  клей между соседними подмассивами
     * @return string
     */
    public static function implode_recursive(array $arr, string $glue = ' ', string $eol = '')
    {
        if (!is_array($arr)) {
            trigger_error('Неверный первый параметр ($arr). Нужен массив', E_USER_ERROR);
        }

        if (!is_string($glue)) {
            trigger_error('Неверный второй параметр ($glue). Нужна строка', E_USER_ERROR);
        }

        if (!is_string($eol)) {
            trigger_error('Неверный третий параметр ($eol). Нужна строка', E_USER_ERROR);
        }

        $result = '';
        foreach ($arr as $v) {
            if (is_null($v)) {
                continue;
            }
            if (is_array($v)) {
                $result .= $eol;
                $v = self::implode_recursive($v, $glue, $eol);
            }
            $result .= $glue . $v;
        }

        return ltrim($result, $glue);
    }

    /**
     * Получение значения массива по заданной цепочке ключей.
     *
     * Допустим, есть некий многомерный массив:
     * <pre>
     * $data = [
     *    'path' => ['app' => ['level1' => '/home', 'level2' => '/www',],],
     *    ...
     * ];
     * </pre>
     *
     * Нужно получить значение из 'level2'. В обычной ситуации это будет:
     * <pre>
     * $v = $data['path']['app']['level2'];
     * </pre>
     *
     * Но когда мы не можем напрямую обратиться к массиву, зато знаем цепочку ключей, используем эту функцию:
     * <pre>
     * $v = Arrays::getValue($data, ['path' => ['app' => 'level2']]);
     * </pre>
     *
     * @param array $arr
     * @param mixed $chain массив ключей или строковый/числовой ключ
     * @return mixed
     */
    public static function getValue(array &$arr, $chain)
    {
        if (is_array($chain)) {
            $key = key($chain);
            return isset($arr[$key]) ? self::getValue($arr[$key], $chain[$key]) : null;
        }
        return isset($arr[$chain]) ? $arr[$chain] : null;
    }
}
