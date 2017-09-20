<?php
use kira\exceptions\FormException;
use kira\validation\validators;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем валидаторы: filterVar, External
 */
class FilterVarAndExternalTest extends TestCase
{
    public function test_external()
    {
        $someValidator = function ($value, $options) {
            $thisRight = $options['thisRight'] ?? false;
            return $thisRight ? ['value' => ++$value] : ['error' => 'Неверное значение'];
        };

        // Правильное значение
        $validator = new validators\External([
            'function' => $someValidator,
            'options'  => ['thisRight' => true],
        ]);
        $this->assertTrue($validator->validate(23));
        $this->assertEquals(24, $validator->value);

        // Неправильное значение
        $validator = new validators\External([
            'function' => $someValidator,
            'options'  => ['thisRight' => false],
        ]);
        $this->assertFalse($validator->validate(23));
        $this->assertEquals('Неверное значение', $validator->error);

        // Забыли задать функцию валидации
        $this->expectException(FormException::class);
        (new validators\External)->validate(23);
    }

    /**
     * Тест валидатора filterVar() без дефолтных значений в php::filter_var()
     */
    public function test_filterVar()
    {
        $validatorOptions = [
            'filter'  => FILTER_VALIDATE_REGEXP,
            'options' => [
                'regexp' => '~^[a-z0-9-_]+$~i',
            ],
            'message' => 'Недопустимые символы в логине',
        ];

        $validator = new validators\FilterVar($validatorOptions);

        $this->assertTrue($validator->validate('vijit'));
        $this->assertFalse($validator->validate('vijit coder'));
        $this->assertEquals('Недопустимые символы в логине', $validator->error);
    }

    /**
     * Тест валидатора filterVar(). Случай, когда он всегда вернет TRUE, потому что в настройки
     * функции php::filter_var() передается значение по умолчанию.
     */
    public function test_filterVar_with_default_value()
    {
        $validatorOptions = [
            'filter'  => FILTER_VALIDATE_REGEXP,
            'options' => [
                'default' => 'unknown', // разница с предыдущим тестом только тут
                'regexp'  => '~^[a-z0-9-_]+$~i',
            ],
            'message' => 'Недопустимые символы в логине',
        ];

        $validator = new validators\FilterVar($validatorOptions);

        // Значение невалидное, но валидатор вернет истину, т.к. задано дефолтное значение
        $this->assertTrue($validator->validate('vijit coder'));
        $this->assertEquals('unknown', $validator->value);
    }
}
