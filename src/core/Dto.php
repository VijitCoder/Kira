<?php
namespace kira\core;

use kira\exceptions\DtoException;

/**
 * Супер-класс Dto объектов
 */
class Dto
{
    /**
     * Конструктор
     *
     * Можно заполнить dto переданными данными. Если в данных будет что-то лишнее, пробросим исключение.
     *
     * @param array $rawData данные для заполнения свойств DTO-объекта
     * @throws DtoException
     */
    public function __construct(array $rawData = null)
    {
        if ($rawData) {
            foreach ($rawData as $name => $value) {
                if (!property_exists($this, $name)) {
                    $this->throwPropertyNotFoundException($name);
                }
                $this->$name = $value;
            }
        }
    }

    /**
     * Ограничиваем поведение PHP односительно магических свойств в DTO классах.
     *
     * Если свойство явно не описано в DTO, его нельзя назначить.
     *
     * @param string $name  имя свойства
     * @param mixed  $value присваемое значение
     * @throws DtoException
     */
    public function __set(string $name, $value)
    {
        $this->throwPropertyNotFoundException($name);

    }

    /**
     * Ограничиваем поведение PHP односительно магических свойств в DTO классах.
     *
     * Ничего нельзя получить неявно.
     *
     * @param string $name property name
     * @return mixed|void
     * @throws DtoException
     */
    public function __get(string $name)
    {
        $this->throwPropertyNotFoundException($name);
    }

    /**
     * Обработка ситуации, когда не найдено свойство класса
     *
     * @param string $name
     * @throws DtoException
     */
    private function throwPropertyNotFoundException(string $name)
    {
        throw new DtoException("Свойство '$name' не найдено в классе '" . get_class($this) . "'");
    }

    /**
     * Конвертация dto-объекта в массив
     *
     * Это метод ограниченного действия. Он каскадом вызовет конвертацию вложенных DTO, но оставит без изменений любые
     * другие типы данных.
     *
     * @param bool $withNulls свойства со значением NULL тоже выбирать
     * @param bool $assoc     результат - ассоцитивный массив. Иначе - с цифровыми ключами
     * @return array
     * @throws DtoException
     */
    public function toArray(bool $withNulls = false, bool $assoc = true): array
    {
        try {
            $reflect = new \ReflectionClass($this);
        } catch (\ReflectionException $e) {
            throw new DtoException('Не удалась конвертация DTO в массив', 0, $e);
        }
        $props = $reflect->getProperties(\ReflectionProperty::IS_PUBLIC);

        $result = [];
        foreach ($props as $prop) {
            $key = $prop->getName();
            if (!$withNulls && is_null($this->$key)) {
                continue;
            }
            $value = $this->$key;

            if ($value instanceof Dto) {
                $value = $value->toArray($withNulls, $assoc);
            }

            $result[$key] = $value;
        }

        return $assoc ? $result : array_values($result);
    }
}
