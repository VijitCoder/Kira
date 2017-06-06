<?php
namespace kira\utils;

use kira\exceptions\FSException;

/**
 * FileSystem. Утилиты по работе с файловой системой
 *
 * На любые ошибки пробрасывается исключение движка FSException. В случае перехвата ошибок PHP, кодом исключения будет
 * уровень ошибки PHP (E_WARNING, E_NOTICE и т.п.)
 *
 * См. документацию, "Утилиты"
 */
class FS
{
    /**
     * Проверка пути на наличие './' или '../' в любом его месте.
     * @param string $path
     * @return bool
     */
    public static function hasDots(string $path): bool
    {
        return (bool)preg_match('~^[.]{1,2}/|/[.]{1,2}/~', $path);
    }

    /**
     * Нормализация пути
     *
     * Понятие нормы условное. В данном контексте: обратные слеши переводятся в прямые, в начале убраем слеш, в конце
     * дописываем при необходимости. Не заменяются переходы './' и '../'. Т.о. получится стабильный вид любого каталога
     * или файла.
     *
     * @param string $path   исходное значение пути
     * @param bool   $isFile переданное значение является путем к файлу
     * @return string
     */
    public static function normalizePath(string $path, bool $isFile = false): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        return $isFile ? $path : $path . '/';
    }

    /**
     * Проверка: переданный каталог похож на абсолютный в стиле Windows
     * @param string $path каталог для проверки
     * @return bool
     */
    public static function isWindowsRootedPath(string $path): bool
    {
        return preg_match('~^[a-z]+:(/|\\\\)~', $path);
    }

    /**
     * Создание каталога.
     *
     * Это сочетание mkdir() и chmod() с улучшениями: перехватываем ошибки (типа E_WARNING и др.), возвращаем их обычным
     * сообщением; назначаем требуемые права на все вложенные каталоги, создаваемые по пути. Если конечный каталог уже
     * существует, только установим ему требуемые права.
     *
     * @param string $path путь к конечному каталогу
     * @param int    $mode права доступа, восьмеричное число
     * @throws FSException
     */
    public static function makeDir(string $path, int $mode = 0777)
    {
        if (self::hasDots($path)) {
            throw new FSException('Путь не должен содержать переходы типа "../", "./"');
        }

        if (!preg_match('~\\\\|/~', $path)) {
            throw new FSException('Неправильный путь. В нем нет ни одного слеша.');
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

        System::errorWrapper(
            FSException::class,
            function () use ($cnt, $path, $dirs, $mode) {
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
            }
        );
    }

    /**
     * Удаление каталога с подкаталогами и файлами
     *
     * Максимальный уровень вложенности каталогов используется как предохранитель (чтобы себе в ногу не выстрелить):
     * сначала выясняем, превышает ли заданный уровень реальная вложенность, и возвращаем ошибку, если так.
     * Только потом удаляем, через рекурсию вспомогательной функции.
     *
     * Сделал суровые условия: удалить можно от 1 до 4 уровней каталогов. Это очень опасная функция, нужно вообще
     * избегать ее использования.
     *
     * @param string $path        каталог для удаления
     * @param int    $expectLevel предохранитель: ожидаемый максимальный уровень вложенности каталогов.
     * @return true|string
     * @throws FSException
     */
    public static function removeDir(string $path, int $expectLevel = 1)
    {
        if (!file_exists($path)) {
            return;
        }

        if (!is_dir($path)) {
            throw new FSException($path . ' должно быть каталогом');
        }

        if ($expectLevel < 1 || $expectLevel > 4) {
            throw new FSException('удалить можно от 1 до 4 уровней каталогов. Для вашей же пользы.');
        }

        if (!self::checkMaxDepth($path, 0, $expectLevel)) {
            throw new FSException('Целевой каталог имеет вложенность подкаталогов больше, чем ожидается.');
        }

        System::errorWrapper(FSException::class, function () use ($path) {
            self::internalRemoveDir($path);
        });
    }

    /**
     * Рекурсивное удаление содержимого каталога
     * @param string $path
     */
    private static function internalRemoveDir(string $path)
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
     * Проверяем вложенность каталогов на превышение предела
     *
     * Важно: не считаем максимальную вложенность, только выясняем, есть ли каталоги глубже требуемого. Это экономит
     * время проверки.
     *
     * @param string $path        путь к каталогу
     * @param int    $parentDepth уровень вложенности родителя
     * @param int    $maxDepth    максимально допустимый уровень вложенности
     * @return bool
     */
    private static function checkMaxDepth(string $path, int $parentDepth, int &$maxDepth): bool
    {
        $parentDepth++;
        $dirList = new \DirectoryIterator($path);
        foreach ($dirList as $obj) {
            if ($obj->isDot() || !$obj->isDir()) {
                continue;
            }
            if ($parentDepth >= $maxDepth
                || !self::checkMaxDepth($obj->getPathname(), $parentDepth, $maxDepth)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Получение списка файлов в указанном каталоге. Не читает вложенные каталоги, только заданный.
     *
     * Результат сортируется алгоритмом "natural order" {@link http://php.net/manual/ru/function.natsort.php natsort()}
     * Такая сортировка сохраняет ключи, даже числовые. <b>Если использовать этот метод в тестах</b>, нужно сбрасывать
     * ключи, т.к. они не важны, как правило, но тест с оригинальными числовыми ключами может не пройти. Используйте
     * array_values() к результату этого метода.
     *
     * @param string $path   абсолютный каталог для поиска файлов
     * @param string $filter регулярка для фильтрации. Полностью, включая операторные скобки и модификаторы.
     * @return array список файлов без пути
     * @throws FSException
     */
    public static function dirList(string $path, string $filter = ''): array
    {
        if (!file_exists($path)) {
            throw new FSException("Каталог {$path} не существует.");
        }

        if (!is_dir($path)) {
            throw new FSException($path . ' должно быть каталогом');
        }

        return System::errorWrapper(
            FSException::class,
            function () use ($path, $filter) {
                $dirList = new \DirectoryIterator($path);
                $fileNames = [];
                foreach ($dirList as $obj) {
                    if ($obj->isDot() || $obj->isDir()) {
                        continue;
                    }

                    $name = $obj->getBasename();
                    if (!$filter || preg_match($filter, $name)) {
                        $fileNames[] = $name;
                    }
                }
                if (!natsort($fileNames)) {
                    trigger_error('Ошибка сортировки массива', E_USER_WARNING);
                }
                return $fileNames;
            }
        );
    }

    /**
     * Очистка каталога от файлов.
     *
     * Не работает с подкаталогами из соображений безопасности, очистка проводится только в текущем каталоге. Удаляем
     * все файлы или подходящие под заданный фильтр (регулярное выражение). Символические ссылки тоже удаляются,
     * оригиналы файлов при этом не будут затронуты.
     *
     * Для очистки процессу нужен доступ на запись в целевой каталог. Доступ к файлам может вообще отсутствовать.
     *
     * @param string $path   каталог для очистки
     * @param string $filter регулярка для фильтрации. Полностью, включая операторные скобки и модификаторы.
     * @throws FSException
     */
    public static function clearDir(string $path, string $filter = '')
    {
        $path = self::normalizePath($path);
        if (!$filesNames = self::dirList($path, $filter)) {
            return;
        }
        System::errorWrapper(
            FSException::class,
            function () use ($filesNames, $path) {
                foreach ($filesNames as $fileName) {
                    unlink($path . $fileName);
                }
            }
        );
    }

    /**
     * Копирование файла с перехватом ошибок
     * @param string   $from    путь/файл_источник
     * @param string   $to      путь/файл_назначение
     * @param resource $context корректный ресурс контекста, созданный функцией php::stream_context_create().
     * @throws FSException из обработчика ошибок
     */
    public static function copyFile(string $from, string $to, resource $context = null)
    {
        System::errorWrapper(
            FSException::class,
            function () use ($from, $to, $context) {
                $context ? copy($from, $to, $context) : copy($from, $to);
            }
        );
    }

    /**
     * Переименовывание файла с перехватом ошибок
     * @param string   $oldName путь/строе_имя_файла
     * @param string   $newName путь/новое_имя_файла
     * @param resource $context корректный ресурс контекста, созданный функцией php::stream_context_create().
     * @throws FSException из обработчика ошибок
     */
    public static function renameFile(string $oldName, string $newName, resource $context = null)
    {
        System::errorWrapper(
            FSException::class,
            function () use ($oldName, $newName, $context) {
                $context ? rename($oldName, $newName, $context) : rename($oldName, $newName);
            }
        );
    }

    /**
     * Перемещение файла с перехватом ошибок
     *
     * Для переименования файла нужны только права на запись в каталоге. Сам файл может быть вообще без прав, даже
     * с другим владельцем. ПРОВЕРЕНО. Так же проверено: целевой файл существует, никаких прав на него нет, владелец
     * другой, но переписать его можно! Нужны только права на запись в каталоге.
     *
     * @param string $from путь/файл_источник
     * @param string $to   путь/файл_назначение
     * @throws FSException из обработчика ошибок
     */
    public static function moveFile(string $from, string $to)
    {
        System::errorWrapper(FSException::class, 'rename', $from, $to);
    }

    /**
     * Удаление файла с перехватом ошибок
     * @param string $fn путь/файл для удаления
     * @throws FSException из обработчика ошибок
     */
    public static function deleteFile(string $fn)
    {
        if (!file_exists($fn)) {
            return;
        }
        System::errorWrapper(FSException::class, 'unlink', $fn);
    }
}
