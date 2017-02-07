<?php
use PHPUnit\Framework\TestCase;
use kira\utils\ColorConsole;

/**
 * Тестируем некоторые методы из ColorConsole
 */
class ColorConsoleTest extends TestCase
{
    /**
     * Тест: сборка произвольного текста с его получением в переменную
     */
    public function test_getText()
    {
        $result = (new ColorConsole)
            ->addText('Какой-то ')->setColor('green')->setBgColor('blue')->setStyle('bold')
            ->addText('текст на')->setBgColor('black')
            ->addText(' черном фоне')->reset()
            ->addText('. Хвост')
            ->getText()
        ;
        $expect = "Какой-то \033[32m\033[44m\033[1mтекст на\033[40m черном фоне\033[0m. Хвост";
        $this->assertEquals($expect, $result, 'Произвольный текст с управлящими последовательностями');
    }

    /**
     * Тест: удаляем из текста управляющие последовательности: цвет, стиль и звук.
     */
    public function test_getClearText()
    {
        $result = (new ColorConsole)
            ->addText('Какой-то ')->setColor('green')
            ->addText('текст на')->setBgColor('blue')
            ->addText(' синем фоне')->reset()
            ->setSoundLong(500)
            ->setSoundHerz(440)
            ->addText('. Хвост')
            ->beep()
            ->getClearText()
        ;
        $expect = 'Какой-то текст на синем фоне. Хвост';
        $this->assertEquals($expect, $result, 'Чистый произвольный текст');
    }
}
