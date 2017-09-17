<?php
use kira\validation\FormValidator;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем валидатор форм.
 */
class FromValidatorTest extends TestCase
{
    /**
     * Правильный обход контракта, добираемся до конечных узлов и тестируем валидаторы для них
     */
    //public function test_internal_validate()
    //{
    //
    //}

    /**
     * Запуск валидаторов для одного конечного узла контракта
     *
     * Здесь не важна правильность работы самих валидаторов, проверяем только их верный вызов.
     */
    /*public function test_fire_validators()
    {
        $data = 7;
        $contractPart = [
            'validators' => [
                'required' => true,

                'filter_var' => [
                    'filter'  => FILTER_CALLBACK,
                    'options' => [FromValidationTest::class, 'normalizePath'],
                ],

                'length' => ['max' => 12,],
            ],
        ];
        $value = $error = null;

        (new FormValidator)->internalValidate($contractPart, $data, $value, $error);
        $this->assertEquals(10, $value, 'Три успешных валидатора ожидаемо увеличили значение');
    }*/
}
