<?php
/**
 * Контроллер профиля юзера
 */
class ProfileController extends AppController
{
    protected $title = 'Личный кабинет';

    /**
     * Защищенная зона юзера. Требуется проверка аутентификации перед любым действием
     */
    public function __construct()
    {
        parent::__construct();
        if (!$this->userId) {
            $this->redirect('/login');
        }
    }

    /**
     * Страница профиля
     */
    public function index()
    {
        $this->render('profile', ['d' => ProfileService::getDatas($this->userId)]);
    }
}
