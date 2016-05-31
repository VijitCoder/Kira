<?php
/**
 * Единственный контроллер мастера приложения
 */

namespace install\app;

class SingleController extends \engine\web\Controller
{
    protected $title = 'Kira Engine. Создание нового приложения';

    /**
     * Индесная страница: форма мастера.
     * POST. Обработка формы мастера.
     *
     * От сервиса контроллер ожидает либо массив [d, errors] либо TRUE в случае создания приложения. Если в процессе
     * создания были ошибки, информация будет предоставлена на странице сводки. Этот метод работает только с формой
     * и ее ошибками валидации.
     */
    public function index()
    {
        $svc = new MasterService;

        if (!$_POST) {
            $this->render('form', $svc->prepareViewData());
        } else {
            if (true !== ($viewData = $svc->createApp())) {
                $this->render('form', $viewData);
            } else {
                $this->redirect('finish');
            }
        }
    }

    /**
     * Приложение создано. Выдаем сводку по процессу. Если были ошибки в процессе, тут их сообщаем.
     */
    public function finish()
    {
        $this->render('finish');
    }
}
