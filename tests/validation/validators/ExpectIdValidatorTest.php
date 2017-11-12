<?php
use kira\validation\validators\ExpectId;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем валидатор id-ника
 */
class ExpectIdValidatorTest extends TestCase
{
    public function test_expect_id()
    {
        $validator = new ExpectId(true);

        $this->assertTrue($validator->validate('0034'));
        $this->assertEquals(34, $validator->value);

        $this->assertFalse($validator->validate(-7));

        $validator = new ExpectId(['message' => 'Неверный id']);
        $this->assertFalse($validator->validate('34key'));
        $this->assertEquals('Неверный id', $validator->error);
    }
}
