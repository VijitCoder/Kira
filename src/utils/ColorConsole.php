<?php
namespace kira\utils;

/**
 * Работа с консолью через управляющие последовательности: цвет, стиль текста и т.п. Так же можно рисовать в консоли,
 * перемещая курсор и используя пвсевдографику.
 *
 * Исходик нашел {@link https://habrahabr.ru/post/45041/ тут}
 *
 * Исправил баги и развил под свои нужды.
 */
class ColorConsole
{
    /**
     * Цвет теста
     * @var array
     */
    private $colors = array(
        'black'   => 30,
        'red'     => 31,
        'green'   => 32,
        'brown'   => 33,
        'blue'    => 34,
        'magenta' => 35,
        'cyan'    => 36,
        'white'   => 37,
        'default' => 39,
    );

    /**
     * Цвет заднего фона
     * @var array
     */
    private $bgColors = array(
        'black'   => 40,
        'red'     => 41,
        'green'   => 42,
        'brown'   => 43,
        'blue'    => 44,
        'magenta' => 45,
        'cyan'    => 46,
        'white'   => 47,
        'default' => 49,
    );

    /**
     * Стиль текста
     * @var array
     */
    private $styles = array(
        'default'          => 0,
        'bold'             => 1,
        'faint'            => 2,
        'normal'           => 22,
        'italic'           => 3,
        'notItalic'        => 23,
        'underlined'       => 4,
        'doubleUnderlined' => 21,
        'notUnderlined'    => 24,
        'blink'            => 5,
        'blinkFast'        => 6,
        'notBlink'         => 25,
        'negative'         => 7,
        'positive'         => 27,
    );

    /**
     * Результирующий тест. Буфер
     * @var string
     */
    private $text = '';

    /**
     * Получение текста из внутреннего буфера класса
     * @param bool $flush TRUE - сброс буфера
     * @return string
     */
    public function getText($flush = true): string
    {
        $text = $this->text;
        if ($flush) {
            $this->text = '';
        }
        return $text;
    }

    /**
     * Удаляем из текста управляющие последовательности: цвет, стиль и звук.
     *
     * Ожидается использование этого метода для вывода сообщения как в консоль так и в браузер, поэтому удаляется только
     * то, что можно увидеть. Т.е. перемещения курсора и мапуляции с консолью тут неважны. Звук выпиливается просто,
     * как бонус.
     *
     * @param bool $flush TRUE - сброс буфера
     * @return string
     */
    public function getClearText($flush = true): string
    {
        $font_style = '/\033\[[\[\d;]+m/';
        $sound = '/\033\[(10|11);\d+\]|\007/';
        $text = preg_replace([$font_style, $sound], '', $this->text);
        if ($flush) {
            $this->text = '';
        }
        return $text;
    }

    /**
     * Заменить текст в буфере
     * @param string $text текст для замены
     * @return $this
     */
    public function setText(string $text)
    {
        $this->text = $text;
        return $this;
    }

    /**
     * Добавить текст в буфер
     * @param string $text текст для добавления
     * @return $this
     */
    public function addText(string $text)
    {
        $this->text .= $text;
        return $this;
    }

    /**
     * Сброс буфера и установка атрибутов по умолчанию. Т.е. приводим объект класса к первоначальному состоянию
     */
    public function flush()
    {
        $this->text = '';
        $this->reset();
        return $this;
    }

    /**
     * Выдать текст в STDOUT. При этом буфер будет сброшен.
     *
     * Добавить к буферизированному ранее тексту переданный в параметре.
     *
     * @param string $text "хвост" выводимого текста
     * @return $this
     */
    public function draw(string $text = '')
    {
        echo $this->text . $text;
        $this->text = '';
        return $this;
    }

    /**
     * Установка частоты звука
     * @param int $herz частота звука, в Гц
     * @return $this
     */
    public function setSoundHerz(int $herz = 100)
    {
        $this->text .= "\033[10;{$herz}]";
        return $this;
    }

    /**
     * Установка продожительности звука
     * @param int $milliseconds продолжительность звука, миллисекунды (1/1000 сек)
     * @return $this
     */
    public function setSoundLong(int $milliseconds = 500)
    {
        $this->text .= "\033[11;{$milliseconds}]";
        return $this;
    }

    /**
     * Выдать звук. Параметры звука настраиваются отдельными методами
     */
    public function beep()
    {
        $this->text .= "\007";
        return $this;
    }

    /**
     * Курсор вверх
     * @param int $lines количество рядов (строк), на которые нужно переместить курсор
     * @return $this
     */
    public function cursorUp(int $lines = 1)
    {
        echo "\033[{$lines}A";
        return $this;
    }

    /**
     * Курсор вниз
     * @param int $lines количество рядов (строк), на которые нужно переместить курсор
     * @return $this
     */
    public function cursorDown(int $lines = 1)
    {
        echo "\033[{$lines}B";
        return $this;
    }

    /**
     * Курсор вправо
     * @param int $columns количество столбцов (символов), на которые нужно переместить курсор
     * @return $this
     */
    public function cursorRight(int $columns = 1)
    {
        echo "\033[{$columns}C";
        return $this;
    }

    /**
     * Курсор влево
     * @param int $columns количество столбцов (символов), на которые нужно переместить курсор
     * @return $this
     */
    public function cursorLeft(int $columns = 1)
    {
        echo "\033[{$columns}D";
        return $this;
    }

    /**
     * курсор вниз на заданное количество строк и поставить в начало строки
     * @param int $lines количество рядов (строк), на которые нужно переместить курсор
     * @return $this
     */
    public function cursorDownFirst(int $lines = 1)
    {
        echo "\033[{$lines}E";
        return $this;
    }

    /**
     * курсор вверх на заданное количество строк и поставить в начало строки
     * @param int $lines количество рядов (строк), на которые нужно переместить курсор
     * @return $this
     */
    public function cursorUpFirst(int $lines = 1)
    {
        echo "\033[{$lines}F";
        return $this;
    }

    /**
     * Курсор в указанный столбец текущей строки
     * @param int $position
     * @return $this
     */
    public function cursorAtPosition(int $position = 1)
    {
        echo "\033[{$position}G";
        return $this;
    }

    /**
     * Перемещение курсора на позицию (абсолютные координаты курсора)
     * @param int $row    ряд
     * @param int $column колонка
     * @return $this
     */
    public function cursorToCoordinates(int $row = 1, int $column = 1)
    {
        echo "\033[{$row};{$column}H";
        return $this;
    }

    /**
     * Установка стиля текста
     * @param string $style название стиля
     * @return $this
     */
    public function setStyle(string $style = 'default')
    {
        $this->text .= "\033[" . $this->styles[$style] . "m";
        return $this;
    }

    /**
     * Установка цвета текста
     * @param string $color название цвета
     * @return $this
     */
    public function setColor(string $color = 'default')
    {
        $this->text .= "\033[" . $this->colors[$color] . "m";
        return $this;
    }

    /**
     * Установка цвета заднего фона
     * @param string $color название цвета
     * @return $this
     */
    public function setBgColor(string $color = 'default')
    {
        $this->text .= "\033[" . $this->bgColors[$color] . "m";
        return $this;
    }

    /**
     * Сброс всех атрибутов в их значения по умолчанию
     * @return $this
     */
    public function reset()
    {
        $this->text .= "\033[0m";
        return $this;
    }

    /**
     * Получение символа по заданному юникоду
     *
     * Передавать только цифры, но в шестрадцатиричной СИ. Наример: '250c' = 'граница вниз и направо'.
     *
     * @link {https://unicode-table.com/ru/blocks/box-drawing/ Символы для рисования рамок}
     * @param string $uCode цифры из шестнадцатиричного кода символа в кодировке UTF-8
     * @return $this
     */
    public function getSymbol(string $uCode)
    {
        return html_entity_decode("&#x{$uCode};", ENT_NOQUOTES, 'UTF-8');
    }

    /**
     * Добавить в буфер символ с заданным юникодов
     * @param string $uCode
     * @return $this
     */
    public function addSymbol(string $uCode)
    {
        $this->text .= $this->getSymbol($uCode);
        return $this;
    }

    /**
     * Очистка окна текущей консоли
     * @return $this
     */
    public function clear()
    {
        echo "\033c";
        return $this;
    }

    /**
     * Сделать текущей консоль с указанным номером
     * @param int $num
     * @return $this
     */
    public function selectConsole($num)
    {
        echo "\033[12;{$num}]";
        return $this;
    }

    /**
     * Работа с xterm. Присваивает имя окну и иконке, потом выдает звуковой сигнал.
     * @param string $name
     * @return $this
     */
    public function setAppName($name)
    {
        echo "\033]0;{$name}\007";
        return $this;
    }

    /**
     * Работа с xterm. Присваивает имя окну, потом выдает звуковой сигнал.
     * @param string $name
     * @return $this
     */
    public function setTitle($name)
    {
        echo "\033]2;{$name}\007";
        return $this;
    }

    /**
     * Работа с xterm. Присваивает имя иконке, потом выдает звуковой сигнал.
     * @param string $name
     * @return $this
     */
    public function setIcon($name)
    {
        echo "\033]1;{$name}\007";
        return $this;
    }
}
