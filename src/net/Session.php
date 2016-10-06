<?php
namespace engine\net;

use engine\App;

/**
 * Работа с сессией.
 *
 * Класс не включает в себя всю возможную работу по сессиям, только наиболее востребованные действия.
 */
class Session
{
    /**
     * Открываем сессию, если не сделали этого раньше
     */
    public static function init()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * Принудительно закрываем сессию после работы с ней.
     *
     * Обычно это не требуется, но могут возникнуть проблемы при параллельной работе скриптов с одним файлом сессии.
     * Подробнее тут {@link http://php.net/manual/ru/function.session-write-close.php}, комменты тоже полезны.
     */
    public static function close()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * Пишем значение в сессию. Перезапись существующего значения.
     *
     * @param string $key  ключ в сессии
     * @param mixed  $data данные для записи
     * @return void
     */
    public static function write($key, $data)
    {
        self::init();
        $_SESSION[$key] = $data;
    }

    /**
     * Читаем значение из сессии.
     *
     * Не задан ключ - вернуть все содержимое при режиме отладки; на боевом ничего без ключа не получим.
     *
     * @param string $key    ключ в сессии
     * @param bool   $strict Реакция на "не найдено значение", пробросить исключение или просто вернуть null.
     * @return string | null
     * @throws \Exception
     */
    public static function read($key = null, $strict = false)
    {
        self::init();
        if ($key) {
            if (isset($_SESSION[$key])) {
                return $_SESSION[$key];
            } elseif (!$strict) {
                return;
            } else {
                throw new \Exception("В сессии не найдено значение с ключом '{$key}'");
            }
        } else {
            return DEBUG ? $_SESSION : null;
        }
    }

    /**
     * Удаление значения из сессии.
     *
     * @param string $key ключ в сессии
     * @return void
     */
    public static function delete($key)
    {
        self::init();
        unset($_SESSION[$key]);
    }

    /**
     * Добавление flash-сообщения в сессию
     *
     * Флаг перезаписи: true - любое значение переписываем, false - массивы объединяем, строки дописываем, <b>целые</b>
     * числа суммируем, с другими данными не работаем. При этом тип новых данных приводим к имеющемуся в сессии.
     *
     * @param string $key   ключ в сессии
     * @param mixed  $data  данные для записи
     * @param bool   $force флаг перезаписи. True - любое значение переписываем, false - массивы объединяем,
     *                      строки дописываем, числа суммируем. При этом тип новых данных приводим к имеющемуся в сессии.
     * @return void
     */
    public static function addFlash($key, $data, $force = false)
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
     */
    public static function newFlash($key, $data)
    {
        self::addFlash($key, $data, true);
    }

    /**
     * Чтение конкретного flash-сообщения из сессии
     *
     * @param string $key ключ в сессии
     * @param bool   $del удалить запись из сессии
     * @return mixed | null
     */
    public static function readFlash($key, $del = true)
    {
        self::init();

        if (isset($_SESSION['flash'][$key])) {
            $result = $_SESSION['flash'][$key];
            if ($del) {
                unset($_SESSION['flash'][$key]);
            }
        } else {
            $result = null;
        }

        return $result;
    }

    /**
     * Чтение всех flash-сообщений из сессии
     *
     * @param bool $del удалить запись из сессии
     * @return mixed | null
     */
    public static function readFlashes($del = true)
    {
        self::init();
        $result = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
        if ($del) {
            unset($_SESSION['flash']);
        }
        return $result;
    }

    /**
     * Очистка flash-сообщений. Полезно в процессе разработки.
     */
    public static function dropFlashes()
    {
        self::init();
        unset($_SESSION['flash']);
    }
}
