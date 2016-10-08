<?php
namespace kira\exceptions;

use kira\App;

/**
 * Исключение для функционала работы с базой данных. Модель и подключение к БД пробрасывают одинаковое исключение, но
 * с разными кодами. Клиента должен заботить только сам класс исключения, коды нужны для правильного логирования внутри
 * этого класса.
 */
class DbException extends \Exception
{
    /**
     * Код причины исключения
     */
    const
        QUERY = 0,
        CONNECT = 1;

    /**
     * Конструктор
     *
     * Логируем ошибку. В зависимости от кода пишем лог в БД или файлы: если исключение проброшено из попытки соединения,
     * сразу же пишем лог в файлы, не пытаясь еще раз ткнуться в базу.
     *
     * @param string    $message
     * @param int       $code
     * @param \Exception $previous
     */
    public function __construct($message, $code = self::QUERY, $previous = null)
    {
        $log =  App::log();
        if ($code === self::CONNECT) {
            $log->add(['message' => $message, 'type' => $log::DB_CONNECT, 'file_force' => true]);
        } else {
            $log->addTyped($message, $log::DB_QUERY);
        }

        parent::__construct($message, $code, $previous);
    }
}
