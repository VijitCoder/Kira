<?php
/**
 * Супер-класс. Контроллер фронта (публичной части сайта) приложения
 */

namespace app\controllers;

class FrontController extends \core\Controller
{
    /** @var int|false id юзера, если он на сайте. По этому значению контроллеры и шаблоны меняют свое поведение */
    protected $userId;

    /**
     * Конструктор контроллера
     * Не забывай вызывать его в потомках ПЕРЕД их конструкторами.
     */
    public function __construct()
    {
        if ($this->title) {
            $this->title = App::t($this->title);
        }

        $this->userId = AuthService::checkAccess();
    }
}
