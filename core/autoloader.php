<?php
/**
 * Автозагрузка классов
 *
 * Загрузчик найдет скрипты по хвостам, от последней заглавной буквы. В случае размещения скрипта в корне
 * каталога приложения (см.PATH_APP) только первая буква имени должна быть заглавной. Проще говоря, кладите
 * свои *Controller.php в [app/controller/], *Service.php в [app/service/] и т.д. Все нетипичное можно класть
 * в [app/], называя с большой буквы.
 */
function classLoader($class)
{
    //ядерные классы движка
    $core = ['App', 'Controller', 'DbModel', 'FormModel', 'Router', 'Session'];

    //если имя класса совпадает с ядерным
    if (in_array($class, $core)) {
        require_once PATH_ROOT . "core/{$class}.php";
    //иначе по хвосту пытаемся понять, что требуется загрузить
    } else if (preg_match('~.*([A-Z][a-z]*)$~', $class, $m)) {
        //если отличия в $m нет, значит - корень приложения, иначе - подкаталог
        $path = PATH_APP . ($m[0] == $m[1] ? '' : lcfirst($m[1]));
        require_once "{$path}/{$class}.php";
    } else {
        throw new Exception('Не могу определить скрипт по имени класса');
    }
}

spl_autoload_register('classLoader');

//Глобальный перехватчик для исключений, которые не будут пойманы в контексте
set_exception_handler(['App', 'exceptionHandler']);
