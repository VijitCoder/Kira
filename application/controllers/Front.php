<?php
/**
 * Супер-класс. Контроллер фронта (публичной части сайта) приложения
 */

namespace app\controllers;

use app\services\AuthService,
    engine\App;

class Front extends \engine\web\Controller
{
    /** @var string макет. Относительный путь в каталоге VIEWS_PATH + имя файла без расширения. */
    protected $layout = 'layouts/main';

    /** @var int|false id юзера, если он на сайте. По этому значению контроллеры и шаблоны меняют свое поведение */
    protected $userId;

    /** @var array массив частоиспользуемых URLs. Для сокращения кода в шаблонах. */
    protected $urls;

    /**
     * Конструктор контроллера
     * Не забывай вызывать его в конструкторах потомков в НАЧАЛЕ функции.
     */
    public function __construct()
    {
        if ($this->title) {
            $this->title = App::t($this->title);
        }
        $this->userId = AuthService::checkAccess();

        $urls = [
            'login'   => 'login',
            'logout'  => 'login/out',
            'profile' => 'profile',
            'reg'     => 'registration',
        ];
        foreach ($urls as &$v) {
            $v = App::router()->url([APP_NS_PREFIX . 'controllers\\', $v]);
        }
        $this->urls = $urls;
    }
}
