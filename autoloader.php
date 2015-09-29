<?php
function classLoader($class)
{
    if (preg_match('~Controller|Service|Model|Form|Helper$~', $class, $match)) {
        //Не проверяем наличие файла, это дорогая операция, а классов много. Допустимые контроллеры
        //валидируются роутером, остальные файлы должны быть. Иначе позволим PHP ругнуться.
        require_once PATH_ROOT . lcfirst($match[0]) . "/{$class}.php";
    } else {
        require_once PATH_ROOT . "core/{$class}.php";

        //Если файла не будет, появится FATAL ERROR. В идеале ошибку нужно где-то ее поймать.
        //Обработчик ошибок не реализован. Делаю минимум по задаче.
    }
}

spl_autoload_register('classLoader');
