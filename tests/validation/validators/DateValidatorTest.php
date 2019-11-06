<?php
use kira\validation\validators\Date;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем валидатор даты
 */
class DateValidatorTest extends TestCase
{
    /**
     * Тестируем валидатор даты, нагружаем разными форматами и нереальными датами
     *
     * @dataProvider dateData
     *
     * @param string $format          ожидаемый формат даты/времени
     * @param string $value           проверяемое значение
     * @param bool   $expectValidDate TRUE - мы полагаем, что дата верная
     */
    public function test_date(string $format, string $value, bool $expectValidDate)
    {
        $validator = new Date(['format' => $format]);
        $isValid = $validator->validate($value);

        $expectValidDate
            ? $this->assertTrue($isValid)
            : $this->assertFalse($isValid);

        $this->assertEquals($value, $validator->value);
    }

    public function dateData()
    {
        return [
            'MySQL. Правильная полная дата/время' => [
                'format'  => 'Y-m-d H:i:s',
                'value'   => '2012-02-28 12:12:12',
                'isValid' => true,
            ],

            'MySQL. Нереальный день февраля' => [
                'format'  => 'Y-m-d H:i:s',
                'value'   => '2012-02-30 12:12:12',
                'isValid' => false,
            ],

            'MySQL. Правильная дата' => [
                'format'  => 'Y-m-d',
                'value'   => '2012-02-28',
                'isValid' => true,
            ],

            'US. Правильная дата' => [
                'format'  => 'm/d/Y',
                'value'   => '02/28/2012',
                'isValid' => true,
            ],

            'US. Неверно: февраль в невисокосном году' => [
                'format'  => 'm/d/Y',
                'value'   => '02/29/2012',
                'isValid' => true,
            ],

            'Правильное время' => [
                'format'  => 'H:i',
                'value'   => '14:50',
                'isValid' => true,
            ],

            'Нереальное время' => [
                'format'  => 'H:i',
                'value'   => '14:77',
                'isValid' => false,
            ],

            'Только часы' => [
                'format'  => 'H',
                'value'   => '14',
                'isValid' => true,
            ],

            'DateTime::ATOM' => [
                'format'  => DateTime::ATOM,
                'value'   => '2012-02-28T12:12:12+02:00',
                'isValid' => true,
            ],

            'Экзотика' => [
                'format'  => 'D, d M Y H:i:s O',
                'value'   => 'Tue, 28 Feb 2012 12:12:12 +0200',
                'isValid' => true,
            ],
        ];
    }

    /**
     * Тестируем валидатор даты с дефолтным форматом, чтобы быть уверенным, что этот формат не изменился с очередной
     * ревизией кода.
     */
    public function test_date_default_format()
    {
        $validator = new Date(true);
        $value = '2012-02-28 12:12:12';
        $this->assertTrue($validator->validate($value));
        $this->assertEquals($value, $validator->value);

    }

    /**
     * Тестируем валидатор даты с кастомным сообщением.
     */
    public function test_date_custom_message()
    {
        $message = 'Упс! Неверная дата';
        $validator = new Date(['message' => $message]);
        $this->assertFalse($validator->validate('2012-02-32'));
        $this->assertEquals($message, $validator->error);
    }
}
