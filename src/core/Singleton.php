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
     * Получение объекта реестра в единственном экземпляре
     * @return Registry
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
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
