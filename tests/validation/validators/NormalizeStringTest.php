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
        $validator->validate(" test  one<script>console.log('hit!')</script> \\ \n \r \t \v \x0B \x00");
        $this->assertEquals('test one&lt;script&gt;console.log(&#039;hit!&#039;)&lt;/script&gt;', $validator->value);
    }

    public function test_keep_line_breaks()
    {
        $validator = new NormalizeString(['keep_line_breaks' => true]);
        $validator->validate(" test  one<script>console.log('hit!')</script>\nstring #2\t");
        $this->assertEquals(
            "test one&lt;script&gt;console.log(&#039;hit!&#039;)&lt;/script&gt;\nstring #2",
            $validator->value
        );
    }
}
