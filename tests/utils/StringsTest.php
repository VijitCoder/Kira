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
            'Транслит русский > английский'
        );

        $this->assertEquals(
            'ёжик по травке бежит и хохочет, ежику травка пузик щекочет',
            Strings::eng2rus('jojik po travke bejit i hohochet, ejiku travka puzik schekochet'),
            'Транслит английский > русский'
        );
    }

    /**
     * Приведение к регистру первых букв в мультибайтной кодировке
     */
    public function test_firstLetter()
    {
         $this->assertEquals('Русский', Strings::mb_ucfirst('русский'), 'Заглавная первая буква');
         $this->assertEquals('пРОБЕЛ', Strings::mb_lcfirst('ПРОБЕЛ'), 'Строчная первая буква');
    }

    public function test_word_chunk()
    {
        $this->assertEquals('рус + ски + й', Strings::word_chunk('русский', 3, ' + '), 'Мультибайтное разбиение строки');
    }

    /**
     * Тест: проверка экранированности символа
     */
    public function test_isShielded()
    {
        $text = "неэкранированный \\' <- символ кавычки";
        $pos = mb_strpos($text, '"');
        $result = Strings::isShielded(mb_substr($text, 0, $pos));
        $this->assertFalse($result, 'Неэкранированный символ');

        $text = "экранированный \\\\\[[\d+] <- символ скобки";
        $pos = mb_strpos($text, '[');
        $result = Strings::isShielded(mb_substr($text, 0, $pos));
        $this->assertTrue($result, 'Экранированный символ');
    }

    /**
     * Тест: Приведение булева значения к строке
     */
    public function test_strBool()
    {
        $this->assertEquals('true', Strings::strBool(true), 'Булевое "true"');
        $this->assertEquals('ложь', Strings::strBool(false, Strings::BOOL_RU), 'Булевое "ложь"');
        $this->assertEquals('yes', Strings::strBool(true, Strings::BOOL_YESNO_EN), 'Булевое "yes"');
        $this->assertEquals('нет', Strings::strBool(false, Strings::BOOL_YESNO_RU), 'Булевое "нет"');
        $this->assertEquals('1', Strings::strBool(true, Strings::BOOL_DIGIT), 'Булевое "1"');
    }
}
