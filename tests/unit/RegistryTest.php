<?php

use PHPUnit\Framework\TestCase;
use kira\utils\Registry;
use kira\exceptions\WriteException;
use kira\exceptions\ReadException;

/**
 * Тестируем реестр движка
 */
class RegistryTest extends TestCase
{
    /**
     * Тест магического сеттера и геттера
     */
    public function testMagicMethods()
    {
        $reg = Registry::getInstance();

        $reg->test = 'some';
        $this->assertEquals('some', $reg->test, 'Получение значения через геттер');
        $reg->delete('test');
        $this->assertNull($reg->test, 'Получение значения через геттер после его удаления');

        $this->assertNull($reg->nonExists, 'Получение несуществующего значения через геттер');
    }

    /**
     * Тест нормальных get(), set()
     */
    public function testNormalMethods()
    {
        $reg = Registry::getInstance();

        // Обычная ситуация. Записать, проверить, прочитать
        $reg->set('test2', 'yeap!');
        $this->assertEquals('yeap!', $reg->get('test2'), 'Получение значения через обычный геттер');
        $this->assertTrue($reg->isExists('test2'), 'Значение есть в реестре');

        try {
            $reg->set('test2', 'deny overwrite');
            $this->fail('Исключение не проброшено');
        } catch (\Exception $e) {
            $this->assertInstanceOf(WriteException::class, $e, 'Запрет на перезапись. Ожидаем исключение');
        }

        // Перезапись разрешена
        $reg->set('test2', 'allow overwrite', true);
        $this->assertEquals('allow overwrite', $reg->get('test2'), 'Получение перезаписанного значения');

        try {
            $reg->get('nonExists');
            $this->fail('Исключение не проброшено');
        } catch (\Exception $e) {
            $this->assertInstanceOf(ReadException::class, $e, 'Чтение несуществующего значения. Ожидаем исключение');
        }

        // Чтение с подавлением ошибки
        $value = $reg->get('nonExists', true);
        $this->assertNull($value, 'Получение несуществующего значения');
    }

    /**
     * Тест сериализации/десериализации
     */
    public function testSaveRestore()
    {
        $reg = Registry::getInstance()->drop();

        $obj = new \stdClass;
        $obj->publicVar = 'some value';

        $reg->set('savedObject', $obj)->set('some', 'single value')->set('arr', [1, 3]);

        $serializedValue = serialize($reg);
        $reg = unserialize($serializedValue);

        $serializeExpect = 'C:19:"kira\utils\Registry":141:{a:3:{s:11:"savedObject";O:8:"stdClass":1:{s:9:"publicVar";'
            . 's:10:"some value";}s:4:"some";s:12:"single value";s:3:"arr";a:2:{i:0;i:1;i:1;i:3;}}}';

        $this->assertEquals($serializeExpect, $serializedValue, 'Сериализованный реестр');

        $this->assertEquals('some value', $reg->savedObject->publicVar, 'Чтение свойства класса после десериализации');
        $this->assertEquals('single value', $reg->some, 'Чтение простого значения после десериализации');
        $this->assertEquals([1, 3], $reg->arr, 'Чтение массива после десериализации');
    }
}
