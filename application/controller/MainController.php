<?php
/**
 * Контроллер главной страницы сайта
 */
class MainController extends AppController
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
