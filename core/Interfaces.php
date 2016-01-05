<?php
/**
 * Интерфейсы. Все в одном месте. Скрипт загружается отдельно в autoloader.php, т.к. функция автозагрузчика его
 * не найдет в таком виде, а создавать кучу файлов под интерфейсы нехорошо.
 */

namespace core;

interface IRouter
{
    /**
     * Парсинг URL и вызов action-метода в соответствующем контроллере.
     * @return void
     */
    public static function callAction();

    /**
     * Построение URL по параметрам.
     *
     * @param mixed $route какое-то определение роута. Например controller/action
     * @param array $params параметры для левой части роута
     * @return string готовый <b>относительный</b> URL
     */
    public static function url($route, array $params = []);
}
