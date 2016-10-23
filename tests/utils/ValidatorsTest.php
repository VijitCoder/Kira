<?php
use PHPUnit\Framework\TestCase;
use kira\utils\Validators;

/**
 * Тестируем валидаторы
 */
class ValidatorsTest extends TestCase
{
    public function test_password()
    {
        $result = Validators::password('`12Qwe', ['min_len' => 6, 'min_comb' => 4]);
        $this->assertEquals(['value' => '`12Qwe'], $result, 'Валидный пароль');

        $result = Validators::password('`1 q', ['min_len' => 6, 'min_comb' => 4]);
        $this->assertArrayHasKey('error', $result, 'Есть ошибки');
        $this->assertEquals(3, count($result['error']), 'Все ошибки сразу');
    }

    public function test_mail()
    {
        $options = ['black_servers' => ['server.com',]];
        $result = Validators::mail('валидный@mail.com', $options);
        $this->assertEquals(['value' => 'валидный@mail.com'], $result, 'Валидный email');

        $result = Validators::mail('black@server.com', $options);
        $this->assertEquals(1, count($result['error']), 'Черный сервер email');

        $badEmails = [
            '@mail.com',
            'any@',
            'my mail.com',
            'first@level',
        ];
        foreach ($badEmails as $email) {
            $this->assertEquals(1, count($result['error']), 'Неверный email: ' . $email);
        }
    }

    public function test_date()
    {
        $result = Validators::date('22.10.2016');
        $this->assertEquals(['value' => '2016-10-22'], $result, 'Валидная дата');

        $result = Validators::date('2.10.2016');
        $this->assertEquals(1, count($result['error']), 'Неполная дата');

        $result = Validators::date('10.22.2016');
        $this->assertEquals(1, count($result['error']), 'Нереальная дата');
    }

    public function test_normalizeString()
    {
        $str = " test  one<script>console.log('hit!')</script> \\ \n \r \t \v \x0B \x00";
        $this->assertEquals(
            'test one&lt;script&gt;console.log(&#039;hit!&#039;)&lt;/script&gt;',
            Validators::normalizeString($str),
            'Дезинфекция и нормализация строки');
    }
}
