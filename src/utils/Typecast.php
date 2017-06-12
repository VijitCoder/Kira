<?php
namespace kira\utils;

/**
 * Приведение типов
 *
 * Основная идея: строгие требования. Если переданное значение не может без изменений трактоваться, как ожидается,
 * возращаем NULL.
 */
class Typecast
{
    /**
     * Приведение скалярного значения к строке
     *
     * Зачем: не всякое переданное значение можно трактовать как строку.
     *
     * Прим.: для приведения булева значения к какому-нибудь строковому названию кроме "1" или "0"
     * используйте Strings::strBool().
     *
     * @param mixed $value   исходное значение
     * @param mixed $instead что вернуть, если значение невалидное
     * @return string|mixed
     */
    public static function string($value, $instead = null)
    {
        return is_scalar($value) ? strval($value) : $instead;
    }

    /**
     * Приведение скалярного значения к логическому
     *
     * Зачем: boolval() слишком примитивный, filter_var(FILTER_VALIDATE_BOOLEAN) поддерживает меньшее количество
     * допустимых значений.
     *
     * @param mixed $value   исходное значение
     * @param mixed $instead что вернуть, если значение невалидное
     * @return bool|mixed
     */
    public static function bool($value, $instead = null)
    {
        if (!is_scalar($value)) {
            return $instead;
        }

        if ($value === true || $value === false) {
            return $value;
        }

        return preg_match('~^true|1|on|yes|checked|истина|да$~iu', $value)
            ? true
            : (preg_match('~^false|0|off|no|unchecked|ложь|нет$~iu', $value) ? false : $instead);
    }

    /**
     * Приведение скалярного значения к целому числу. Ожидаем десятичное число.
     *
     * Зачем: filter_var(FILTER_VALIDATE_INT) неправильно работает с ведущими нулями.
     *
     * @param mixed $value   исходное значение
     * @param mixed $instead что вернуть, если значение невалидное
     * @return int|mixed
     */
    public static function int($value, $instead = null)
    {
        if (!is_scalar($value)) {
            return $instead;
        }
        return preg_match('~^-?\d+$~', $value) ? intval($value) : $instead;
    }

    /**
     * Приведение скалярного значения к рациональному. Ожидаем десятичное число.
     *
     * Зачем: floatval() достанет число, отбросив неверный хвост, filter_var(FILTER_VALIDATE_FLOAT) допускает запись
     * числа типа "-.95" или "21."
     *
     * @param mixed $value   исходное значение
     * @param mixed $instead что вернуть, если значение невалидное
     * @return float|mixed
     */
    public static function float($value, $instead = null)
    {
        if (!is_scalar($value)) {
            return $instead;
        }
        return preg_match('~^-?\d+(\.\d+)?$~', $value) ? floatval($value) : $instead;
    }
}
