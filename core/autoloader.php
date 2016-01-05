<?php
/**
 * Автозагрузчик почти по стандарту PSR-4 (через пространства имен) {@link http://www.php-fig.org/psr/psr-4/ru/}
 *
 * Если начальная часть FQN класса совпадает с префиксом пространства имен приложения, тогда базовым каталогом считать
 * каталог приложения. Иначе - база = корень сайта. Пространства имен должны совпадать с иерархией каталогов и файлов,
 * включая регистр букв.
 *
 * Если запрошенный класс не найден, передаем управление следующему автозагрузчику. Этот подход позволит подключать
 * стронние библитеки со своими пространствами имен и своими загрузчиками классов. Главное, чтоб префиксы имен
 * не совпали с моими.
 *
 * Загрузчик не найдет файл интерфейсов, он не по стандарту. Подключен отдельно.
 *
 * @param string $class полностью определённое имя класса (FQN)
 * @return void
 */
function PSR4_loader($class)
{
    $prefix = APP_NS_PREFIX;
    $len = strlen($prefix);
    if (strncmp($class, $prefix, $len) === 0) {
        $class = substr($class, $len);
        $base_dir = APP_PATH;
    } else {
        $base_dir = ROOT_PATH;
    }

    $file = $base_dir . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
}

/**
 * @DEPRECATED
 *
 * Не стандартизированный автозагрузчик классов.
 *
 * Загрузчик найдет скрипты по хвостам, от последней заглавной буквы. В случае размещения скрипта в корне
 * каталога приложения (см.APP_PATH) только первая буква имени должна быть заглавной. Проще говоря, кладите
 * свои *Controller.php в [app/controller/], *Service.php в [app/service/] и т.д. Все нетипичное можно класть
 * в [app/], называя с большой буквы.
 *
 * @param $class
 */
function customLoader($class)
{
    $core = ['App', 'Controller', 'Db', 'Form', 'Router', 'Session', 'Validator'];

    if (in_array($class, $core)) {
        require_once ROOT_PATH . "core/{$class}.php";
    } else if (preg_match('~.*([A-Z][a-z]*)$~', $class, $m)) {
        $path = APP_PATH . ($m[0] == $m[1] ? '' : lcfirst($m[1]));
        require_once "{$path}/{$class}.php";
    }
}

require_once 'Interfaces.php';
spl_autoload_register('PSR4_loader');

//Глобальный перехватчик для исключений, которые не будут пойманы в контексте
set_exception_handler(['core\App', 'exceptionHandler']);
