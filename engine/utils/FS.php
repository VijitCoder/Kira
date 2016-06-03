<?php
namespace engine\utils;

/**
 * FileSystem
 * Утилиты по работе с файловой системой
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
     * Перехватываем ошибки функций PHP и возвращаем как сообщение об ошибке из этого метода.
     * @see http://stackoverflow.com/questions/1241728/can-i-try-catch-a-warning
     *
     * @param string $path путь к конечному каталогу
     * @param int    $mode права доступа, восьмеричное число
     * @return bool|string
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

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

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
     * @return bool
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

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

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
                //echo "Файл $name <br>";
                unlink($name);
            }
        }
        //echo "Каталог $path <br>";
        rmdir($path);
    }

    /**
     * Удаление файлов из каталога с заданными расширениеми.
     * Если расширения не указаны, удаляем все файлы. Расширения пишем без точки, через разделитель "|".
     * @param string $path каталог для очистки
     * @param string $ext  расширения удаляемых файлов
     * @return bool
     */
    public static function clearDir($path, $ext = '')
    {
        //TODO
    }

    /**
     * Проверяем вложенность каталогов на превышение предела.
     * Важно: не считаем максимальную вложенность, только выясняем, есть ли каталоги глубже требуемого. Это экономит
     * время проверки.
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
     * Просто обертка для php::unlink().
     * Перехватываем ошибки и возвращаем как сообщение об ошибке из этого метода. Иначе будет E_WARNING.
     * @param $fn
     * @return bool|string
     */
    public static function deleteFile($fn)
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        try {
            unlink($fn);
            $result = true;
        } catch (\ErrorException $e) {
            $result = $e->getMessage();
        }

        restore_error_handler();

        return $result;
    }
}