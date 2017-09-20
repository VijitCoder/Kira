<?php
use kira\exceptions\FormException;
use kira\validation\validators;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем валидаторы: Bounds, Length
 */
class BoundsAndLengthTest extends TestCase
{
    public function test_bounds()
    {
        $validator = new validators\Bounds([
            'min' => 5,
            'max' => 10,
        ]);

        $this->assertTrue($validator->validate(5));
        $this->assertTrue($validator->validate(7));
        $this->assertTrue($validator->validate(10));

        $this->assertFalse($validator->validate(3));
        $this->assertFalse($validator->validate(12));

        $this->expectException(FormException::class);
        $validator->validate('one');
    }

    public function test_bounds_no_limit()
    {
        $validator = new validators\Bounds([
            'min' => 5,
        ]);

        $this->assertTrue($validator->validate(10));
        $this->assertTrue($validator->validate(100043));
        $this->assertFalse($validator->validate(3));

        $validator = new validators\Bounds([
            'max' => 10,
        ]);

        $this->assertTrue($validator->validate(-10005));
        $this->assertTrue($validator->validate(10));
        $this->assertFalse($validator->validate(12));

        // Валидация границ с такими настройками не имеет смысла, но не должна падать.
        $this->assertTrue((new validators\Bounds(false))->validate(234));
        $this->assertTrue((new validators\Bounds)->validate(100500));
    }


    public function test_Length()
    {
        $validator = new validators\Length([
            'min' => 5,
            'max' => 10,
        ]);

        $this->assertTrue($validator->validate('plate'));      // 5 символов
        $this->assertTrue($validator->validate('тарелка'));    // 7 символов
        $this->assertTrue($validator->validate('supervisor')); // 10 символов

        $this->assertFalse($validator->validate('нет'));
        $this->assertFalse($validator->validate('tyrannosaur Rex')); // 15 символов

        $this->expectException(FormException::class);
        $validator->validate(123457);
    }

    public function test_length_no_limit()
    {
        $validator = new validators\Length([
            'min' => 5,
        ]);

        $this->assertTrue($validator->validate('tyrannosaur Rex')); // 15 символов
        $this->assertFalse($validator->validate('нет'));

        $validator = new validators\Length([
            'max' => 10,
        ]);

        $this->assertTrue($validator->validate('supervisor'));       // 10 символов
        $this->assertFalse($validator->validate('tyrannosaur Rex')); // 15 символов

        // Валидация границ с такими настройками не имеет смысла, но не должна падать.
        $this->assertTrue((new validators\Length(false))->validate('some'));
        $this->assertTrue((new validators\Length)->validate('very long long string'));
    }
}
