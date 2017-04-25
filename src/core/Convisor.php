<?php
namespace kira\core;

use kira\utils\{
    FS, ColorConsole, System
};

/**
 * Сonvisor - менеджер консольного запуска скриптов приложения
 *
 * См. документацию, "Менеджер консоли".
 */
class Convisor
{
    /**
     * Абсолютный путь к каталогу Консоли
     */
    const CONSOLE_PATH = KIRA_APP_PATH . 'console/';

    /**
     * Целевой скрипт
     * @var string
     */
    private $script;

    /**
     * Параметры для передачи в скрипт
     * @var array
     */
    private $params = [];

    /**
     * Парсим параметры, проверяем право запуска, выполняем, что требуется.
     * @param array $params параметры вызова Консоли
     */
    public function __construct(array $params)
    {
        if (!System::isConsoleInterface()) {
            exit('Ошибка. Допустим запуск только из консоли.');
        }
        $key = $this->parseParams($params);
        $this->checkAccess($key);
        if ($this->script) {
            $this->fireUp();
        } else {
            $files = [];
            $this->getScriptsList($files, '/');
            ksort($files);
            $this->drawList($files);
        }
    }

    /**
     * Разбираем параметры запуска требуемого скрипта
     *
     * Убираем нулевой элемент - это 'console.php'. Пытаемся получить ключ элемента '-k'. Есть такой - запоминаем
     * следующий за ним элемент (это ключ доступа) и убираем эти элементы из массива. Если еще что-то осталось, значит
     * нулевой элемент - имя скрипта для запуска, остальное - параметры для него. Имя скипта может быть без расширения,
     * гарантируем правильный вариант.
     *
     * В параметрах поддерживаются имена с одним/двумя минусами в начале, весь набор разбирается в ассоциативный массив.
     *
     * @param array $params параметры вызова Консоли
     * @return string ключ доступа
     */
    private function parseParams(array $params)
    {
        array_shift($params);
        $key = '';
        if (($k = array_search('-k', $params)) !== false) {
            $key = $params[++$k];
            unset($params[$k--]);
            unset($params[$k]);
        }

        if ($params) {
            $script = array_shift($params);
            $script = FS::normalizePath($script, true);
            if (!preg_match('/\.php$/i', $script)) {
                $script .= '.php';
            }
            $this->script = self::CONSOLE_PATH . $script;
        }

        if ($params) {
            $assocParams = [];
            $lastKey = '';
            foreach ($params as $p) {
                if (preg_match('/^-{1,2}[^-]/', $p)) {
                    $lastKey = $p;
                    $assocParams[$lastKey] = null;
                    continue;
                }

                if ($lastKey) {
                    $assocParams[$lastKey] = $p;
                    $lastKey = '';
                } else {
                    $assocParams[] = $p;
                }
            }
            $this->params = $assocParams;
        }

        return $key;
    }

    /**
     * Проверка права запускать скрипты через Convisor
     *
     * В режиме отладки - разрешено без вопросов, иначе - при указании правильного ключа. Количество ошибок считается.
     * Ключ и количество попыток задаются в конфиге приложения. Неудавшиеся попытки логируются, после NN попыток
     * Convisor блокируется через файл [KIRA_TEMP_PATH/console.lock]. При блокировке админу пойдет письмо.
     *
     * @param string $key ключ доступа, спарсенный из запроса
     * @return null если нет права запуска, прямо тут все и закончится. Поэтому возвращаемое значение не важно.
     */
    private function checkAccess(string $key)
    {
        if (KIRA_DEBUG) {
            return;
        }

        $lockFile = KIRA_TEMP_PATH . 'convisor.lock';

        $count = file_exists($lockFile) ? file_get_contents($lockFile) : 0;
        $tries = (int)App::conf('convisor.tries', false);
        $critical = $tries && $count >= $tries;

        if ($critical) {
            exit('Консоль заблокирована' . PHP_EOL);
        }

        $allow = (string)App::conf('convisor.key', false) == $key;
        if (!$allow) {
            $count++;
            $msg = "{$count} неудачная попытка запуска скрипта в консоли: {$this->script} "
                . implode(' ', $this->params);
            if ($critical) {
                App::logger()->add(['message' => $msg, 'type' => 'deny console', 'notify' => true]);
            } else {
                App::logger()->addTyped($msg, 'deny console');
            }
            file_put_contents($lockFile, $count);
            exit('Запуск запрещен' . PHP_EOL);
        }

        FS::deleteFile($lockFile);
    }

    /**
     * Запуск скрипта
     */
    public function fireUp()
    {
        if (!file_exists($this->script)) {
            exit('Не найден скрипт ' . $this->script . PHP_EOL);
        } elseif (!is_readable($this->script)) {
            exit('Не могу прочитать скрипт ' . $this->script . PHP_EOL);
        }

        require $this->script;

        if (!function_exists('run')) {
            exit('В запускаемом скрипте не найдена функция run()' . PHP_EOL);
        }

        call_user_func('run', $this->params);
    }

    /**
     * Получение списка доступных скриптов для запуска, включая подкаталоги. Рекурсия.
     *
     * Отсчет ведется от каталога консоли приложения.
     *
     * В результате $files = [путь от корня консоли => файлы]
     *
     * @param array  $files   одномерный массив для сборки результата
     * @param string $subPath относительный каталог для сканирования
     */
    private function getScriptsList(array &$files, string $subPath)
    {
        $path = self::CONSOLE_PATH . ($subPath == '/' ? '' : $subPath);
        $listName = $subPath;
        $consolePathLen = mb_strlen(self::CONSOLE_PATH);
        $dirList = new \DirectoryIterator($path);
        foreach ($dirList as $obj) {
            if ($obj->isDot()) {
                continue;
            }

            if ($obj->isDir()) {
                $subPath = mb_substr($obj->getPathName(), $consolePathLen);
                $this->getScriptsList($files, $subPath . '/');
                continue;
            }

            $fn = $obj->getBasename();
            if (preg_match('/\.php$/i', $fn)) {
                $files[$listName][] = basename($fn);
            }
        }
    }

    /**
     * Вывод полученного ранее списка скриптов в консоль
     *
     * Немного псевдографики и цвета для красоты
     *
     * @param array $list список скриптов, [путь от корня консоли => файлы]
     * @see https://unicode-table.com/ru/blocks/box-drawing/
     */
    private function drawList(array $list)
    {
        if (!$list) {
            return;
        }

        $cc = new ColorConsole;

        $line = $cc->getSymbol('2500');   // горизонтальная линия
        $pass = $cc->getSymbol('251c');   // граница вертикально и направо
        $corner = $cc->getSymbol('2514'); // угол вверх вправо
        $eol = PHP_EOL;

        $cc->setColor('green')
            ->addText($eol . 'Корневой каталог: ')
            ->reset()
            ->addText(self::CONSOLE_PATH . "{$eol}")
            ->draw($eol);

        if (count($list) === 1) {
            foreach (array_pop($list) as $file) {
                echo $file . $eol;
            }
            echo $eol;
        } else {
            foreach ($list as $dir => $files) {
                $cc->flush()
                    ->setStyle('bold')
                    ->addText($dir)
                    ->reset()
                    ->draw($eol);

                $last = array_pop($files);
                foreach ($files as $file) {
                    echo "{$pass}{$line} {$file}{$eol}";
                }
                echo "{$corner}{$line} {$last}{$eol}{$eol}";
            }
        }
    }
}
