<?php
use kira\exceptions\FormException;
use kira\validation\validators\Typecast;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем валидатор приведения к типу
 *
 * Не проверяем тут все возможные типы, т.к. для этого есть отдельный тест, покрывающий kira\utils\TypeCast. Здесь
 * же важно видеть, что валидатор вообще работает и валидирует правильно.
 */
class TypecastValidatorTest extends TestCase
{
    /**
     * Проверяем приведение к типу с дефолтным сообщением об ошибке
     */
    public function test_typecast_default()
    {
        $validator = new Typecast(Typecast::STRING);

        $this->assertTrue($validator->validate('some string'));
        $this->assertTrue($validator->validate(123));
        $this->assertTrue(is_string($validator->value), 'Число не преобразовано в строку');

        $this->assertFalse($validator->validate([123]));
    }

    /**
     * Свое сообщение в валидаторе приведения к типу
     */
    public function test_typecast_with_custom_message()
    {
        $message = 'Должно быть целое число';
        $validator = new Typecast([
            'type'    => Typecast::INT,
            'message' => $message,
        ]);

        $this->assertFalse($validator->validate('x123'));
        $this->assertEquals($message, $validator->error);
    }

    /**
     * Пропустим обязательную настройку валидатора
     */
    public function test_without_required_options()
    {
        $this->expectException(FormException::class);
        new Typecast(true);

    }

    /**
     * Попытка привести значение к неизвестному типу
     */
    public function test_typecast_to_wrong_type()
    {
        $this->expectException(FormException::class);
        (new Typecast)->validate('unknown_type');
    }
}
