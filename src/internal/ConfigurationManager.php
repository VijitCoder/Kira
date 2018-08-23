<?php
namespace kira\internal;

use kira\core\Singleton;
use kira\exceptions\ConfigException;
use kira\utils\Arrays;
use kira\utils\FS;

/**
 * Менеджер конфигурации приложения
 */
class ConfigurationManager
{
    use Singleton;

    /**
     * @var array конфигурация приложения
     */
    private $config = [];

    /**
     * Чтение конкретного значения из конфигурации
     *
     * Можно указать цепочку вложенных ключей, через точку. Типа: "validators.password.minComb". Возвращено будет
     * значение последнего ключа. Очевидно, что использовать точку в именах ключей конфига теперь нельзя.
     *
     * @param string $key    ключ в конфиге
     * @param bool   $strict флаг критичности реакции, когда настройка не найдена: TRUE = пробросить исключение
     * @return mixed|null
     * @throws ConfigException
     */
    public function getValue($key, $strict = true)
    {
        $result = $this->loadConfiguration();
        foreach (explode('.', $key) as $levelKey) {
            if (!isset($result[$levelKey])) {
                if ($strict) {
                    throw new ConfigException("В конфигурации не найден ключ '{$levelKey}'");
                } else {
                    return null;
                }
            }

            $result = $result[$levelKey];
        }

        return $result;
    }

    /**
     * Замена значения в конфиге "налету"
     *
     * Ключ может быть составным, через точку (по аналогии с чтением конфига). Новое значение <b>перезаписывает</b>
     * текущее.
     *
     * @param string $key   ключ в конфиге
     * @param mixed  $value новое значение
     * @throws ConfigException
     */
    public function setValue($key, $value)
    {
        if (!$key) {
            throw new ConfigException('Ключ в конфигурации не может быть пустым.');
        }

        // Нужно гарантировать, что конфиг загружен.
        $this->loadConfiguration();

        $conf = &$this->config;
        $keys = explode('.', $key);
        for ($i = 0, $cnt = count($keys); $i < $cnt; ++$i) {
            $levelKey = $keys[$i];
            if (!isset($conf[$levelKey])) {
                throw new ConfigException("В конфигурации не найден ключ '{$levelKey}'");
            }
            if ($i < $cnt - 1) {
                $conf = &$conf[$levelKey];
            }
        }
        $conf[$levelKey] = $value;
    }

    /**
     * Загрузка и получение всей конфигурации приложения
     *
     * @return array
     */
    private function loadConfiguration()
    {
        if (!$this->config) {
            foreach ($this->mainConfigsGenerator() as $fileName) {
                $configPart = require $fileName;
                $this->config = Arrays::merge_recursive($this->config, $configPart, true);
            }
        }

        return $this->config;
    }

    /**
     * Генератор: получить список файлов основных конфигураций. Отдавать по одному.
     *
     * @return \Generator
     */
    private function mainConfigsGenerator()
    {
        $mains = self::enumerateMains();
        foreach ($mains as $main) {
            yield $main;
        }
    }

    /**
     * Перечислить основные файлы конфигураций, в том порядке, как они будут загружаться.
     *
     * Метод публичный для отладки в конечном приложении. Можно его напрямую вызвать и увидеть, какие основные конфиги
     * собирается подхватить загрузчик, и в каком порядке.
     *
     * @return array
     */
    public static function enumerateMains(): array
    {
        $parts = pathinfo(KIRA_MAIN_CONFIG);
        $dir = '/' . FS::normalizePath($parts['dirname']);
        $fileName = $parts['filename'];
        $extension = $parts['extension'];

        $pattern = "/{$fileName}\d?\.{$extension}/";
        $mains = FS::dirList($dir, $pattern);

        foreach ($mains as &$main) {
            $main = $dir . $main;
        }
        return $mains;
    }
}

