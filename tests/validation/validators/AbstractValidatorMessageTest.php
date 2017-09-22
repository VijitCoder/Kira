<?php
use kira\validation\validators\AbstractValidator;
use PHPUnit\Framework\TestCase;

/**
 * Тестируем правильность работы с сообщениями об ошибках
 *
 * Метод находится в абстрактном супер-классе валидаторов. Поэтому для него создаем тут два валидатора-пустышки, только
 * для проверки нужной логики.
 */
class AbstractValidatorMessageTest extends TestCase
{
    /**
     * Тест валидатора с единственным дефолтным сообщением об ошибке
     */
    public function test_single_message_default()
    {
        $validator = new SingleMessageValidator(true);
        $validator->validate('whatever');
        $this->assertEquals('default message', $validator->error);
    }

    /**
     * Тест: проверить, что правильно работает установка своего сообщения об ошибке
     */
    public function test_single_message_custom()
    {
        $validator = new SingleMessageValidator(['message' => 'overriding message']);
        $validator->validate('whatever');
        $this->assertEquals('overriding message', $validator->error);

    }

    /**
     * Тест валидатора с дефолтной библиотекой сообщений
     */
    public function test_message_library_default()
    {
        $validator = new MessageLibraryValidator(true);
        $validator->validate(1);
        $this->assertEquals('default error #1', $validator->error);
        $validator->validate('whatever');
        $this->assertEquals('default error #2', $validator->error);
    }

    /**
     * Тест: проверить, что правильно работает установка своего сообщения на каждый случай ошибки
     */
    public function test_message_library_each_custom()
    {
        $validator = new MessageLibraryValidator([
            'message' => [
                'case#2' => 'my error',
            ],
        ]);
        $validator->validate(1);
        $this->assertEquals('default error #1', $validator->error);
        $validator->validate('whatever');
        $this->assertEquals('my error', $validator->error);
    }

    /**
     * Тест: проверить, что правильно работает установка своего общего сообщения на любой случай ошибки
     */
    public function test_message_library_one_custom_for_all()
    {
        $validator = new MessageLibraryValidator([
            'message' => 'my common error',
        ]);
        $validator->validate(1);
        $this->assertEquals('my common error', $validator->error);
        $validator->validate('whatever');
        $this->assertEquals('my common error', $validator->error);
    }
}

/**
 * Пример валидатора с единственным сообщением об ошибке
 */
class SingleMessageValidator extends AbstractValidator
{
    var $error = 'default message';

    public function validate($value)
    {
        return false;
    }
}

/**
 * Пример валидатора с библиотекой сообщений об ошибках
 */
class MessageLibraryValidator extends AbstractValidator
{
    protected $messageLibrary = [
        'case#1' => 'default error #1',
        'case#2' => 'default error #2',
    ];

    public function validate($value)
    {
        $this->error = $value === 1
            ? $this->messageLibrary['case#1']
            : $this->messageLibrary['case#2'];
        return false;
    }
}
