<?php
use kira\exceptions\FormException;
use kira\validation\validators\Required;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем валидатор "обязательное поле"
 */
class RequiredValidatorTest extends TestCase
{
    public function test_required_strict()
    {
        $validator = new Required(true);

        $this->assertTrue($validator->validate('some'));
        $this->assertTrue($validator->validate(0));
        $this->assertTrue($validator->validate(0.0));
        $this->assertTrue($validator->validate('0'));
        $this->assertTrue($validator->validate([1, 3, 5]));

        $this->assertFalse($validator->validate(''));
        $this->assertFalse($validator->validate('   '));
        $this->assertFalse($validator->validate([]));
        $this->assertFalse($validator->validate([null, null, null]));
        $this->assertFalse($validator->validate(null));

        $this->expectException(FormException::class);
        new Required(null);
    }

    public function test_required_not_strict()
    {
        $validator = new Required(['strict' => false]);

        $this->assertTrue($validator->validate('   '));
        $this->assertTrue($validator->validate([null, null, null]));

        $this->assertFalse($validator->validate([]));
        $this->assertFalse($validator->validate(''));
    }
}
