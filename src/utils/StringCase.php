<?php
namespace kira\utils;

/**
 * Преобразование строки между различными регистрами: CamelCase, snake_case, kebab-case.
 * Приведение строки к указанным регистрам.
 *
 * Нет мультибайтной поддержки, работает только с латиницей.
 */
class StringCase
{
    /**
     * sOme_strIng > SomeString
     * @param string $str
     * @return string
     */
    public static function snakeToCamel(string $str): string
    {
        return self::replaceCaseToCamel($str, '_');
    }

    /**
     * sOme-strIng > SomeString
     * @param string $str
     * @return string
     */
    public static function kebabToCamel(string $str): string
    {
        return self::replaceCaseToCamel($str, '-');
    }

    /**
     * snake или kebab в camel
     *
     * В таких преобразованиях разделителем слов считается <b>заявленный регистр</b> (snake или kebab). Каждое найденное
     * "слово" приводится к нижнему регистру букв, потом поднимается регистр первой буквы.
     * Поэтому sOme_strIng > SomeString, а не SOmeStrIng.
     *
     * @param string $str
     * @param string $case символ "_" или "-"
     * @return string
     */
    private static function replaceCaseToCamel(string $str, string $case): string
    {
        $str = strtolower($str);
        $str = ucwords($str, $case);
        $str = str_replace($case, '', $str);
        return ucfirst($str);
    }

    /**
     * SomeString > some_string
     * @param string $str
     * @return string
     */
    public static function camelToSnake(string $str): string
    {
        return self::transformCamelToCase($str, '_');
    }

    /**
     * SomeString > some-string
     * @param string $str
     * @return string
     */
    public static function camelToKebab(string $str): string
    {
        return self::transformCamelToCase($str, '-');
    }

    /**
     * Перевести CamelCase в нужный регистр
     * @param string $str  исходная строка
     * @param string $case символ-разделитель (aka нужный регистр)
     * @return string
     */
    private static function transformCamelToCase(string $str, string $case): string
    {
        $parts = preg_split('/([A-Z])/', $str, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

        $words = [];
        $word = '';
        foreach ($parts as $part) {
            if ($part !== lcfirst($part)) {
                if ($word) {
                    $words[] = $word;
                }
                $word = $part;
            } else {
                $word .= $part;
            }
        }

        if ($word) {
            $words[] = $word;
        }

        return strtolower(implode($case, $words));
    }

    /**
     * some_string > some-string
     * @param string $str
     * @return string
     */
    public static function snakeToKebab(string $str): string
    {
        return str_replace('_', '-', $str);
    }

    /**
     * some-string > some_string
     * @param string $str
     * @return string
     */
    public static function kebabToSnake(string $str): string
    {
        return str_replace('-', '_', $str);
    }

    /**
     * sOme strIng! > some_string
     *
     * Замена любых не буквенно-числовых символов на подчеркивание, все в нижний регистр
     *
     * @param string $str
     * @return string
     */
    public static function toSnake(string $str): string
    {
        return self::replaceUnsuitedSymbolsToCase($str, '_');
    }

    /**
     * sOme strIng! > some-string
     *
     * Замена любых не буквенно-числовых символов на дефис, все в нижний регистр
     *
     * @param string $str
     * @return string
     */
    public static function toKebab(string $str): string
    {
        return self::replaceUnsuitedSymbolsToCase($str, '-');
    }

    /**
     * sOme strIng! > SomeString
     *
     * Выравнивание всей строки по нижнему регистру, замена любых не буквенно-числовых символов на пробел и приведение
     * к CamelCase.
     *
     * @param string $str
     * @return string
     */
    public static function toCamel(string $str): string
    {
        $str = self::replaceUnsuitedSymbolsToCase($str, ' ');
        return self::replaceCaseToCamel($str, ' ');
    }

    /**
     * Замена любых не буквенно-числовых символов на заданный символ, приведение к нижнему регистру.
     * @param string $str
     * @param string $case
     * @return string
     */
    private static function replaceUnsuitedSymbolsToCase(string $str, string $case): string
    {
        $str = strtolower($str);
        $words = preg_split(self::getPatternForReplace(), $str, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        return implode($case, $words);
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
