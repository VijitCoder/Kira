<?php
namespace kira\utils;

use kira\core\Dto;

/**
 * Информация о частях файлового пути после парсинга через FS::fileInfo()
 */
class FilePathInfo extends Dto
{

    /**
     * Исходное значение для парсинга
     *
     * @var string
     */
    public $source;

    /**
     * Путь к файлу, с концевым слешем (или обратным слешем)
     *
     * @var string
     */
    public $path;

    /**
     * Полное имя файла, с расширением
     *
     * @var string
     */
    public $fullName;

    /**
     * Часть имени файла без расширения
     *
     * @var string
     */
    public $shortName;

    /**
     * Расширение, без ведущей точки
     *
     * @var string
     */
    public $extension;

    /**
     * Заданный путь является каталогом?
     *
     * @return bool
     */
    public function isDir(): bool
    {
        return !$this->fullName;
    }

    /**
     * Заданный путь является файлом?
     *
     * @return bool
     */
    public function isFile(): bool
    {
        return $this->fullName;
    }
}
