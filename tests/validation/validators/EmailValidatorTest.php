<?php
use kira\validation\validators\Email;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем валидатор email
 */
class EmailValidatorTest extends TestCase
{
    /**
     * Тест валидатора Email
     */
    public function test_email()
    {
        $validator = new Email(true);
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

        $validator = new Email([
            'black_servers' =>  ['server.com', 'dummy.ru'],
        ]);
        $this->assertFalse($validator->validate('user@server.com'));
    }
}
