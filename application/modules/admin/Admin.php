<?php
/**
 * Супер-класс контроллеров админки приложения
 */

namespace app\modules\admin\controllers;

class Admin extends \core\Controller
{
    /** @var int|false id юзера, если он на сайте */
    protected $userId;

    /**
     * Конструктор контроллера
     * Не забывай вызывать его в потомках ПЕРЕД их конструкторами.
     */
    public function __construct()
    {
        $this->userId = AuthService::checkAccess();

        //Тут нужна доп. поверка прав на работу в админке
    }
}
