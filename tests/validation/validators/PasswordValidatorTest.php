<?php
use kira\validation\validators\Password;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем валидаторы, для которых нет отдельных тестов
 */
class PasswordValidatorTest extends TestCase
{
    public function test_password()
    {
        $validator = new Password([
            'min_length' => 6,
            'min_combination' => 4,
            'glue' => ' | '
        ]);

        $this->assertTrue($validator->validate('`12Qwe'));
        $this->assertTrue($validator->validate('[`12Qwe]>', 'Пароль с не учитываемыми символами оказался невалидным'));

        $this->assertFalse($validator->validate('`1 q'));
        $errors = explode(' | ', $validator->error);
        $this->assertEquals(2, count($errors));

        $this->assertFalse($validator->validate('12345!'));

        $this->assertFalse($validator->validate('[12Qwe]>',
            'Пароль с не учитываемыми символами увеличил счетчик комбинаций'));
    }
}
