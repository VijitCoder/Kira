<?php

namespace kira\exceptions;

use kira\core\App;

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
        QUERY = 1,
        CONNECT = 2,
        // Неправильное использование функционала
        LOGIC = 3;

    /**
     * Конструктор
     *
     * Логируем ошибку. В зависимости от кода пишем лог в БД или файлы: если исключение проброшено из попытки соединения,
     * сразу же пишем лог в файлы, не пытаясь еще раз ткнуться в базу.
     *
     * @param string $message
     * @param int $code
     * @param \Exception $previous
     */
    public function __construct($message, $code = self::QUERY, $previous = null)
    {
        $logger = App::logger();
        if ($code === self::CONNECT) {
            $logger->add(['message' => $message, 'type' => $logger::DB_CONNECT, 'file_force' => true]);
        } elseif ($code === self::QUERY) {
            $logger->addTyped($message, $logger::DB_QUERY);
        }

        parent::__construct($message, $code, $previous);
    }
}
