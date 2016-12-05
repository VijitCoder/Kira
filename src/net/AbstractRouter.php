<?php
namespace kira\net;

/**
 * Абстрактный класс роутера
 *
 * См. документацию, "Маршрутизация"
 */
abstract class AbstractRouter
{
    /**
     * Парсинг URL и вызов action-метода в соответствующем контроллере
     * @return void
     */
    abstract public function callAction();

    /**
     * Построение URL по описанию
     * @param mixed $route  какое-то определение роута. Например controller/action
     * @param array $params доп.параметры для передачи в адрес. Ассоциативный массив ['имя параметра' => 'значение']
     * @return string готовый <b>относительный</b> URL
     */
    abstract public function url($route, array $params = []);

    /**
     * Собираем из массива GET-строку запроса, которая пишется в URL после знака вопроса
     *
     * Каждая пара ключ=значение кодируется для безопасного использования в URL-ах. Поддерживаются ключи-массивы,
     * т.е. возможно собрать строку вида: label[]=3&label[]=43&q=...
     *
     * @param array $params исходные параметры. Максимум двумерный массив
     * @return string строка типа ?p=34&str=%23%35%30&arr[]=4&arr[]=qwerty
     */
    abstract public function makeQueryString(array $params);

    /**
     * Названия контроллера, к которому обратился роутер после парсинга запроса
     * @return string
     */
    abstract public function getController();

    /**
     * Названия метода-действия, которое вызвал роутер после парсинга запроса
     * @return string
     */
    abstract public function getAction();
}
