<?php
/**
 * Свои манипуляции с массивами. В PHP их много, но иногда чего-то не хватает :)
 */

namespace engine\utils;

class Arrays
{
    /**
     * Оборачиваем массив любой вложенности в класс ArrayObject для доступа к элементам, как свойствам класса.
     *
     * Рекурсия
     *
     * Одно ограничение: не принимаются массивы со смешанными ключами. Каждый вложенный массив либо полностью
     * ассоциативный, либо полностью неассоциативный. Числовые ключи, обернутые в строки, распознаются и массив
     * приравнивается к неассоциативному.
     *
     * @param array $data массив любого типа и вложенности
     * @return ArrayObject
     */
    public static function arrObject($data)
    {
        if (is_array($data)) {
            $data = array_map(['DataHelper', 'arrObject'], $data);
            if (!is_numeric(key($data)) && count($data)) {
                $data = new ArrayObject($data, ArrayObject::ARRAY_AS_PROPS);
            }
        }

        return $data;
    }

    /**
     * Фильтрация массива по ключам через функцию обратного вызова.
     *
     * Функция делает тоже самое, что и array_filter() в сочетании с константой ARRAY_FILTER_USE_KEY
     * @see http://php.net/manual/ru/function.array-filter.php
     *
     * Потребовалась своя реализация для PHP < 5.6
     *
     * @param callable $callback
     * @param array    $arr
     * @return array
     */
    public static function filter_keys($callback, $arr)
    {
        foreach ($arr as $key => $whatever) {
            if (!$callback($key)) {
                unset($arr[$key]);
            }
        }
        return $arr;
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
    public static function merge_recursive(array &$array1, array &$array2, $numKeyAsString = false)
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
    public static function implode_recursive($arr, $glue = ' ', $eol = '')
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
            if (is_array($v)) {
                $result .= $eol;
                $v = implode_recursive($v, $glue, $eol);
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
     * $v = Arrays::getValue(['path' => ['app' => 'level2']]);
     * </pre>
     *
     * @param array $arr
     * @param mixed $chain массив ключей или строковый/числовой ключ
     * @return mixed
     */
    public static function getValue(&$arr, $chain)
    {
        if (is_array($chain)) {
            $key = key($chain);
            return isset($arr[$key]) ? self::getValue($arr[$key], $chain[$key]) : null;
        }
        return isset($arr[$chain]) ? $arr[$chain] : null;
    }
}
