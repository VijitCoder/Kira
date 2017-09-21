<?php
namespace {

    use PHPUnit\Framework\TestCase;
    use app\SUTClass;
    use kira\tests\traits\CallAsPublic;

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
                'Не удался вызов приватного динамического метода'
            );

            $class = new SUTClass;

            $this->assertEquals(
                'dynamic',
                $this->callMethod($class, 'privateDynamic'),
                'Не удался вызов приватного динамического метода из объекта класса'
            );

            $this->assertEquals(
                'static',
                $this->callMethod($class, 'privateStatic'),
                'Не удался вызов приватного статического метода'
            );

            $this->assertEquals(
                '12-word-end',
                $this->callMethod($class, 'methodWithArgs', [12, '-word-']),
                'Не удался вызов метода с параметрами'
            );

            $class = new SUTClass(' glory');

            $this->assertEquals(
                'dynamic glory',
                $this->callMethod($class, 'privateDynamic'),
                'Не удался вызов приватного динамического метода из объекта класса с инициализацией через конструктор'
            );

            $this->assertEquals(
                'welcome',
                $this->callMethod(SUTClass::class, 'init'),
                'Не удался вызов публичного динамического метода'
            );

            $var = 'some';
            $this->callMethod($class, 'impossible', [&$var, 12, SUTClass::IMPOSSIBLE_VAL]);
            $this->assertEquals('12impossible', $var, 'Не удался вызов метода с первым параметром-ссылкой');

        }
    }
}

namespace app {

    /**
     * System under test (SUT)
     */
    class SUTClass
    {
        const IMPOSSIBLE_VAL = 'impossible';

        private $state;

        public function __construct($state = '')
        {
            $this->state = $state;
        }

        private function privateDynamic(): string
        {
            return 'dynamic' . $this->state;
        }

        private static function privateStatic(): string
        {
            return 'static';
        }

        protected function methodWithArgs(string $v1, string $v2, string $v3 = 'end'): string
        {
            return $v1 . $v2 . $v3;
        }

        public function init()
        {
            return 'welcome';
        }

        private function impossible(&$var, $num, $const)
        {
            $var = $num . $const;
        }
    }
}
