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
        $this->assertEquals($_POST, Request::post(), 'Ошибка получения всех значений');
        $this->assertEquals($arrValue, Request::post('deep'),
            'Ошибка получения значения-массива, без приведения к типу');
        $this->assertEquals('', Request::post('empty', 'этой фразы не будет'), 'Неверное значение для пустой строки');
        $this->assertEquals(null, Request::post('nop'), 'Найдено несуществующе значение');
        $this->assertEquals('200', Request::post('nop', '200'), 'Подстановка дефолтного значения не выполнена');

        // Приведение к типу INTEGER
        $this->assertEquals(33, Request::postAsInt('int'), 'Приведение к INTEGER не выполнено');
        $this->assertEquals(null, Request::postAsInt('wrong_int'), 'Неожиданно верное INTEGER значение');
        $this->assertEquals(100, Request::postAsInt('wrong_int', 100), 'Подстановка дефолтного значения не выполнена');

        $this->assertEquals(false, Request::postAsBool('bool'), 'Приведение к BOOLEAN не выполнено');

        // Проверка по регулярному выражению
        $this->assertEquals('12/2017', Request::postAsRegexp('regex', '~^\d{2}/\d{4}$~'),
            'Проверка по регулярке  не выполнена');
        $this->assertEquals(null, Request::postAsRegexp('regex', '~^[a-z]+$~'), 'Не прошло регулярку, ожидали NULL');

        // Массив допустимых значений
        $this->assertEquals('3F', Request::postAsEnum('enum', ['FF', '3F', '2C']), 'Проверка по списку не выполнена');
        $this->assertEquals('ED', Request::postAsEnum('enum', ['FF', '2C'], 'ED'),
            'Подстановка дефолтного значения не выполнена');

        // Получение значения из вложенного массива данных, с приведением к типу
        $this->assertEquals(2, Request::postAsInt(['deep' => 'nested']),
            'Не удалось получить вложенное INTEGER значение');
        $this->assertEquals(null, Request::postAsInt(['deep' => 'nop']), 'Найдено вложенное значение там, где его нет');
        $this->assertEquals(10, Request::postAsInt(['deep' => 'nop'], 10),
            'Подстановка дефолтного значения не выполнена');

        // Приведение к типу STRING
        $this->assertEquals('33', Request::postAsString('int'), 'Приведение к типу STRING не выполнено');
        $this->assertEquals(null, Request::postAsString('deep'),
            'Значение-массив, ожидали NULL после приведения к типу');
    }
}
