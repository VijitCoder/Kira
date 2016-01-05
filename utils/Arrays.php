<?php
/**
 * Свои манипуляции с массивами. В PHP их много, но иногда чего-то не хватает :)
 */

namespace utils;

class Arrays
{
    /**
     * Оборачиваем массив любой вложенности в класс ArrayObject для доступа к элементам, как свойствам
     * класса. Это позволит использовать методы трансов почти без модификации кода.
     *
     * Рекурсия
     *
     * Одно ограничение: не принимаются массивы со смешанными ключами. Каждый вложенный массив либо
     * полностью ассоциативный, либо полностью неассоциативный. Числовые ключи, обернутые в строки,
     * распознаются и массив приравнивается к неассоциативному.
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
}
