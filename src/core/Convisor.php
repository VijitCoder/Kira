<?php
namespace kira\core;

use kira\utils\FS;

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
        if (!isConsoleInterface()) {
            exit('Ошибка. Допустим запуск только из консоли.');
        }
        $key = $this->parseParams($params);
        $this->checkAccess($key);
        $this->script ? $this->fireUp() : $this->scriptsList();
    }

    /**
     * Разбираем параметры запуска требуемого скрипта
     *
     * Убираем нулевой элемент - это 'console.php'. Пытаемся получить ключ элемента '-k'. Есть такой - запоминаем
     * следующий за ним элемент (это ключ доступа) и убираем эти элементы из массива. Если еще что-то осталось, значит
     * нулевой элемент - имя скрипта для запуска, остальное - параметры для него. Имя скипта может быть без расширения,
     * гарантируем правильный вариант.
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
            $this->script = self::CONSOLE_PATH . basename($script, '.php') . '.php';
        }

        $this->params = $params;

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
        $critical =  $tries && $count >= $tries;

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
            exit('Не найден скрипт ' .  $this->script . PHP_EOL);
        } elseif (!is_readable($this->script)) {
            exit('Не могу прочитать скрипт ' .  $this->script . PHP_EOL);
        }

        require $this->script;

        if (!function_exists('run')) {
            exit('В запускаемом скрипте не найдена функция run()' . PHP_EOL);
        }

        call_user_func('run', $this->params);
    }

    /**
     * Получение списка доступных скриптов для запуска
     */
    private function scriptsList()
    {
        $files = glob(self::CONSOLE_PATH . '*.php');
        foreach ($files as $fn) {
            echo basename($fn) . PHP_EOL;
        }
    }
}
