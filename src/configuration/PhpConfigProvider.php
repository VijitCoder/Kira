<?php
namespace kira\configuration;

use kira\exceptions\ConfigException;
use kira\utils\FS;

/**
 * Поставщик конфигурации приложения из php-файлов.
 */
class PhpConfigProvider extends AbstractConfigProvider
{
    /**
     * Абсолютный путь + имя главного файла конфигурации
     *
     * @var string
     */
    private $mainConfigFile;

    /**
     * Основные файлы конфигураций, в том порядке, как они будут загружаться.
     *
     * Явная инициализация с NULL нужна, чтобы случайно не переписать это значение при очередном рефакторинге.
     *
     * @var null|array
     */
    private $mains = null;

    /**
     * Конфигурация загружена полностью?
     *
     * @var bool
     */
    private $fullyLoaded = false;

    /**
     * Поставщик конфигурации приложения из php-файлов.
     *
     * Устанавливаем путь/файл главного конфига
     *
     * @param string $file абсолютный путь + имя главного файла конфигурации
     */
    public function __construct(string $file)
    {
        $this->mainConfigFile = $file;
    }

    /**
     * Пошаговая загрузка конфигурации приложения.
     *
     * По одному основному файлу конфигурации на вызов этого метода.
     *
     * @return array
     */
    public function loadConfiguration(): array
    {
        $this->mains = $this->mains ?? $this->enumerateMains();

        if ($this->mains) {
            $fileName = array_shift($this->mains);
            $config = require $fileName;
        } else {
            $config = [];
        }

        $this->fullyLoaded = !$this->mains;

        return $config;
    }

    /**
     * Метод-флаг сообщает, когда конфиг загружен полностью.
     *
     * @return bool
     */
    public function isFullyLoaded(): bool
    {
        return $this->fullyLoaded;
    }

    /**
     * Перечислить основные файлы конфигураций, в том порядке, как они будут загружаться.
     *
     * @return array
     * @throws ConfigException
     */
    private function enumerateMains(): array
    {
        $parts = FS::pathInfo($this->mainConfigFile);

        $pattern = "/{$parts->shortName}\d?\.{$parts->extension}/";
        $mains = FS::dirList($parts->path, $pattern);

        foreach ($mains as &$main) {
            $main = $parts->path . $main;
        }

        if (!$mains) {
            throw new ConfigException(
                'Не найден ни один основной файл конфигурации по заданному пути: ' . $this->mainConfigFile
            );
        }

        return $mains;
    }

    /**
     * Какие главные конфиги видит движок, отталкиваясь от указанного файла.
     *
     * Метод для отладки в конечном приложении. Можно его напрямую вызвать и увидеть, какие основные конфиги
     * собирается подхватить движок, и в каком порядке.
     *
     * @param string $mainConfigFile
     * @return array
     */
    public static function dryLoad(string $mainConfigFile): array
    {
        return (new static($mainConfigFile))->enumerateMains();
    }
}
