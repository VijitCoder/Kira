<?php
namespace kira\validation;

use kira\core\App;
use kira\exceptions\FormException;
use kira\utils\StringCase;
use kira\validation\validators\AbstractValidator;

/**
 * Служебный класс для модели формы. Содержит только методы валидации. Готовые данные и ошибки передаются в клиентский
 * класс и тут не хранятся.
 *
 * См. документацию, "Модель формы"
 *
 * @internal
 */
class FormValidator
{
    /**
     * Флаг результата валидации, успешно или нет
     * @var bool
     */
    protected $isValid = true;

    /**
     * Экземпляры инстациированных валидаторов. Внутренний кеш класса
     * @var array [md5() настроек валидатора => объект валидатора]
     */
    private $validatorsInstances = [];

    /**
     * Валидация одного узла дерева контракта. Рекурсия.
     *
     * Функция не предполагает прямой вызов из кода приложения. Это часть логики движка.
     *
     * Узел может быть равен нулю, только если он конечный и в контракте заявлено принять поле "как есть". Тогда пишем
     * полученные данные без валидации и выходим.
     *
     * Если узел не конечный (является веткой), тогда в нем не может быть валидаторов, есть только дочерний массив
     * узлов. Для каждого такого узла рекурсивно вызываем этот же метод.
     *
     * @param array|null $contractPart описательная часть контракта по конкретному узлу
     * @param mixed      $data         проверяемые данные. Соответствуют узлу в массиве {@see Form::$rawData}
     * @param mixed      $value        куда писать валидированное значение. Соответствует узлу в {@see Form::$values}
     * @param mixed      $error        куда писать ошибку. Соответствует узлу в {@see Form::$errors}
     * @throws FormException через магический FormValidator::__call()
     */
    public function internalValidate(&$contractPart, &$data, &$value, &$error)
    {
        if (!$contractPart) {
            $value = $data;
            return;
        }

        if (!isset($contractPart['validators'])) {
            foreach ($contractPart as $k => $cp) {
                $d = isset($data[$k]) ? $data[$k] : null;
                $this->internalValidate($cp, $d, $value[$k], $error[$k]);
            }
            return;
        }

        if ($expectArray = isset($contractPart['validators']['expect_array'])) {
            $expectArray = $contractPart['validators']['expect_array'] == true;
            unset($contractPart['validators']['expect_array']);
        }
        if (!$this->checkDataTypeForArray($data, $expectArray, $error)) {
            return;
        }

        $validators = &$contractPart['validators'];
        $this->popupRequired($validators);

        if ($expectArray) {
            foreach ($data as $k => $d) {
                $this->fireValidators($validators, $d, $value[$k], $error[$k]);
            }
        } else {
            $this->fireValidators($validators, $data, $value, $error);
        }
    }

    /**
     * Проверяем данные на соответствие ожиданиям
     *
     * Есть две ситуации: ждем массив или любой другой тип данных. Если ожидания не оправдались - ошибка валидации.
     *
     * Прим: это особый вид валидатора, поэтому его вызов отличается от остальных Функций validator*()
     *
     * @param mixed $data        проверяемые данные
     * @param bool  $expectArray ожидаем массив?
     * @param mixed $error       куда писать ошибку
     * @return bool
     */
    private function checkDataTypeForArray(&$data, $expectArray, &$error)
    {
        $isArray = is_array($data);
        if ($expectArray && !$isArray) {
            $this->isValid = false;
            $error[] = App::t('По контракту ожидаем массив данных. Получили :type.', [':type' => gettype($data)]);
            return false;
        } elseif (!$expectArray && $isArray) {
            $this->isValid = false;
            $error[] = App::t('Данные в массиве. В контракте не заявлено.');
            return false;
        }
        return true;
    }

    /**
     * Ставим валидатор "required" на первое место
     *
     * Валидатор "required" имеет особое значение: соответствующий ему метод так же проверяет, можно ли в принципе
     * пропустить  проверки, если поле пустое. Т.о. вызов валидатора "required" необходим в любом случае. Чтобы
     * не перегружать логику основного метода валидации, делаем так, чтобы "required" всегда присутствовал в списке
     * валидаторов. Если он не описан в контракте, тогда просто ставим его в FALSE.
     *
     * @param array $validators
     */
    private function popupRequired(&$validators)
    {
        if (isset($validators['required'])) {
            if (key($validators) !== 'required') {
                $tmp = ['required' => $validators['required']];
                unset($validators['required']);
                $validators = array_merge($tmp, $validators);
                unset($tmp);
            }
        } else {
            $validators = array_merge(['required' => false], $validators);
        }
    }

    /**
     * Последовательный вызов всех заявленных валидаторов для конкретного значения
     *
     * Каждый последующий валидатор должен работать с данными, обработанными предыдущим. Если какой-то валидатор
     * забракует значение, прерываем проверки.
     *
     * @param array $validators валидаторы данных
     * @param mixed $data       проверяемые данные
     * @param mixed $value      куда писать значение
     * @param mixed $error      куда писать ошибку
     */
    private function fireValidators(&$validators, &$data, &$value, &$error)
    {
        $newData = $data;
        foreach ($validators as $name => $options) {
            $validator = $this->getValidator($name, $options);

            if (!$passed = $validator->validate($newData)) {
                $error[] = $validator->error;
            }
            $value = $validator->value;

            if (!$passed) {
                break;
            }

            $newData = $value;
        }
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

        $validator = ValidationFactory::makeValidator($name, $options);
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
    public function isValid()
    {
        return $this->isValid;
    }
}
