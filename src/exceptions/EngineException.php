<?php
namespace kira\exceptions;

/**
 * Базовое исключение движка. Все остальные исклчючения наследуются от него.
 *
 * Если наследник так же имеет свои коды по причинам исключения, они должны начинаться с 100 во избежание случайного
 * пересечения с кодами этого исключения.
 */
class EngineException extends \Exception
{
    /**
     * Коды причины исключения
     */

    /**
     * Ошибка, которая может быть найдена только во время выполнения
     */
    const RUNTIME_ERROR = 1;

    /**
     * Неверный агрумент/тип агрумента
     */
    const INVALID_ARGUMENT = 2;

    /**
     * Неправильное использование функционала
     */
    const LOGIC_ERROR = 3;

    /**
     * callback-функция относится к неопределенной функции или если некоторые аргументы отсутствуют
     */
    const BAD_FUNC_CALL = 4;

    /**
     * callback-функция относится к неопределенному методу или если некоторые аргументы отсутствуют
     */
    const BAD_METHOD_CALL = 5;

    /**
     * Имя класса, из которого проброшено исключение
     *
     * @var string
     */
    private $class = '';

    /**
     * Имя метода, из которого проброшено исключение
     *
     * @var string
     */
    private $method = '';

    /**
     * Конструктор
     *
     * @param string         $message
     * @param int            $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = '', $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $trace = $this->getTrace();
        $caller = $trace[0];
        $this->method = $caller['function'];
        if (isset($caller['class'])) {
            $this->class = $caller['class'];
        }
    }

    /**
     * Получить имя класса, из которого проброшено исключение
     *
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Получить имя метода, из которого проброшено исключение
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Получить форматированную строку вида "Class::method()". Она указывает на место,
     * откуда проброшено исключение.
     *
     * @return string
     */
    public function getClassMethodString(): string
    {
        return $this->class && $this->method
            ? "{$this->class}::{$this->method}()"
            : '';
    }
}
