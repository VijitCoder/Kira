<?php
namespace kira\core;

/**
 * Абстрактный класс логера
 *
 * Создан в основном для возможности подмены реазлизации на mock-объект в unit-тестах. В целом логер запилен в движке.
 * Подробности см. в его реализации.
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
        HTTP_ERROR = 'HTTP error', // например, 404, 403 можно логировать
        INFO = 'information',
        UNTYPED = 'untyped';

    /**
     * Хранение лога.
     *
     * Эти константы используются только в настройках приложения и задают поведение логера по умолчанию. В текущей
     * реализации интерфейса логера в случае сбоя переключаемся на вышестоящий. При 0 - только письмо админу.
     */
    const
        STORE_ERROR = 0,
        STORE_IN_FILES = 1,
        STORE_IN_DB = 2;

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
