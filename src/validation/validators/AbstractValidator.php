<?php
namespace kira\validation\validators;

use kira\core\App;
use kira\exceptions\FormException;

/**
 * Абстрактный класс для реализации любого валидатора
 * @property-read mixed  $value проверенное значение
 * @property-read string $error сообщение об ошибке валидации
 */
abstract class AbstractValidator
{
    /**
     * Дефолтные параметры валидатора
     * @var array
     */
    protected $options = [];

    /**
     * Проверенное значение
     *
     * Если значение валидное, оно может так же быть приведено к нужному типу, или очищенно от лишних символов и т.п.
     * Это свойство хранит новое чистое значение.
     *
     * @var mixed
     */
    protected $value;

    /**
     * Запоминаем настройки валидатора
     *
     * Настройки могут быть переданы в любом типе данных. Обычно это массив. Сливаем их с предустановленными настройками
     * валидатора, если таковые имеются.
     *
     * Если в настройках есть сообщение, используемое при ошибке валидации, прогоняем его через переводчик.
     *
     * @param mixed $options настройки валидатора
     */
    public function __construct($options = [])
    {
        if (is_array($options) && $options) {
            $this->options = array_merge($this->options, $options);
        }
        $this->options['message'] = isset($this->options['message']) ? App::t($this->options['message']) : '';
    }

    /**
     * Магический геттер валидированного значения и сообщения об ошибке валидации
     * @param string $name имя свойства класса
     * @return mixed
     * @throws FormException
     */
    public function __get(string $name)
    {
        if ($name == 'error') {
            return $this->getErrorMessage();
        } else if ($name == 'value') {
            return $this->value;
        } else {
            throw new FormException('Неизвестное свойство класса валидатора - ' . $name);
        }

    }

    /**
     * Получение валидированного значения
     * @return mixed
     */
    public function getValidatedValue()
    {
        return $this->value;
    }

    /**
     * Получение сообщения об ошибке
     *
     * Прим: это сообщение всегда существует, независимо от процесса или результата валидации. Т.о. нельзя использовать
     * вызов этого метода для выяснения, как прошла валидация. Более того, сообщение может быть пустым, зависит от
     * настроек валидатора.
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->options['message'];
    }

    /**
     * Функция валидации
     *
     * Она никуда не сохраняет результат валидации. Все предельно просто: она только проверяет значение по заданной
     * логике и согласно настроек валидатора.
     *
     * @param mixed $value проверяемое значение
     * @return bool
     * @throws FormException
     */
    abstract public function validate($value);
}
