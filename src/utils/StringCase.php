<?php
namespace kira\utils;

/**
 * Преобразование строки между различными регистрами: CamelCase, snake_case, kebab-case.
 * Приведение строки к указанным регистрам.
 */
class StringCase
{
    /**
     * some_string > SomeString
     * @param string $str
     * @return string
     */
    public static function snakeToCamel(string $str): string
    {
        return ucfirst(str_replace('_', '', ucwords($str, '_')));
    }

    /**
     * some_string > some-string
     * @param string $str
     * @return string
     */
    public static function snakeToKebab(string $str): string
    {

    }

    /**
     * SomeString > some_string
     * @param string $str
     * @return string
     */
    public static function camelToSnake(string $str): string
    {

    }

    /**
     * SomeString > some-string
     * @param string $str
     * @return string
     */
    public static function camelToKebab(string $str): string
    {

    }

    /**
     * some-string > some_string
     * @param string $str
     * @return string
     */
    public static function kebabToSnake(string $str): string
    {

    }

    /**
     * some-string > SomeString
     * @param string $str
     * @return string
     */
    public static function kebabToCamel(string $str): string
    {

    }

    /**
     * Удаление любых проблельных символов и приведение к верхнему регистру первой буквы каждого слова
     * some string > SomeString
     * @param string $str
     * @return string
     */
    public static function toCamel(string $str): string
    {

    }

    /**
     * Замена любых не буквенно-числовых символов на подчеркивание
     * some string > some_string
     * @param string $str
     * @return string
     */
    public static function toSnake(string $str): string
    {
        return preg_replace(self::getPatternForReplace(), '_', $str);
    }

    /**
     * Замена любых не буквенно-числовых символов на дефис
     * some string > some-string
     * @param string $str
     * @return string
     */
    public static function toKebab(string $str): string
    {
        return preg_replace(self::getPatternForReplace(), '-', $str);
    }

    /**
     * Регулярка для замены символов на заданный кейс
     *
     * Не заменять только латинские буквы и цифры. Последовательность неподходящих символов заменять на один символ
     * кейса.
     *
     * @return string
     */
    private static function getPatternForReplace(): string
    {
        return '/[^a-z0-9]+/i';
    }
}
