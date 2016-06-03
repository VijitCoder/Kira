<?php
namespace install\app;

use engine\net\Session;
use engine\web\Controller;

/**
 * Единственный контроллер мастера приложения
 */
class SingleController extends Controller
{
    protected $title = 'Kira Engine. Создание нового приложения';

    /**
     * Индесная страница: форма мастера.
     * POST. Обработка формы мастера.
     *
     * От сервиса контроллер ожидает либо массив [d, errors] либо BOOL в случае создания приложения. Если в процессе
     * создания были ошибки, информация будет предоставлена на странице сводки. Этот метод работает только с формой
     * и ее ошибками валидации. Ошибки процесса - на другую страницу.
     */
    public function index()
    {
        $svc = new MasterService;

        if (!$_POST) {
            $this->render('form', $svc->prepareViewData());
        } else {
            $result = $svc->createApp();
            if (is_array($result)) {
                $this->render('form', $result);
            } else {
                $this->redirect('/finish');
            }
        }
    }

    /**
     * Приложение создано. Выдаем сводку по процессу. Если были ошибки в процессе, тут их сообщаем.
     */
    public function finish()
    {
        dd(Session::readFlash('brief')); exit; //DBG

        $this->render('finish', ['brief' => Session::readFlash('brief')]);
    }

    /**
     * Откат созданного приложения. Удаляем каталоги, файлы и таблицу логера.
     */
    public function rollback()
    {
        $svc = new MasterService;
        $svc->rollback();

        dd($svc->getBrief()); exit; //DBG

        $this->render('finish', ['brief' => $svc->getBrief()]);
    }
}
