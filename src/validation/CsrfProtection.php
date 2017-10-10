<?php
namespace kira\validation;

use kira\net\Request;
use kira\net\Session;
use kira\utils\Strings;

/**
 * CSRF защита
 */
class CsrfProtection
{
    /**
     * Имя cookie токена при хранении на клиенте, или ключа в сессии при хранении токена на сервере
     */
    private const KEY_NAME = 'CSRF_TOKEN';

    /**
     * Имя заголовка с соблюдением регистра, которое рекомендуется использовать при передаче токена на проверку через
     * заголовки. Модель формы ищет именно его.
     */
    const HEADER_NAME = 'X-Csrf-Token';

    /**
     * Создание токена для защиты от CSRF-атаки
     *
     * Если разрешено хранить токен в cookie, то создаем ее:
     * <pre>
     * Время жизни - до конца сессии
     * Видимость - по всему сайту
     * Отключить передачу "только по HTTPS"
     * Разрешить доступ из скриптов клиента (javascript, например).
     * </pre>
     *
     * @param bool $clientStore TRUE - хранить токен в печеньке, иначе - в сессии на сервере
     * @return string
     */
    public static function createToken($clientStore = true):string
    {
        $name = self::KEY_NAME;
        $token = Strings::randomString(32);
        if ($clientStore) {
            setcookie($name, $token, 0, '/', '', false, false);
        } else {
            Session::write($name, $token);
        }
        return $token;
    }

    /**
     * Получение текущего токена защиты от CSRF-атаки
     * @param bool $create       создать токен, если он не существует
     * @param bool $clientStore TRUE - хранить токен в печеньке, иначе - в сессии на сервере
     * @return string|null
     */
    public static function getToken($create = true, $clientStore = true): ?string
    {
        $name = self::KEY_NAME;
        $token = $clientStore ? Request::cookie($name) : Session::read($name);
        if (!$token && $create) {
            $token = self::createToken($clientStore);
        }
        return $token;
    }

    /**
     * Проверка токена защиты от CSRF-атаки
     *
     * Вызов метода выполняется без участия клиентского кода, следовательно нельзя определить, где хранится токен.
     * Поэтому сначала пытаемся найти его на клиенте, потом на сервере.
     *
     * @param string $passedToken токен для проверки, переданный клиентом
     * @return bool
     */
    public static function validate($passedToken)
    {
        $storeToken = self::getToken(false, true) ?? self::getToken(false, false);
        return $passedToken === $storeToken;
    }

    /**
     * Вспомогательный метод валидации, когда заведомо известно, что токен на проверку должен быть передан в заголовке
     * и при этом не используем модель формы для валидации.
     * @return bool
     */
    public static function validateFromHeader()
    {
        $passedToken = Request::headers(self::HEADER_NAME);
        return $passedToken && self::validate($passedToken);
    }
}
