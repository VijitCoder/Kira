<?php
namespace kira\validation\validators;

use kira\core\App;
use kira\exceptions\FormException;

/**
 * Проверка необходимого значения на существование
 *
 * Валидатор с особым поведением. Он вызывается раньше любого другого валидатора независимо от их очередности,
 * т.к. в нем выясняем, можно ли пропустить остальные проверки.
 */
class Required extends AbstractValidator
{
    /**
     * Проверка необходимого значения на существование
     *
     * Если поле пустое, нет смысла его валидировать. При этом нужно проверить, задана ли в контакте его обязательность.
     * Внутри функции пишем значение поля, возможно клиенту важно, какая именно пустота (строка, массив и т.д.).
     * Пишем ошибку валидатора "required", если она есть.
     *
     * Прим: разделение логики этого метода повлечет за собой сильное усложнение основного метода валидации.
     *
     * @param mixed $desc  описание валидатора. Ожидаем массив или TRUE
     * @param mixed $data  проверяемые данные
     * @param mixed $value куда писать значение
     * @param mixed $error куда писать ошибку
     * @return bool
     */
    public function validate(&$desc, &$data, &$value, &$error)
    {
        if (!($desc === true || is_array($desc))) {
            throw new FormException('Неправильное описание валидатора. Ожидаем массив или TRUE');
        }

        $passed = !empty($data);

        if (!$passed) {
            $value = $data;

            if ($desc) {
                $this->isValid = false;
                $error[] = isset($desc['message']) ? App::t($desc['message']) : App::t('Поле должно быть заполнено');
            }
        } else {
            $value = $data;
        }

        return $passed;
    }
}
