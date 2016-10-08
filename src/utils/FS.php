<?php
namespace kira\utils;

/**
 * FileSystem. Утилиты по работе с файловой системой
 */
class FS
{
    /**
     * Проверка пути на наличие './' или '../' в любом его месте.
     * @param string $path
     * @return bool
     */
    public static function hasDots($path)
    {
        return (bool)preg_match('~^[.]{1,2}/|/[.]{1,2}/~', $path);
    }

    /**
     * Создание каталога.
     *
     * Это сочетание mkdir() и chmod() с улучшениями: перехватываем ошибки (типа E_WARNING и др.), возвращаем их обычным
     * сообщением; назначаем требуемые права на все вложенные каталоги, создаваемые по пути. Если конечный каталог уже
     * существует, только установим ему требуемые права.
     *
     * Суть: выясняем, сколько каталогов по пути нужно будет создать. Создаем все разом через php::mkdir(). Каждому
     * новому каталогу с конца назначаем требуемые права. Почему с конца: если запрещено исполнение, то в каталог нельзя
     * будет попасть после установки прав.
     *
     * Прим: не используем для вычисления родителя функцию php::dirname(), она привносит свои заморочки.
     *
     * @param string $path путь к конечному каталогу
     * @param int    $mode права доступа, восьмеричное число
     * @return true|string
     */
    public static function makeDir($path, $mode = 0777)
    {
        if (self::hasDots($path)) {
            return 'Путь не должен содержать переходы типа "../", "./"';
        }

        if (!preg_match('~\\\\|/~', $path)) {
            return 'Неправильный путь. В нем нет ни одного слеша.';
        }

        $dirs = array_reverse(preg_split('~\w:\\\\|\\\\|/~', $path, -1, PREG_SPLIT_NO_EMPTY));

        $existingPath = $path;
        $cnt = 0;
        foreach ($dirs as $k => $d) {
            if (file_exists($existingPath)) {
                break;
            }
            $len = mb_strlen($existingPath) - (mb_strlen($d) + 1); // +1 это символ слеша
            $existingPath = mb_substr($existingPath, 0, $len);
            $cnt++;
        }

        set_error_handler(['\kira\utils\FS', 'error_handler']);

        try {
            if ($cnt) {
                mkdir($path, 0777, true);
                $dirs = array_slice($dirs, 0, $cnt);
                foreach ($dirs as $d) {
                    chmod($path, $mode);
                    $len = mb_strlen($path) - (mb_strlen($d) + 1); // +1 это символ слеша
                    $path = mb_substr($path, 0, $len);
                }
            } else {
                chmod($path, $mode);
            }
            $result = true;
        } catch (\ErrorException $e) {
            $result = $e->getMessage();
        }

        restore_error_handler();

        return $result;
    }

    /**
     * Удаление каталога с подкаталогами и файлами.
     *
     * Максимальный уровень вложенности каталогов используется как предохранитель (чтобы себе в ногу не выстрелить):
     * сначала выясняем, превышает ли заданный уровень реальная вложенность, и возвращаем ошибку, если так.
     * Только потом удаляем, через рекурсию вспомогательной функции.
     *
     * Сделал суровые условия: удалить можно от 1 до 4 уровней каталогов. Это очень опасная функция, нужно вообще
     * избегать ее использования.
     *
     * @param string $path      каталог для удаления
     * @param int    $fuseLevel предохранитель: ожидаемый максимальный уровень вложенности каталогов.
     * @return true|string
     * @throws \InvalidArgumentException
     */
    public static function removeDir($path, $fuseLevel = 1)
    {
        if (!file_exists($path)) {
            return true;
        }

        if (!is_dir($path)) {
            throw new \InvalidArgumentException($path . ' должно быть каталогом');
        }

        if ($fuseLevel < 1 || $fuseLevel > 4) {
            return 'удалить можно от 1 до 4 уровней каталогов. Для вашей же пользы.';
        }

        if (!self::checkMaxDepth($path, 0, $fuseLevel)) {
            return 'Целевой каталог имеет вложенность подкаталогов больше, чем ожидается.';
        }

        set_error_handler(['\kira\utils\FS', 'error_handler']);

        try {
            self::internalRemoveDir($path);
            $result = true;
        } catch (\ErrorException $e) {
            $result = $e->getMessage();
        }

        restore_error_handler();

        return $result;
    }

    /**
     * Рекурсивное удаление содержимого каталога.
     *
     * Прим: использование DirectoryIterator экономичнее по отношению к памяти, чем FileSystemIterator
     * @link http://php.net/manual/ru/class.filesystemiterator.php#114997
     *
     * @param string $path
     */
    private static function internalRemoveDir($path)
    {
        $dirList = new \DirectoryIterator($path);
        foreach ($dirList as $obj) {
            if ($obj->isDot()) {
                continue;
            }
            $name = $obj->getPathname();
            if ($obj->isDir()) {
                self::internalRemoveDir($name);
            } else {
                unlink($name);
            }
        }
        rmdir($path);
    }

    /**
     * Проверяем вложенность каталогов на превышение предела.
     *
     * Важно: не считаем максимальную вложенность, только выясняем, есть ли каталоги глубже требуемого. Это экономит
     * время проверки.
     *
     * @param string $path        путь к каталогу
     * @param int    $parentDepth уровень вложенности родителя
     * @param int    $maxDepth    максимально допустимый уровень вложенности
     * @return bool
     */
    private static function checkMaxDepth($path, $parentDepth, &$maxDepth)
    {
        $parentDepth++;
        $dirList = new \DirectoryIterator($path);
        foreach ($dirList as $obj) {
            if ($obj->isDot() || !$obj->isDir()) {
                continue;
            }
            if ($parentDepth >= $maxDepth) {
                return false;
            } elseif (!self::checkMaxDepth($obj->getPathname(), $parentDepth, $maxDepth)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Очистка каталога от файлов.
     *
     * Не работает с подкаталогами из соображений безопасности, очистка проводится только в текущем каталоге. Удаляем
     * все файлы или подходящие под заданный фильтр (регулярное выражение). Символические ссылки тоже удаляются,
     * оригиналы файлов при этом не будут затронуты.
     *
     * Прим: для очистки процессу нужен доступ на запись в целевой каталог. Доступ к файлам может вообще отсутствовать.
     *
     * @param string $path   каталог для очистки
     * @param string $filter регулярка для фильтрации. Полностью, включая операторные скобки и модификаторы.
     * @return true|string
     * @throws \LogicException
     * @throws \InvalidArgumentException
     */
    public static function clearDir($path, $filter = '')
    {
        if (!file_exists($path)) {
            throw new \LogicException("Каталог {$path} не существует.");
        }

        if (!is_dir($path)) {
            throw new \InvalidArgumentException($path . ' должно быть каталогом');
        }

        set_error_handler(['\kira\utils\FS', 'error_handler']);

        try {
            $dirList = new \DirectoryIterator($path);
            foreach ($dirList as $obj) {
                if ($obj->isDot() || $obj->isDir()) {
                    continue;
                }

                $name = $obj->getPathname();
                if (!$filter || preg_match($filter, $name)) {
                    unlink($name);
                }
            }
            $result = true;
        } catch (\ErrorException $e) {
            $result = $e->getMessage();
        }

        restore_error_handler();

        return $result;
    }

    /**
     * Копирование файла с перехватом ошибок. Просто обертка для php::copy()
     *
     * @param string   $from    путь/файл_источник
     * @param string   $to      путь/файл_назначение
     * @param resource $context корректный ресурс контекста, созданный функцией php::stream_context_create().
     * @return string|true
     */
    public static function copyFile($from, $to, $context = null)
    {
        set_error_handler(['\kira\utils\FS', 'error_handler']);

        try {
            if ($context) {
                copy($from, $to, $context);
            } else {
                copy($from, $to);
            }
            $result = true;
        } catch (\ErrorException $e) {
            $result = $e->getMessage();
        }

        restore_error_handler();

        return $result;
    }

    /**
     * Перемещение файла с перехватом ошибок. Просто обертка для php::rename()
     *
     * Прим: для переименования файла нужны только права на запись в каталоге. Сам файл может быть вообще без прав, даже
     * с другим владельцем. ПРОВЕРЕНО. Так же проверено: целевой файл существует, никаких прав на него нет, владелец
     * другой, но переписать его можно! Нужны только права на запись в каталоге.
     *
     * @param string $from путь/файл_источник
     * @param string $to   путь/файл_назначение
     * @return true|string
     */
    public static function moveFile($from, $to)
    {
        set_error_handler(['\kira\utils\FS', 'error_handler']);

        try {
            rename($from, $to);
            $result = true;
        } catch (\ErrorException $e) {
            $result = $e->getMessage();
        }

        restore_error_handler();

        return $result;
    }

    /**
     * Удаление файла с перехватом ошибок. Просто обертка для php::unlink().
     *
     * Перехватываем ошибки и возвращаем как сообщение об ошибке из этого метода. Иначе будет E_WARNING.
     *
     * @param string $fn путь/файл для удаления
     * @return true|string
     */
    public static function deleteFile($fn)
    {
        if (!file_exists($fn)) {
            return true;
        }

        set_error_handler(['\kira\utils\FS', 'error_handler']);
        try {
            unlink($fn);
            $result = true;
        } catch (\ErrorException $e) {
            $result = $e->getMessage();
        }
        restore_error_handler();
        return $result;
    }

    /**
     * Перехватываем ошибки функций PHP и вместо них пробрасываем исключение. Его должны ловить методы этого класса.
     *
     * По мотивам {@link http://stackoverflow.com/questions/1241728/can-i-try-catch-a-warning}
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     * @return void
     * @throws \ErrorException
     */
    public static function error_handler($errno, $errstr, $errfile, $errline)
    {
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
}
