<?php
namespace kira\configuration;

use kira\core\Singleton;
use kira\exceptions\ConfigException;
use kira\utils\Arrays;

/**
 * Менеджер конфигурации приложения
 *
 * Бизнес-логика работы с конфигурацией.
 */
class ConfigManager
{
    use Singleton;

    /**
     * Загруженная конфигурация приложения
     *
     * @var array
     */
    private $config = [];

    /**
     * Поставщик конфигурации
     *
     * @var AbstractConfigProvider
     */
    private $provider;

    /**
     * Менеджер конфигурации приложения
     *
     * Запоминаем поставщика конфигурации, с которым будем работать.
     *
     * @param AbstractConfigProvider $provider
     */
    private function __construct(AbstractConfigProvider $provider)
    {
        $this->provider = $provider;
    }

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
        $result = $this->getConfiguration();
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
        $this->getConfiguration();

        $conf = &$this->config;
        $keys = explode('.', $key);
        $levelKey = '';
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
     * Получаем конфигурацию у поставщика
     *
     * Если что-то в конфигурации потребует у движка данные из самой себя, движок сможет использовать ту часть конфига,
     * что уже успел загрузить. В этом суть пошаговой загрузки конфигурации приложения.
     *
     * TODO не смог создать unit-тест для пошаговой загрузки конфигурации. На реальных файлах знаю, что это работает.
     *
     * @return array
     */
    private function getConfiguration(): array
    {
        if (!$this->config) {
            $iterator = $this->provider->loadConfiguration();
            foreach ($iterator as $part) {
                $this->config = Arrays::merge_recursive($this->config, $part);
            }
        }

        return $this->config;
    }
}
