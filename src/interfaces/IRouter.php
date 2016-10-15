<?php
namespace kira\interfaces;

/**
 * Интерфейс роутера.
 *
 * В конструктор роутера передается экземпляр автозагрузчика Composer. В роутере можно использовать его API.
 */
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
     * @param mixed $route  какое-то определение роута. Например controller/action
     * @param array $params доп.параметры для передачи в адрес. Ассоциативный массив ['имя параметра' => 'значение']
     * @return string готовый <b>относительный</b> URL
     */
    public function url($route, array $params = []);

    /**
     * Названия контроллера, к которому обратился роутер после парсинга запроса
     * @return string
     */
    public function getController();

    /**
     * Названия метода-действия, которое вызвал роутер после парсинга запроса
     * @return string
     */
    public function getAction();
}
