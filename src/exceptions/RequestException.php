<?php
namespace kira\exceptions;

/**
 * Исключение указывает на ошибку в классе Request.
 *
 * Их пробрасывают методы типа postAsInt() и вся остальная компания.
 */
class RequestException extends EngineException
{
}
