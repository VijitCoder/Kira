<?php
namespace kira\core;

/**
 * Абстрактный класс логера
 */
abstract class AbstractLogger
{
    /**
     * Типы логов
     */
    const
        ENGINE = 'engine',
        DB_CONNECT = 'DB connection',
        DB_QUERY = 'DB query',
        EXCEPTION = 'exception',
        HTTP_ERROR = 'HTTP error',
        INFO = 'information',
        UNTYPED = 'untyped';

    /**
     * Хранение лога.
     *
     * Эти константы используются только в настройках приложения и задают поведение логера по умолчанию. В случае сбоя
     * логирования на заданном уровне переключаемся на вышестоящий. При 0 - только письмо админу.
     */
    const
        STORE_ERROR = 0,
        STORE_IN_FILES = 1,
        STORE_IN_DB = 2;

    /**
     * Формат даты/времени при записи лога в файл или на почту
     */
    protected const FILE_TIME_FORMAT = 'Y.m.d H:i:s \G\M\T P';

    /**
     * Запись сообщения в лог
     *
     * Ожидаем либо строку с сообщением либо массив, содержащий сообщение и другие данные. Полный формат массива такой:
     * <pre>
     * [
     *  'message'    => string                 текст сообщения
     *  'type'       => const  | self::UNTYPED тип лога, см. константы этого класса
     *  'source'     => string | ''            источник сообщения
     *  'notify'     => bool | FALSE           флаг "Нужно оповещение по почте"
     *  'file_force' => bool | FALSE           сообщение писать в файл, независимо от настройки.
     * ]
     * </pre>
     *
     * @param string|array $data
     * @return void
     * @throws \LogicException
     */
    abstract public function add($data);

    /**
     * Запись типизированного сообщения
     * @param string $message текст сообщения
     * @param string $type    тип лога, см. константы этого класса
     * @return void
     */
    abstract public function addTyped(string $message, string $type);
}
