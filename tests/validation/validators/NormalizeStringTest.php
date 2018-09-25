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
            'Это первый тест &lt;script&gt;console.log(&apos;hit!&apos;)&lt;/script&gt;',
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

    public function test_strip_tags()
    {
        $validator = new NormalizeString(['strip_tags' => true]);
        $validator->validate('some & \'<script>console.log("hit!")</script>\' tail');
        $this->assertEquals('some &amp; &apos;console.log(&quot;hit!&quot;)&apos; tail', $validator->value);
    }

    /**
     * Проверяем, как применяются флаги для php::htmlspecialchars()
     */
    public function test_ent_flags()
    {
        $string = 'some "text" here &';

        // Флаги по умолчанию преобразуют кавычки
        $validator = new NormalizeString(true);
        $validator->validate($string);
        $this->assertEquals('some &quot;text&quot; here &amp;', $validator->value);

        // Теперь просим оставить кавычки, как есть
        $validator = new NormalizeString(['ent_flags' => ENT_NOQUOTES | ENT_HTML401]);
        $validator->validate($string);
        $this->assertEquals('some "text" here &amp;', $validator->value);
    }

    /**
     * Конкретно это слово указало на баг с пропущенным модификатором "u" (юникод). Не знаю, почему именно так,
     * в валидаторе нет регулярок, требующих учитывать юникод. Модификатор в выражения добавил, а тест пусть будет.
     */
    public function test_unicode_bug()
    {
        $validator = new NormalizeString(['strip_tags' => true]);
        $validator->validate('сеРВЕр');
        $this->assertEquals('сеРВЕр', $validator->value);
    }
}
