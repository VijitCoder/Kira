<?php
use kira\net\Request;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем только магические методы получения данных из суперглобальных переменных.
 *
 * Интеграции тут нет, как таковой. Только то, что данные будем записывать прямо в суперглобальную переменную, а
 * это некоторая зависимость, ее состояние может измениться из-вне.
 */
class RequestTest extends TestCase
{
    /**
     * Тестируем магические методы получения данных из суперглобальных переменных.
     * Работаем только с $_POST для удобства, остальные переменные абсолютно так же обрабатываются.
     */
    public function test_magic()
    {
        $arrValue = [
            'nested'  => 2,
            'another' => 6,
        ];
        $_POST = [
            'empty'     => '',
            'int'       => 33,
            'wrong_int' => 'not_int',
            'bool'      => 'off',
            'regex'     => '12/2017',
            'enum'      => '3F',
            'deep'      => $arrValue,
        ];

        // Прим: я знаю про assertNull(), assertFalse(). Тут удобнее использовать assertEquals(), чтобы наглядно
        // показать, что именно ожидаем.

        // Просто получение значений, без приведения к типу
        $this->assertEquals($_POST, Request::post(), 'Вообще все значения');
        $this->assertEquals($arrValue, Request::post('deep'), 'Значение-массив, без приведения к типу');
        $this->assertEquals('', Request::post('empty', 'этого теста не будет'), 'Пустая строка не есть NULL');
        $this->assertEquals(null, Request::post('nop'), 'Не найдено значение');
        $this->assertEquals('200', Request::post('nop', '200'), 'Не найдено значение, есть дефолтное');

        // Приведение к типу INTEGER
        $this->assertEquals(33, Request::postAsInt('int'), 'Приведение к INTEGER');
        $this->assertEquals(null, Request::postAsInt('wrong_int'), 'Неверное INTEGER значение');
        $this->assertEquals(100, Request::postAsInt('wrong_int', 100), 'Неверное INTEGER значение, выдать дефолтное');

        $this->assertEquals(false, Request::postAsBool('bool'), 'Приведение к BOOLEAN');

        // Проверка по регулярному выражению
        $this->assertEquals('12/2017', Request::postAsRegexp('regex', '~^\d{2}/\d{4}$~'), 'Проверка по регулярке');
        $this->assertEquals(null, Request::postAsRegexp('regex', '~^[a-z]+$~'), 'Не прошло регулярку, нет дефолта');

        // Массив допустимых значений
        $this->assertEquals('3F', Request::postAsEnum('enum', ['FF', '3F', '2C']), 'Проверка по списку');
        $this->assertEquals('ED', Request::postAsEnum('enum', ['FF', '2C'], 'ED'), 'Нет в списке, есть дефолт');

        // Получение значения из вложенного массива данных, с приведением к типу
        $this->assertEquals(2, Request::postAsInt(['deep' => 'nested']), 'Вложенное INTEGER значение');
        $this->assertEquals(null, Request::postAsInt(['deep' => 'nop']), 'Не найдено вложенное значение');
        $this->assertEquals(10, Request::postAsInt(['deep' => 'nop'], 10),
            'Не найдено вложенное значение, есть дефолт');

        // Приведение к типу STRING
        $this->assertEquals('33', Request::postAsString('int'), 'Число в строковом формате');
        $this->assertEquals(null, Request::postAsString('deep'), 'Значение-массив, проверка приведением к типу');
    }
}
