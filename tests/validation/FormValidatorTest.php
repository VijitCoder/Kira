<?php
use kira\tests\traits\CallAsPublic;
use kira\validation\FormValidator;
use kira\validation\ValidatorFactory;
use kira\validation\validators\AbstractValidator;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем валидатор формы
 */
class FromValidatorTest extends TestCase
{
    use CallAsPublic;

    /**
     * Тест: Ставим валидатор "required" на первое место
     */
    public function test_popupRequired()
    {
        $factory = $this->createMock(ValidatorFactory::class);
        $formValidator = new FormValidator($factory);

        $validators = [
            'one'      => true,
            'required' => ['message' => 'some'],
            'two'      => [],
        ];

        $this->callMethod($formValidator, 'popupRequired', [&$validators]);
        $expect = [
            'required' => ['message' => 'some'],
            'one'      => true,
            'two'      => [],
        ];
        $this->assertEquals($expect, $validators, 'Валидатор "required" не поднят на первое место');
    }

    /**
     * Проверяем последовательный вызов всех заявленных валидаторов для конкретного значения.
     *
     * В определенном месте значение станет невалидным, после чего цепочка вызовов должна прекратиться.
     */
    public function test_fireValidators()
    {
        $factory = $this->createMock(ValidatorFactory::class);
        $factory->method('makeValidator')
            ->willReturn(new FalseIf20Validator(true));

        $formValidator = new FormValidator($factory);

        $value = 18;
        $error = null;
        $validators = [
            'required'      => true,
            'limits'        => [],
            'will_not_call' => true, // допустим, это какой-то валидатор, который уже не будет вызван
            'expect_id'     => true, // и этот тоже
        ];

        $isPassed = $this->callMethod($formValidator, 'fireValidators', [$validators, &$value, &$error]);

        $this->assertEquals(20, $value, 'Последнее валидное значение не совпадает с ожиданием');
        $this->assertFalse($isPassed);
    }

    /**
     * Правильный обход контракта, добираемся до конечных узлов и валидируем их. Сами валидаторы будут подменены,
     * тут проверяем, что обход дерева контракта работает верно.
     *
     * Заглушка для любого используемого валидатора всегда вернет TRUE и увеличит проверяемое значение на единицу.
     * Здесь проверяем, что к каждому полю применяется валидация и значения увеличиваются согласно количеству вызванных
     * валидаторов.
     *
     * Этот тест-метод должен быть последним, т.к. в нем вызываются методы, протестированные выше. Если их тесты будут
     * падать, проще найти ошибку, когда клиентский метод тестируется в конце.
     */
    public function test_internalValidate()
    {
        $factory = $this->createMock(ValidatorFactory::class);
        $factory->method('makeValidator')
            ->willReturn(new AlwaysTrueValidator(true));

        $formValidator = new FormValidator($factory);

        $contract = [
            'field1'     => [
                'validators' => [
                    'required'  => true,
                    'limits'    => [],
                    'expect_id' => true,
                ],
            ],
            'fields-set' => [
                'validators' => [
                    'expect_array' => true,
                    'limits'       => ['max' => 12,],
                ],
            ],
        ];

        $value = [
            'field1'     => 30,
            'fields-set' => [
                'c1' => 12,
                'c3' => 2,
            ],
        ];

        $error = null;
        $formValidator->validate($contract, $value, $error);

        $expect = [
            'field1'     => 33,
            'fields-set' => [
                'c1' => 13,
                'c3' => 3,
            ],
        ];

        $this->assertTrue($formValidator->isValid());
        $this->assertEquals($expect, $value, 'Не получили ожидаемые валидированные значения');
    }

    # --- Никаких методов ниже этой линии. Причина: test_internalValidate() должен быть последним тестом -----
}

/**
 * Класс-валидатор, который всегда вернет TRUE и увеличит проверяемое значение на единицу
 * Это нужно для подмены любого класса валидатора
 */
class AlwaysTrueValidator extends AbstractValidator
{
    public function validate($value)
    {
        $this->value = ++$value;
        return true;
    }
}

/**
 * Класс-валидатор, который всегда FALSE если проверяемое значение равно 20, иначе возвращает TRUE и увеличивает
 * проверяемое значение на единицу.
 * Это нужно для имитации неверного значения для какого-нибудь валидатора.
 */
class FalseIf20Validator extends AbstractValidator
{
    public function validate($value)
    {
        if ($value != 20) {
            $this->value = ++$value;
            return true;
        }
        return false;
    }
}
