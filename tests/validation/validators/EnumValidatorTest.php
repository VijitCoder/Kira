<?php
use kira\exceptions\FormException;
use kira\validation\validators\Enum;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем валидатор, который проверяет значение по списку допустимых значений
 */
class EnumValidatorTest extends TestCase
{
    /**
     * Допустимые значения перечислены в массиве
     */
    public function test_enum_array_set()
    {
        $validator = new Enum(['one', 'два']);
        $this->assertTrue($validator->validate('one'));
        $this->assertTrue($validator->validate('два'));
        $this->assertFalse($validator->validate('whatever'));
    }

    /**
     * Допустимые значения перечислены в строке
     */
    public function test_enum_string_set()
    {
        $validator = new Enum('one|два');
        $this->assertTrue($validator->validate('one'));
        $this->assertFalse($validator->validate('whatever'));
    }

    /**
     * Полный формат валидатора
     * Тут же проверим регистронезависимое сравнение
     */
    public function test_enum_full_format()
    {
        $customErrorMessage = 'Значение не найдено';
        $validator = new Enum([
            'values'      => 'one|два',
            'insensitive' => true,
            'message'     => $customErrorMessage,
        ]);

        $this->assertEquals($customErrorMessage, $validator->error);

        $this->assertTrue($validator->validate('one'));
        $this->assertTrue($validator->validate('oNe'));
        $this->assertTrue($validator->validate('ДВА'));
        $this->assertFalse($validator->validate('two'));
    }

    /**
     * Тестируем исключительные ситуации: допустимые значения вообще не заданы; значения заданы не в том ключе.
     * @dataProvider exceptionData
     * @param $values
     */
    public function test_enum_exceptions($values)
    {
        $this->expectException(FormException::class);
        new Enum($values);
    }

    public function exceptionData()
    {
        return [
            'нет допустимых значений, v1'               => [
                'values' => null,
            ],
            'нет допустимых значений, v2'               => [
                'values' => '',
            ],
            'нет допустимых значений, v3'               => [
                'values' => [],
            ],
            'допустимые значения заданы не в том ключе' => [
                'values' => [
                    'wrong_key' => [1, 5, 6],
                ],
            ],
        ];
    }
}
