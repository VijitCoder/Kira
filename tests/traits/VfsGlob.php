<?php
namespace kira\tests\traits;

use kira\utils\FS;

/**
 * Костыль для vfsStream. В ней нельзя использовать glob() {@link https://github.com/mikey179/vfsStream/issues/2}
 * Использую свое решение.
 */
trait VfsGlob
{
    /**
     * Получение списка файлов в заданном каталоге виртуальной файловой системы
     *
     * См. так же комментарий к используемому методу касательно сортировки.
     *
     * @param string $absolutePath абсолютный путь в каталог для получения из него списка файлов
     * @param string $filter       регулярка фильтра
     * @return array
     */
    protected function getFileList(string $absolutePath, string $filter = '/.*/')
    {
        $absolutePath = FS::normalizePath($absolutePath);
        return array_values(FS::dirList($absolutePath, $filter));
    }
}
