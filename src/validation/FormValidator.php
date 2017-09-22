<?php
namespace kira\validation;

use kira\core\App;
use kira\exceptions\FormException;
use kira\validation\validators\AbstractValidator;

/**
 * Служебный класс для модели формы
 *
 * Это вспомогательный класс, который отвечает за сам процесс вызова валидаторов в правильной последовательности, сбор
 * результатов, останов валидации конкретного поля, если оно будет забраковано каким-то валидатором в цепи проверки.
 * Готовые данные и ошибки передаются в клиентский класс и тут не хранятся.
 *
 * См. документацию, "Модель формы"
 *
 * @internal
 */
class FormValidator
{
    /**
     * Флаг результата валидации, успешно или нет. По умолчанию - TRUE, но любая проваленная проверка изменит значение.
     * @var bool
     */
    protected $isValid = true;

    /**
     * Фабрика для получения классов валидаторов
     * @var IValidationFactory
     */
    private $factory;

    /**
     * Экземпляры инстациированных валидаторов. Внутренний кеш класса
     * @var array [название валидатора + md5() его настроек => объект валидатора]
     */
    private $validatorsInstances = [];

    /**
     * Внедряем зависимость от фабрики валидаторов
     * @param IValidationFactory $factory реализация интерфейса фабрики
     */
    public function __construct(IValidationFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Валидация одного узла дерева контракта. Рекурсия.
     *
     * Функция не предполагает прямой вызов из кода приложения. Это часть логики движка.
     *
     * Узел может быть равен NULL, только если он конечный и в контракте заявлено принять поле "как есть". Тогда пишем
     * полученные данные без валидации и выходим.
     *
     * Проверяемое значение может быть преобразовано в любом валидаторе и в таком виде будет передаваться дальше.
     *
     * Если узел не конечный (является веткой), тогда в нем не может быть валидаторов, есть только дочерний массив
     * узлов. Для каждого такого узла рекурсивно вызываем этот же метод.
     *
     * @param array|null $contractPart описательная часть контракта по конкретному узлу
     * @param mixed      $value        проверяемое значение. Соответствует узлу в {@see Form::$values}
     * @param mixed      $error        куда писать ошибку. Соответствует узлу в {@see Form::$errors}
     * @throws FormException через магический FormValidator::__call()
     */
    public function internalValidate($contractPart, &$value, &$error)
    {
        if (!$contractPart) {
            return;
        }

        if (!isset($contractPart['validators'])) {
            foreach ($contractPart as $k => $cp) {
                $this->internalValidate($cp, $value[$k], $error[$k]);
            }
            return;
        }

        if ($expectArray = isset($contractPart['validators']['expect_array'])) {
            $expectArray = (bool)$contractPart['validators']['expect_array'];
            unset($contractPart['validators']['expect_array']);
        }
        if (!$this->checkDataTypeForArray($expectArray, $value, $error)) {
            $this->isValid = false;
            return;
        }

        $validators = &$contractPart['validators'];
        $this->popupRequired($validators);

        if ($expectArray) {
            foreach (array_keys($value) as $k) {
                $this->isValid = $this->fireValidators($validators, $value[$k], $error[$k]) && $this->isValid;
            }
        } else {
            $this->isValid = $this->fireValidators($validators, $value, $error) && $this->isValid;
        }
    }

    /**
     * Проверяем данные на соответствие ожиданиям
     *
     * Есть две ситуации: ждем массив или любой другой тип данных. Если ожидания не оправдались - ошибка валидации.
     *
     * Прим: это особый вид валидатора, поэтому его вызов отличается от остальных классов-валидаторов.
     *
     * @param bool  $expectArray ожидаем массив?
     * @param mixed $value       проверяемые данные
     * @param mixed $error       куда писать ошибку
     * @return bool
     */
    private function checkDataTypeForArray(bool $expectArray, &$value, &$error): bool
    {
        $passed = true;
        $isArray = is_array($value);
        if ($expectArray && !$isArray) {
            $error[] = App::t('Ожидаем массив данных. Получили :type.', [':type' => gettype($value)]);
            $passed = false;
        } elseif (!$expectArray && $isArray) {
            $error[] = App::t('Значение - массив, но ожидаем скалярный тип данных.');
            $passed = false;
        }
        return $passed;
    }

    /**
     * Ставим валидатор "required" на первое место
     * @param array $validators
     */
    private function popupRequired(array &$validators)
    {
        if (!isset($validators['required'])) {
            return;
        }

        if (key($validators) !== 'required') {
            $atFirstPlace = ['required' => $validators['required']];
            unset($validators['required']);
            $validators = array_merge($atFirstPlace, $validators);
            unset($atFirstPlace);
        }
    }

    /**
     * Последовательный вызов всех заявленных валидаторов для конкретного значения
     *
     * Каждый последующий валидатор должен работать с данными, обработанными предыдущим. Если какой-то валидатор
     * забракует значение, прерываем проверки, а в $value останется значение в последнем успешном состоянии.
     *
     * Если значение отсутствует (NULL), нет смысла вызывать для него валидаторы. Оно будет считаться правильным, если
     * не является обязательным.
     *
     * @param array $validators валидаторы данных
     * @param mixed $value      проверяемые данные
     * @param mixed $error      куда писать ошибку
     * @return bool
     */
    private function fireValidators(array $validators, &$value, &$error): bool
    {
        $passed = true;
        foreach ($validators as $name => $options) {
            if (is_null($value) && $name != 'required') {
                break;
            }

            $validator = $this->getValidator($name, $options);

            if (!$passed = $validator->validate($value)) {
                $error[] = $validator->error;
                break;
            }

            $value = $validator->value;
        }
        return $passed;
    }

    /**
     * Получение экземпляра класса валидатора
     * @param string $name    название валидатора, используемое в контракте. Почти совпадает с именем класса.
     * @param mixed  $options параметры, передаваемые в класс-валидатор
     * @return AbstractValidator наследник супер-класса валидаторов
     * @throws FormException
     */
    private function getValidator(string $name, $options): AbstractValidator
    {
        $cacheKey = $name . '_' . serialize($options);
        if (isset($this->validatorsInstances[$cacheKey])) {
            return $this->validatorsInstances[$cacheKey];
        }

        $validator = $this->factory->makeValidator($name, $options);
        if (is_null($validator)) {
            throw new FormException("Не найден класс валидатора для '{$name}'" . PHP_EOL
                . 'Проверьте правильность контракта формы.');
        }

        $this->validatorsInstances[$cacheKey] = $validator;

        return $validator;
    }

    /**
     * Результат проведенной валидации. Геттер.
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }
}
