<?php
/**
 * Свои манипуляции с массивами. В PHP их много, но иногда чего-то не хватает :)
 */

namespace utils;

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
     * @param array $arr
     * @return array
     */
    public static function filter_keys($callback, $arr) {
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
     * Это объединение массивов, как описано в справке, а не как на самом деле работает array-merge-recursive().
     *
     * Прим: в комментарии другое объединение, там числовые ключи заменяются так же, как и строковые.
     *
     * Логика объединения:
     * - значения с числовыми ключами всегда добавляются в конец, независимо от самих значений.
     * - строковые ключи перезаписываются. Если у обоих массивов значения окажутся подмассивами, тогда они объединяются
     * в рекурсивном вызове этой функции.
     *
     * Схематично (для строковых ключей):
     *  value1 + array2 = array2
     *  array1 + value2 = value2
     *  array1 + array2 > recurse call
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    public static function merge_recursive(array &$array1, array &$array2)
    {
        $merged = $array1;

        foreach ($array2 as $key => &$value) {
            if (is_int($key)) {
               $merged[] = $value;
            } else if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = self::merge_recursive($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
