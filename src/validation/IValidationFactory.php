<?php
namespace kira\validation;

/**
 * Интерфейс. Фабрика получения экземпляров классов, задействованных в валидации. Служит для подмены зависимостей
 * в unit-тестировании.
 */
interface IValidationFactory
{
    /**
     * Получаем экземпляр класса валидатора по названию валидатора
     * @param string $name    название валидатора. Почти совпадает с именем класса.
     * @param mixed  $options параметры, передаваемые в класс-валидатор
     * @return validators\AbstractValidator|null
     */
    public function makeValidator(string $name, $options);
}
