<?php
/**
 * Супер-класс. Контроллер админки приложения
 */
class AdminController extends Controller
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
