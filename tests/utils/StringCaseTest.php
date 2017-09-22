<?php

use PHPUnit\Framework\TestCase;
use kira\utils\StringCase;

/**
 * Тестируем утилиту преобразования строки между различными регистрами: CamelCase, snake_case, kebab-case.
 *
 * Набор тестов показывает обычное использование функций, какие символы будут проигнорированы или останутся без
 * изменений.
 */
class StringCaseTest extends TestCase
{
    /**
     * snakeToCamel() и kebabToCamel() используют один метод, поэтому тестируем только один регистр
     */
    public function test_replace_to_camel()
    {
        $this->assertEquals('SomeString10', StringCase::snakeToCamel('some_string_10'));
        $this->assertEquals('OtherString!', StringCase::snakeToCamel('oTHer_strIng!'));
        $this->assertEquals('Somestring', StringCase::snakeToCamel('SomeString'));
    }

    /**
     * camelToSnake() и camelToKebab() используют один метод, поэтому тестируем только один регистр
     */
    public function test_transform_camel_to_case()
    {
        $this->assertEquals('some_string10', StringCase::camelToSnake('SomeString10'));
        $this->assertEquals('some_string_m', StringCase::camelToSnake('someStringM'));
        $this->assertEquals('o_t_her_str_ing!', StringCase::camelToSnake('OTHerStrIng!'));
        $this->assertEquals('some_string', StringCase::camelToSnake('some_string'));
    }

    /**
     * toSnake() и toKebab() используют один метод, поэтому тестируем только один регистр
     * toCamel() использует тоже самое + протестированный выше метод. Поэтому для него нет теста.
     */
    public function test_replace_to_case()
    {
        $this->assertEquals('some_string_10', StringCase::toSnake('* Some, strIng! 10 *'));
        $this->assertEquals('some_string', StringCase::toSnake('sOme_strIng'));
    }

    public function test_snakeToKebab()
    {
        $this->assertEquals('some-string', StringCase::snakeToKebab('some_string'));
        $this->assertEquals('oTHer-strIng!', StringCase::snakeToKebab('oTHer_strIng!'));
    }

    public function test_kebabToSnake()
    {
        $this->assertEquals('some_string', StringCase::kebabToSnake('some-string'));
        $this->assertEquals('oTHer_strIng!', StringCase::kebabToSnake('oTHer-strIng!'));
    }
}
