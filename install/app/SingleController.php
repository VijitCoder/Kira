<?php
/**
 * Единственный контроллер мастера приложения
 */

namespace install\app;

class SingleController extends \engine\web\Controller
{
    protected $title = 'Kira Engine. Создание нового приложения';

    /**
     * Индесная страница: форма мастера
     */
    public function index()
    {
        $this->render('form');
    }

    /**
     * Обработка формы мастера
     */
    public function createApp()
    {
        new MasterService;
        //...TODO
        $this->redirect('success');
    }

    /**
     * Успешно создано приложение. Поздравляем и выдаем сводку по процессу.
     */
    public function finish()
    {
        $this->render('finish');
    }
}
