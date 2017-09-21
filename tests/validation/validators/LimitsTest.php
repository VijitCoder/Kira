<?php
use kira\exceptions\FormException;
use kira\validation\validators\Limits;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем валидатор Limits
 */
class LimitsTest extends TestCase
{
    /**
     * Проверка диапазона числа. Дефолтные сообщения, оба предела
     */
    public function test_limits_number()
    {
        $validator = new Limits([
            'min' => '4.56',
            'max' => 10.2,
        ]);

        $this->assertTrue($validator->validate(4.56));
        $this->assertTrue($validator->validate(7));
        $this->assertTrue($validator->validate(10.2));

        $this->assertFalse($validator->validate(3));
        $this->assertFalse($validator->validate(12));

        $this->expectException(FormException::class);
        $validator->validate(['one']);
    }

    /**
     * Проверка длины строки. Дефолтные сообщения, оба предела
     */
    public function test_limits_string()
    {
        $validator = new Limits([
            'min' => 5,
            'max' => 10,
        ]);

        $this->assertTrue($validator->validate('plate'));      // 5 символов
        $this->assertTrue($validator->validate('тарелка'));    // 7 символов
        $this->assertTrue($validator->validate('supervisor')); // 10 символов

        $this->assertFalse($validator->validate('нет'));
        $this->assertFalse($validator->validate('tyrannosaur Rex')); // 15 символов
    }

    /**
     * Один предел или вообще без ограничений
     */
    public function test_no_limit()
    {
        $validator = new Limits([
            'min' => 5,
        ]);

        $this->assertTrue($validator->validate(10));
        $this->assertTrue($validator->validate(100043));
        $this->assertFalse($validator->validate(3));

        $validator = new Limits([
            'max' => 10,
        ]);

        $this->assertTrue($validator->validate(-10005));
        $this->assertTrue($validator->validate(10));
        $this->assertFalse($validator->validate(12));

        // Валидация границ с такими настройками не имеет смысла, но не должна падать.
        $this->assertTrue((new Limits)->validate(100500));
    }

    /**
     * Кастомные соообщения об ошибках
     */
    public function test_custom_message()
    {
        $validator = new Limits([
            'min'     => 5,
            'max'     => 10,
            'message' => 'Общее сообщение на ошибки',
        ]);

        $this->assertFalse($validator->validate(3));
        $this->assertEquals('Общее сообщение на ошибки', $validator->error);
        $this->assertFalse($validator->validate(12));
        $this->assertEquals('Общее сообщение на ошибки', $validator->error);

        $validator = new Limits([
            'min'     => 5,
            'max'     => 10,
            'message' => [
                'min' => 'Число не меньше :min',
                'max' => 'Слишком большое число',
            ],
        ]);

        $this->assertFalse($validator->validate(3));
        $this->assertEquals('Число не меньше 5', $validator->error);
        $this->assertFalse($validator->validate(12));
        $this->assertEquals('Слишком большое число', $validator->error);
    }

    /**
     * Тест на угадывание правильного типа проверяемого значения
     */
    public function test_guess()
    {
        $validator = new Limits([
            'min'     => 2,
            'max'     => 10,
        ]);

        $this->assertTrue($validator->validate('77'), 'Ошибка валидации верного значения, как строки');
        $this->assertFalse($validator->validate(77),
            'Ошибка валидации: значение, как число, не должно входить в заданный диапазон');
    }
}
