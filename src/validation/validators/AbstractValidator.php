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
     * Дефолтные параметры валидатора. После создания объекта тут хранятся запрошенные настройки валидатора. Значение
     * может быть любого типа. Так же валидатор может быть объявлен но умышленно отключен (FALSE|NULL). Если это может
     * сломать класс-наследник, следует переопределить его конструктор, добавив обработку исключений.
     * @var mixed
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
     * Если в настройках есть сообщение, используемое при ошибке валидации, прогоняем его через переводчик. Сообщение
     * может быть задано в классе валидатора (дефолтное) или установлено при указании валидатора в контакте (кастомное).
     *
     * @param mixed $options настройки валидатора
     */
    public function __construct($options = [])
    {
        if ($options === true) {
            $options = [];
        }
        if (is_array($options)) {
            $options = array_merge($this->options, $options);
            $options['message'] = isset($options['message']) ? App::t($options['message']) : '';
        }
        $this->options = $options;
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
     * Прим: это сообщение вычисляется при создании объекта валидатора, т.е. еще до проверки. Поэтому нельзя
     * использовать вызов этого метода для выяснения, как прошла валидация. Более того, сообщение может быть пустым,
     * зависит от настроек валидатора.
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->options ? $this->options['message'] : '';
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
