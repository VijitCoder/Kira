<?php
namespace kira\net;

use kira\core\App;
use kira\exceptions\SessionException;

/**
 * Работа с сессией.
 *
 * Класс не включает в себя всю возможную работу по сессиям, только наиболее востребованные действия.
 *
 * См. документацию, "Обобщение"
 */
class Session
{
    /**
     * Открываем сессию, если не сделали этого раньше
     */
    public static function init(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * Явно закрываем сессию после работы с ней
     *
     * Обычно это не требуется, но могут возникнуть проблемы при параллельной работе скриптов с одним файлом сессии.
     * Подробнее тут {@link http://php.net/manual/ru/function.session-write-close.php}, комменты тоже полезны.
     */
    public static function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * Пишем значение в сессию. Перезапись существующего значения.
     * @param string $key  ключ в сессии
     * @param mixed  $data данные для записи
     */
    public static function write(string $key, $data): void
    {
        self::init();
        $_SESSION[$key] = $data;
    }

    /**
     * Читаем значение из сессии
     * @param string $key    ключ в сессии
     * @param bool   $strict Реакция на "не найдено значение", пробросить исключение или просто вернуть null.
     * @return string | null
     * @throws SessionException
     */
    public static function read(string $key, bool $strict = false): ?string
    {
        self::init();
        if (isset($_SESSION[$key])) {
            return $_SESSION[$key];
        } elseif (!$strict) {
            return null;
        } else {
            throw new SessionException("В сессии не найдено значение с ключом '{$key}'");
        }
    }

    /**
     * Извлечение значения из сессии. Сочетание чтения с удалением
     * @param string $key    ключ в сессии
     * @param bool   $strict Реакция на "не найдено значение", пробросить исключение или просто вернуть null.
     * @return string|null
     */
    public static function pop(string $key, bool $strict = false): ?string
    {
        $value = self::read($key, $strict);
        self::delete($key);
        return $value;
    }

    /**
     * Получение всех значений из сессии, за исключением flash-сообщений
     * @return array|null
     */
    public static function all(): ?array
    {
        self::init();
        $values = $_SESSION;
        unset($values['flash']);
        return $values;
    }

    /**
     * Удаление значения из сессии
     * @param string $key ключ в сессии
     */
    public static function delete(string $key): void
    {
        self::init();
        unset($_SESSION[$key]);
    }

    /**
     * Очистка сессии
     *
     * Важно: очистка не удаляет cookie сессии (если id вообще хранится в печеньке). Тут выполняется только удаление
     * данных из сессии.
     *
     * Использование этого метода может привести к состоянию гонки, см. мануал по session_regenerate_id().
     * Но пока не столкнулся - не усложняю.
     *
     * @param bool $resetId TRUE - обновить id сессии после очистки
     */
    public static function clear($resetId = false): void
    {
        self::init();
        session_unset();

        if ($resetId) {
            session_regenerate_id();
        }
    }

    /**
     * Добавление flash-сообщения в сессию
     *
     * Флаг перезаписи: true - любое значение переписываем, false - массивы объединяем, строки дописываем, <b>целые</b>
     * числа суммируем, с другими данными не работаем. При этом тип новых данных приводим к имеющемуся в сессии.
     *
     * @param string $key   ключ в сессии
     * @param mixed  $data  данные для записи
     * @param bool   $force флаг перезаписи
     */
    public static function addFlash(string $key, $data, bool $force = false): void
    {
        self::init();

        $data = App::t($data);

        if (isset($_SESSION['flash'][$key]) && !$force) {
            $curData = $_SESSION['flash'][$key];
            if (is_array($curData)) {
                $data = array_merge($curData, (array)$data);
            } elseif (!is_numeric($curData)) {
                $data = $curData . $data;
            } elseif (preg_match('~^\d+$~', $curData)) {
                $data = (int)$curData + intval($data);
            }
        }
        $_SESSION['flash'][$key] = $data;
    }

    /**
     * Алиас функции addFlash() с поднятым флагом перезаписи. Для удобства использования.
     * @param string $key  ключ в сессии
     * @param mixed  $data данные для записи
     */
    public static function newFlash(string $key, $data): void
    {
        self::addFlash($key, $data, true);
    }

    /**
     * Чтение конкретного flash-сообщения из сессии
     * @param string $key    ключ в сессии
     * @param bool   $delete удалить запись из сессии
     * @return mixed | null
     */
    public static function readFlash(string $key, bool $delete = true)
    {
        self::init();

        if (isset($_SESSION['flash'][$key])) {
            $result = $_SESSION['flash'][$key];
            if ($delete) {
                unset($_SESSION['flash'][$key]);
            }
        } else {
            $result = null;
        }

        return $result;
    }

    /**
     * Чтение всех flash-сообщений из сессии
     * @param bool $delete удалить запись из сессии
     * @return mixed | null
     */
    public static function readFlashes(bool $delete = true)
    {
        self::init();
        $result = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
        if ($delete) {
            unset($_SESSION['flash']);
        }
        return $result;
    }

    /**
     * Очистка flash-сообщений. Полезно в процессе разработки.
     */
    public static function dropFlashes(): void
    {
        self::init();
        unset($_SESSION['flash']);
    }
}
