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
    public function callAction();

    /**
     * Построение URL по описанию.
     *
     * @param mixed $route какое-то определение роута. Например controller/action
     * @param array $params доп.параметры для передачи в адрес. Ассоциативный массив ['имя параметра' => 'значение']
     * @return string готовый <b>относительный</b> URL
     */
    public function url($route, array $params = []);
}
