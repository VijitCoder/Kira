<?php
use kira\exceptions\FormException;
use kira\validation\validators\AbstractValidator;
use kira\validation\validators\External;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем валидатор External
 */
class ExternalValidatorTest extends TestCase
{
    /**
     * Тест external c минимальными настройками. Класс кастомного валидатора в этом же скрипте.
     */
    public function test_default()
    {
        $validator = new External([
            'class'   => CustomValidator::class,
            'options' => [],
        ]);

        $this->assertTrue($validator->validate(10));
        $this->assertEquals('ten', $validator->value);

        $this->assertFalse($validator->validate(5));
        $this->assertEquals('Ожидаем число 10', $validator->error);
    }

    /**
     * Тест: передача своего сообщения об ошибке во внешний валидатор
     */
    public function test_custom_message()
    {
        $validator = new External([
            'class'   => CustomValidator::class,
            'options' => [
                'message' => 'не угадал',
            ],
        ]);

        $this->assertFalse($validator->validate(5));
        $this->assertEquals('не угадал', $validator->error);
    }

    /**
     * Тест External с пропущенным параметром options. Это неправильная ситуация, т.к. супер-класс AbstractValidator
     * требует указания options для любого валидатора. Но если в данном случае для кастомного валидатора забыть
     * про options, трудно будет разобраться, где именно ошибка. Поэтому External подставляет пустой массив для
     * пропущенного параметра.
     */
    public function test_no_options()
    {
        $validator = new External([
            'class'   => CustomValidator::class,
        ]);

        $this->assertTrue($validator->validate(10));
        $this->assertEquals('ten', $validator->value);
    }

    public function test_exception_no_class()
    {
        $this->expectException(FormException::class);
        new External([]);
    }

    public function test_exception_wrong_inheritance()
    {
        $this->expectException(FormException::class);
        new External([
            'class' => \StrClass::class
        ]);
    }
}

/**
 * Кастомный валидатор для теста External
 */
class CustomValidator extends AbstractValidator
{
    protected $error = 'Ожидаем число 10';

    public function validate($value)
    {
        if ($value === 10) {
            $this->value = 'ten';
            return true;
        }

        return false;
    }
}
