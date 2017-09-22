<?php
namespace kira\validation\validators;

use kira\exceptions\FormException;

/**
 * Валидатор email
 *
 * Проверка на корректность и черный список серверов.
 *
 * Настройки:
 * <pre>
 * $options = [
 *   'regexp' => '/.+@.+\..+/', // значение по умолчанию
 *   'black_servers' => array | NULL
 * ]
 * </pre>
 *
 * Свои сообщения об ошибках можно назначить в 'message' либо массивом с ключами regexp|black_servers, либо строкой,
 * которая будет возвращаться на любые ошибки валидации email.
 */
class Email extends AbstractValidator
{
    /**
     * Настройки валидатора по умолчанию
     * @var array
     */
    protected $options = [
        'regexp'        => '/.+@.+\..+/',
        'black_servers' => null,
    ];

    /**
     * Библиотека возможных сообщений об ошибках
     * @var array
     */
    protected $messageLibrary = [
        'regexp'        => 'Неверный формат почтового адреса',
        'black_servers' => 'Почтовый сервер вашего email в черном списке. Пожалуйста, укажите другой адрес',
    ];

    /**
     * Требуем наличие обязательных настроек валидатора
     * @param array|true $options
     * @throws FormException
     */
    public function __construct($options)
    {
        parent::__construct($options);
        if (!isset($this->options['regexp'])) {
            throw new FormException('Не задано обязательное регулярное выражение для проверки email');
        }
    }

    /**
     * Проверка email на корректность и черный список
     * @param mixed $value проверяемое значение
     * @return bool
     */
    public function validate($value)
    {
        $this->value = $value;

        if (!preg_match($this->options['regexp'], $value)) {
            $this->error = $this->messageLibrary['regexp'];
            return false;
        }

        if ($blackServers = $this->options['black_servers']) {
            $server = mb_substr($value, mb_strpos($value, '@') + 1);
            if (in_array($server, $blackServers)) {
                $this->error = $this->messageLibrary['black_servers'];
                return false;
            }
        }

        return true;
    }
}
