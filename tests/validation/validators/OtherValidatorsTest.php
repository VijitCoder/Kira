<?php
use kira\exceptions\FormException;
use kira\validation\validators;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем валидаторы, для которых нет отдельных тестов
 */
class OtherValidatorsTest extends TestCase
{
    public function test_expect_id()
    {
        $validator = new validators\ExpectId(true);

        $this->assertTrue($validator->validate('0034'));
        $this->assertEquals(34, $validator->value);

        $this->assertFalse($validator->validate(-7));

        $validator = new validators\ExpectId(['message' => 'Неверный id']);
        $this->assertFalse($validator->validate('34key'));
        $this->assertEquals('Неверный id', $validator->error);
    }

    public function test_required()
    {
        $validator = new validators\Required(true);

        $this->assertTrue($validator->validate('some'));
        $this->assertTrue($validator->validate(0));
        $this->assertTrue($validator->validate(0.0));
        $this->assertTrue($validator->validate('0'));
        $this->assertTrue($validator->validate([1, 3, 5]));

        $this->assertFalse($validator->validate([]));
        $this->assertFalse($validator->validate(''));
        $this->assertFalse($validator->validate(null));

        $this->expectException(FormException::class);
        new validators\Required(null);
    }

    public function test_password()
    {
        $validator = new validators\Password([
            'min_length' => 6,
            'min_combination' => 4,
            'glue' => ' | '
        ]);

        $this->assertTrue($validator->validate('`12Qwe'));
        $this->assertTrue($validator->validate('[`12Qwe]>', 'Пароль с не учитываемыми символами оказался невалидным'));

        $this->assertFalse($validator->validate('`1 q'));
        $errors = explode(' | ', $validator->error);
        $this->assertEquals(2, count($errors));

        $this->assertFalse($validator->validate('[12Qwe]>',
            'Пароль с не учитываемыми символами увеличил счетчик комбинаций'));
    }

    /**
     * Тест валидатора Email
     */
    public function test_email()
    {
        $validator = new validators\Email(true);
        $this->assertTrue($validator->validate('user@mail.com'));

        $badEmails = [
            '@mail.com',
            'any@',
            'my mail.com',
            'first@level',
        ];
        foreach ($badEmails as $email) {
            $this->assertFalse($validator->validate($email));
        }

        $validator = new validators\Email([
            'black_servers' =>  ['server.com', 'dummy.ru'],
        ]);
        $this->assertFalse($validator->validate('user@server.com'));
    }

    /**
     * Тест нормализации строки
     */
    public function test_normalize_string()
    {
        $str = " test  one<script>console.log('hit!')</script> \\ \n \r \t \v \x0B \x00";
        $validator = new validators\NormalizeString(true);
        $validator->validate($str);
        $this->assertEquals('test one&lt;script&gt;console.log(&#039;hit!&#039;)&lt;/script&gt;', $validator->value);
    }
}

