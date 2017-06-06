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

            $class = new SUTClass(' glory');

            $this->assertEquals(
                'dynamic glory',
                $this->callMethod($class, 'privateDynamic'),
                'Приватный динамический метод + объект класса + инициализация через конструктор'
            );

            $this->assertEquals(
                'welcome',
                $this->callMethod(SUTClass::class, 'init'),
                'Публичный динамический метод'
            );

            /*
            // Этот тест не пройдет. Невозможно передать параметр по ссылке.
            $var = 'some';
            $this->assertEquals(
                'success12',
                $this->callMethod($class, 'impossible', $var, 12, SUTClass::IMPOSSIBLE_VAL),
                'Метод с первым параметром-ссылкой'
            );
            */
        }
    }
}

namespace app {

    /**
     * System under test (SUT)
     */
    class SUTClass
    {
        const IMPOSSIBLE_VAL = 'success';

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

        /**
         * Этот метод невозможно протестировать из-за передачи параметра по ссылке
         * @param $var
         * @param $num
         * @param $const
         */
        private function impossible(&$var, $num, $const)
        {
            $var = $const . $num;
        }
    }
}
