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
            } elseif ($result === true) {
                $this->redirect('/install/success');
            } else {
                $this->redirect('/install/error');
            }
        }
    }

    /**
     * Приложение успешно создано. Выдаем сводку по процессу.
     * Тут выясняем максимальный уровень сводки. Если не выше BRIEF_INFO, значит установка прошла вообще без проблем.
     */
    public function success()
    {
        if (!$brief =Session::readFlash('brief')) {
            $this->redirect('/install');
        }
        $brief = unserialize($brief);
        $isOk = array_shift($brief);
        $isOk = $isOk <= MasterService::BRIEF_INFO;
        $this->render('success', compact('isOk', 'brief'));
    }

    /**
     * Уже после валидации произошли ошибки при создании приложения. Выдаем сводку.
     */
    public function error()
    {
        if (!$brief =Session::readFlash('brief')) {
            $this->redirect('/install');
        }

        $brief = unserialize($brief);
        array_shift($brief);
        $this->render('error', ['brief' => $brief]);
    }

    /**
     * Откат созданного приложения. Удаляем каталоги, файлы и таблицу логера.
     * TODO два поведения: либо откат, либо удаление файла отката. GET[confirm] = yes|no, при нужно куда-то редиректить.
     */
    public function rollback()
    {
        $svc = new MasterService;
        $svc->rollback();
        $this->render('_brief', ['brief' => $svc->getBrief()]);
    }
}
