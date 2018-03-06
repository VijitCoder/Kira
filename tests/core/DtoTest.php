<?php
use kira\core\Dto;
use kira\exceptions\DtoException;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем супер-класс Dto
 */
class DtoTest extends TestCase
{
    /**
     * Заполнение DTO налету
     */
    public function test_fill_on_construct()
    {
        $data = [
            'id'    => 3,
            'title' => 'Hi!',
        ];
        $dto = new TestDto($data);
        $this->assertEquals(3, $dto->id);
        $this->assertEquals('Hi!', $dto->title);
        $this->assertNull($dto->status);

        $this->expectException(DtoException::class);
        $data['missed_property'] = 'Ooops!';
        new TestDto($data);
    }

    /**
     * Тест: конвертация dto в массив
     *
     * @dataProvider dtoToArrayProvider
     * @param array  $expect    что ожидаем получить
     * @param bool   $withNulls свойства со значением NULL тоже выбирать
     * @param bool   $assoc     результат - ассоцитивный массив. Иначе - с цифровыми ключами
     * @param string $error     текст ошибки, если тест провален
     * @throws DtoException
     */
    public function test_toArray(array $expect, bool $withNulls, bool $assoc, string $error)
    {
        $data = [
            'id'    => 3,
            'title' => 'Hi!',
        ];
        $dto = new TestDto($data);

        $subData = [
            'id'   => 20,
            'name' => 'Smith',
        ];
        $dto->user = new TestSubDto($subData);

        $this->assertEquals($expect, $dto->toArray($withNulls, $assoc), $error);
    }

    /**
     * Данные для теста конвертации dto в массив
     * @return array
     */
    public function dtoToArrayProvider()
    {
        $assocNoNulls = [
            'id'    => 3,
            'title' => 'Hi!',
            'user'  => [
                'id'   => 20,
                'name' => 'Smith',
            ],
        ];

        $assocWithNulls = $assocNoNulls;
        $assocWithNulls['status'] = null;

        return [
            'assoc no nulls'       => [
                'expected'  => $assocNoNulls,
                'withNulls' => false,
                'assoc'     => true,
                'error'     => 'Должен был получиться массив идентичный исходным данным',
            ],
            'assoc with nulls'     => [
                'expected'  => $assocWithNulls,
                'withNulls' => true,
                'assoc'     => true,
                'error'     => 'Ожидал ассоциативный массив включая элементы с NULL',
            ],
            'not assoc no nulls'   => [
                'expected'  => [
                    0 => 3,
                    1 => 'Hi!',
                    2 => [
                        0 => 20,
                        1 => 'Smith',
                    ],
                ],
                'withNulls' => false,
                'assoc'     => false,
                'error'     => 'Ожидал неассоциативный массив с данными, без NULL',
            ],
            'not assoc with nulls' => [
                'expected'  => [
                    0 => 3,
                    1 => 'Hi!',
                    2 => null,
                    3 => [
                        0 => 20,
                        1 => 'Smith',
                    ],
                ],
                'withNulls' => true,
                'assoc'     => false,
                'error'     => 'Должен быть неассоцитативный массив с NULL элементом',
            ],
        ];
    }
}

/**
 * Класс для тестирования супер-класса Dto
 */
class TestDto extends Dto
{
    public $id;
    public $title;
    public $status;

    /**
     * Подчиненный DTO объект
     * @var TestSubDto
     */
    public $user;

    protected $state;

    private $hiddenLevel;
}

class TestSubDto extends Dto
{
    public $id;
    public $name;
}
