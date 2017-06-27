#Трейты для unit-тестов

## Тестирование непубличных методов PHP

Трейт `kira\tests\traits\CallAsPublic`

*Если у вас возникла необходимость тестировать непубличные методы, значит скорее всего проблемы в архитектуре приложения. Хотя мне известны исключения из этого утверждения, но вы присмотритесь :) ...*

С помощью этого трейта можно тестировать непубличные методы в PHP. Обобщенный пример вызвова:

```php
use kira\tests\CallAsPublic;

class SomeTest extends TestCase
{
    use CallAsPublic;

    public function test_SomeAction() {
        $result = $this->callMethod(SUTClass::class, 'someAction', [$param1, $param2 ... $paramN]);
        $this->assert...
    }
}
```

`SUTClass` - тестируемый класс
`someAction` - непубличный метод в нем
`[$param1 ... $paramN]` - параметры метода, если ему нужны параметры. Передавать в массиве.

Рядом в подкаталоге лежит тест для проверки этого трейта - `CallAsPublicTest`. Он так же является практическим примером использования трейта.

Трейт подключается через `Composer.classmap`, несмортя на то, что его пространство имен отвечает требованиям PSR-4. Так сделано, потому что в целом каталог с тестами не загружается через Composer, но оттуда нужен этот единственный скрипт.

## Костылька для VfsStream

Трейт `kira\tests\traits\VfsGlob`

[vfsStream](https://github.com/mikey179/vfsStream) - виртуальная файловая система, используется для подмены реальной ФС в тестах с файлами/каталогами. Библиотека отличная, но в ней нельзя использовать `glob()`, см. <https://github.com/mikey179/vfsStream/issues/2>

Представленный трейт решает проблему, используя в конечном счете [php::DirectoryIterator](http://php.net/manual/en/class.directoryiterator.php).
