<?php
/**
 * Контроллер главной страницы сайта
 */
class MainController extends FrontController
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
