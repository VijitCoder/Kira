<?php
use kira\validation\validators;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем валидаторы, для которых нет отдельных тестов
 */
class OtherValidatorsTest extends TestCase
{
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

    public function test_expect_id()
    {
        $validator = new validators\ExpectId;

        $this->assertTrue($validator->validate('0034'));
        $this->assertEquals(34, $validator->value);

        $this->assertFalse($validator->validate(-7));

        $validator = new validators\ExpectId(['message' => 'Неверный id']);
        $this->assertFalse($validator->validate('34key'));
        $this->assertEquals('Неверный id', $validator->error);
    }

    public function test_required()
    {
        $validator = new validators\Required;

        $this->assertTrue($validator->validate('some'));
        $this->assertTrue($validator->validate(0));
        $this->assertTrue($validator->validate(0.0));
        $this->assertTrue($validator->validate('0'));

        // Отключаем валидатор. Тогда проверка должна быть всегда пройдена.
        $validator = new validators\Required(false);

        $this->assertTrue($validator->validate('0'));
        $this->assertTrue($validator->validate(null));
    }

    public function test_password()
    {
        $validator = new validators\Password([
            'min_length' => 6,
            'min_combination' => 4,
            'glue' => ' | '
        ]);

        $this->assertTrue($validator->validate('`12Qwe'));

        $this->assertFalse($validator->validate('`1 q'));
        $errors = explode(' | ', $validator->error);
        $this->assertEquals(3, count($errors));
    }
}
