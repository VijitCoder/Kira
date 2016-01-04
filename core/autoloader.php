<?php
/**
 * Автозагрузчик стандарта PSR-4 (через пространства имен) {@link http://www.php-fig.org/psr/psr-4/ru/}
 *
 * Если грузим класс движка, тогда базовый каталог - корень сайта. Иначе выясняем, что требуется загрузить. Приложение
 * определяется префиксом в своем пространстве имен, а так же имеет свой базовый каталог. ВСе это требует стандарт PSR-4.
 *
 * Если запрошенный класс не относится к приложению, передаем управление следующему автозагрузчику. Этот подход позволит
 * подключать стронние библитеки со своими пространствами имен и своими загрузчиками классов. Главное, чтоб префиксы имен
 * не совпали с моими.
 *
 * @param string $class полностью определённое имя класса (FQN)
 * @return void
 */
function PSR4_loader($class)
{
    if (substr($class, 0, 5) == 'core\\') {
        $base_dir = ROOT_PATH;
    } else {
        $prefix = APP_NS_PREFIX;
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) {
            return;
        }
        $class = substr($class, $len);
        $base_dir = APP_PATH;
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
