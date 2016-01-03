<?php
/**
 * Контроллер главной страницы сайта
 */

namespace app\controllers;

class MainController extends \app\controllers\FrontController
{
    public $defaultAction = 'welcome';

    /**
     * Главная страница
     */
    public function welcome()
    {
        echo 'main page will be here';
    }
}
