<?php
namespace kira\configuration;

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
     * Конфигурация загружена полностью?
     *
     * @var bool
     */
    private $fullyLoaded;

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
     * Загрузка конфигурации приложения
     *
     * @return \Iterator в данной реализации - генератор.
     */
    public function loadConfiguration(): \Iterator
    {
        $this->fullyLoaded = false;
        foreach (self::enumerateMains() as $fileName) {
            yield require $fileName;
        }
        $this->fullyLoaded = true;
    }

    /**
     * Метод-флаг сообщает, когда конфиг загружен полностью.
     *
     * В данной реализации конфиг загрузится полностью, когда закончит работу генератор в loadConfiguration().
     *
     * @return bool
     */
    public function isFullyLoaded()
    {
        // return !$this->loadConfiguration()->isValid();
        return $this->fullyLoaded;
    }

    /**
     * Перечислить основные файлы конфигураций, в том порядке, как они будут загружаться.
     *
     * @return array
     */
    private function enumerateMains(): array
    {
        $parts = pathinfo($this->mainConfigFile);
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
