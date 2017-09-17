<?php
use kira\validation\validators;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем конечные валидаторы
 */
class ValidatorsNewTest extends TestCase
{
    public function test_expect_id()
    {
        $validator = new validators\ExpectId(['message' => 'Неверный id пользователя']);

        $this->assertTrue($validator->validate('0034'));
        $this->assertEquals(34, $validator->value);

        $this->assertFalse($validator->validate(-7));
        $this->assertEquals(null, $validator->value);

        $validator = new validators\ExpectId(['message' => 'Неверный id']);
        $this->assertFalse($validator->validate('34key'));
        $this->assertEquals('Неверный id', $validator->error);
    }

    /**
     * Проверим магические геттеры и непосредственные методы получения валидированного значения и сообщения об ошибке.
     * Должно работать одинаково.
     *
     * Этот тест относится к супер-классу валидаторов, но его нельзя проверить напрямую. Поэтому сделано через одного
     * из наследников.
     */
    public function test_magic_getters()
    {
        $validator = new validators\ExpectId(['message' => 'Неверный id']);
        $this->assertFalse($validator->validate('34key'));

        $this->assertEquals(null, $validator->value);
        $this->assertEquals('Неверный id', $validator->error);

        $this->assertEquals(null, $validator->getValidatedValue());
        $this->assertEquals('Неверный id', $validator->getErrorMessage());
    }
}
