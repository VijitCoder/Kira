<?php
namespace {

    use PHPUnit\Framework\TestCase;
    use app\SUTClass;

    /**
     * Тестируем метод, позволяющий тестировать непубличные методы классов
     */
    class CallAsPublicTest extends TestCase
    {
        use CallAsPublic;

        public function test_callMethod()
        {
            $this->assertEquals(
                'dynamic',
                $this->callMethod(SUTClass::class, 'privateDynamic'),
                'Приватный динамический метод'
            );

            $class = new SUTClass;

            $this->assertEquals(
                'dynamic',
                $this->callMethod($class, 'privateDynamic'),
                'Приватный динамический метод + объект класса'
            );

            $this->assertEquals('static', $this->callMethod($class, 'privateStatic'), 'Приватный статический метод');

            $this->assertEquals(
                '12-word-end',
                $this->callMethod($class, 'methodWithArgs', 12, '-word-'),
                'Вызов метода с параметрами'
            );

        }
    }
}

namespace app {

    /**
     * System under test (SUT)
     */
    class SUTClass
    {
        private function privateDynamic(): string
        {
            return 'dynamic';
        }

        private static function privateStatic(): string
        {
            return 'static';
        }

        protected function methodWithArgs(string $v1, string $v2, string $v3 = 'end'): string
        {
            return $v1 . $v2 . $v3;
        }
    }
}
