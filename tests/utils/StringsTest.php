<?php

use PHPUnit\Framework\TestCase;
use kira\utils\Strings;

/**
 * Тестируем утилиту по работе со строками
 */
class StringsTest extends TestCase
{
    public function test_declination()
    {
        $words = ['комментарий', 'комментария', 'комментариев'];

        $this->assertEquals('1 комментарий', Strings::declination(1, $words));
        $this->assertEquals('2 комментария', Strings::declination(2, $words));
        $this->assertEquals('5 комментариев', Strings::declination(5, $words));
    }

    public function test_translit()
    {
        $this->assertEquals(
            'shodni korablya mokryie ot rosyi',
            Strings::rus2eng('сходни корабля мокрые от росы'),
            'Неверный транслит русский > английский'
        );

        $this->assertEquals(
            'ёжик по травке бежит и хохочет, ежику травка пузик щекочет',
            Strings::eng2rus('jojik po travke bejit i hohochet, ejiku travka puzik schekochet'),
            'Неверный транслит английский > русский'
        );
    }

    /**
     * Приведение к регистру первых букв в мультибайтной кодировке
     */
    public function test_firstLetter()
    {
         $this->assertEquals('Русский', Strings::mb_ucfirst('русский'), 'Ошибка приведения к заглавной первой букве');
         $this->assertEquals('пРОБЕЛ', Strings::mb_lcfirst('ПРОБЕЛ'), 'Ошибка приведения к строчной первой букве');
    }

    public function test_word_chunk()
    {
        $this->assertEquals('рус + ски + й', Strings::word_chunk('русский', 3, ' + '),
            'Не верное мультибайтное разбиение строки');
    }

    /**
     * Тест: проверка экранированности символа
     */
    public function test_isShielded()
    {
        $text = "неэкранированный \\' <- символ кавычки";
        $pos = mb_strpos($text, '"');
        $result = Strings::isShielded(mb_substr($text, 0, $pos));
        $this->assertFalse($result, 'Ошибочно обнаружен неэкранированный символ');

        $text = "экранированный \\\\\[[\d+] <- символ скобки";
        $pos = mb_strpos($text, '[');
        $result = Strings::isShielded(mb_substr($text, 0, $pos));
        $this->assertTrue($result, 'Не обнаружен экранированный символ');
    }

    /**
     * Тест: превращение булева значения в строковое название
     */
    public function test_strBool()
    {
        $this->assertEquals('true', Strings::strBool(true), 'Неверное превращение в булевое "true"');
        $this->assertEquals('ложь', Strings::strBool('false', Strings::BOOL_RU),
            'Неверное превращение в булевое "ложь"');
        $this->assertEquals('yes', Strings::strBool(true, Strings::BOOL_YESNO_EN),
            'Неверное превращение в булевое "yes"');
        $this->assertEquals('нет', Strings::strBool(false, Strings::BOOL_YESNO_RU),
            'Неверное превращение в булевое "нет"');
        $this->assertEquals('1', Strings::strBool(true, Strings::BOOL_DIGIT), 'Неверное превращение в булевое "1"');
    }
}
