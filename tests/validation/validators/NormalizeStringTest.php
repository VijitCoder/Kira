<?php
use kira\validation\validators\NormalizeString;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем нормализацию строки
 */
class NormalizeStringTest extends TestCase
{
    /**
     * Тест нормализации строки, с удалением переносов строк
     */
    public function test_normalize_string()
    {
        $validator = new NormalizeString(true);
        $validator->validate("Это\tпервый\tтест  \n\r<script>console.log('hit!')</script> \\ \n \r \v \x0B \x00");
        $this->assertEquals(
            'Это первый тест &lt;script&gt;console.log(&#039;hit!&#039;)&lt;/script&gt;',
            $validator->value
        );
    }

    public function test_keep_line_breaks()
    {
        $validator = new NormalizeString(['keep_line_breaks' => true]);
        $validator->validate("Коммент  \t\n\r в несколько  \nстрок\v.");
        $this->assertEquals(
            "Коммент \n\r в несколько \nстрок.",
            $validator->value
        );
    }
}
