<?php
use kira\validation\validators\FilterVar;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем валидатор filter_var
 */
class FilterVarValidatorTest extends TestCase
{
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

        $validator = new FilterVar($validatorOptions);

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

        $validator = new FilterVar($validatorOptions);

        // Значение невалидное, но валидатор вернет истину, т.к. задано дефолтное значение
        $this->assertTrue($validator->validate('vijit coder'));
        $this->assertEquals('unknown', $validator->value);
    }
}
