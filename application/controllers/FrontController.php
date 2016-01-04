<?php
/**
 * Супер-класс. Контроллер фронта (публичной части сайта) приложения
 */

namespace app\controllers;

use core\App;

class FrontController extends \core\Controller
{
    /** @var string макет. Относительный путь в каталоге VIEWS_PATH + имя файла без расширения. */
    protected $layout = 'layouts/main';

    /** @var int|false id юзера, если он на сайте. По этому значению контроллеры и шаблоны меняют свое поведение */
    protected $userId;

    /**
     * Конструктор контроллера
     * Не забывай вызывать его в конструкторах потомков в НАЧАЛЕ функции.
     */
    public function __construct()
    {
        if ($this->title) {
            $this->title = App::t($this->title);
        }
        $this->userId = \app\services\Auth::checkAccess();
    }
}
