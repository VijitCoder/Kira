<?php
namespace kira\validation\validators;

use kira\exceptions\FormException;

/**
 * Валидатор-посредник. Служит для вызова кастомных валидаторов, не зашитых в движке.
 *
 * Сам он ничего не валидирует. Он передает настройки и сообщение в указанный валидатор, забирает из него результат
 * и возвращает без изменений.
 *
 * Настройки external:
 * <pre>
 * $options = [
 *      'class'   => string // FQN класса-валидатора
 *      'options' => mixed  // Настройки вызываемого валидатора
 * ];
 * </pre>
 *
 * Если нужно переопределить сообщение в вызываемом валидаторе, прописывать его надо в options => [message => string].
 */
class External extends AbstractValidator
{
    /**
     * Объект внешнего валидатора
     * @var AbstractValidator
     */
    private $validator;

    /**
     * Проверяем правильность настроек и создаем кастомный валидатор
     *
     * Полностью перепределяем конструктор родителя.
     *
     * @param array|true $options настройки валидатора External
     * @throws FormException
     */
    public function __construct($options)
    {
        if (!isset($options['class'])) {
            throw new FormException('Не указан класс внешнего валидатора');
        }

        if (!is_subclass_of($options['class'], AbstractValidator::class)) {
            throw new FormException('Внешний валидатор должен быть наследником ' . AbstractValidator::class);
        }

        $class = $options['class'];
        $options = $options['options'] ?? [];
        $this->validator = new $class($options);
    }

    /**
     * Этот валидатор сам ничего не проверяет.
     * @param mixed $value
     * @return bool|void
     */
    public function validate($value)
    {
        $passed = $this->validator->validate($value);
        $this->error = $this->validator->error;
        $this->value = $this->validator->value;
        return $passed;
    }
}
