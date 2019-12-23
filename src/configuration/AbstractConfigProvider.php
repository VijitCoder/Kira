<?php
namespace kira\configuration;

/**
 * Поставщик конфигурации.
 *
 * Конфигурацию приложения в теории можно описать в php-файлах, yaml, json, DB.. Правда в случае использования баз
 * данных сложно описать их самих. Но тем не менее, это возможно. Данный абстакт описывает поведение любого поставщика
 * конфигурации.
 */
abstract class AbstractConfigProvider
{
    /**
     * Загрузить конфигурацию. Результатом должен быть ассоциативный массив конфигурации, готовый к использованию.
     *
     * @return array
     */
    abstract public function loadConfiguration(): array;

    /**
     * Конфигурация загружена полностью?
     *
     * Возможна пошаговая загрузка конфигурации с ее немедленным использованием. Метод-флаг сообщает, когда конфиг
     * загружен полностью.
     *
     * @return bool
     */
    public function isFullyLoaded(): bool
    {
        return true;
    }
}