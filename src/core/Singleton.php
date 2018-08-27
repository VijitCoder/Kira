<?php
namespace kira\core;

/**
 * Реализация шаблона проектирования Singleton (Одиночка) в PHP
 */
trait Singleton
{
    /**
     * Объект этого класса
     * @var self
     */
    private static $instance;

    /**
     * Получение объекта в единственном экземпляре
     *
     * @param array $args параметры в конструктор создаваемого объекта
     * @return self
     */
    public static function getInstance(...$args)
    {
        if (!self::$instance) {
            self::$instance = new static(...$args);
        }
        return self::$instance;
    }

    /**
     * Обнуляем singleton-объект.
     *
     * На практике нужно для unit-тестирования, каждый тест требует чистый объект-одиночку.
     */
    public static function reset()
    {
        self::$instance = null;
    }

    /**
     * Запрещаем любое размножение объекта. Установка private доступа к этим методам не позволит выполнить
     * соответствующие действия над объектом из клиентского кода.
     */
    private function __construct()
    {
    }

    private function __clone()
    {
    }

    private function __wakeup()
    {
    }
}
