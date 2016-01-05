<?php
/**
 * Контроллер главной страницы сайта
 */

namespace app\controllers;

class Welcome extends \app\controllers\Front
{
    /**
     * Главная страница
     */
    public function index()
    {
        echo 'main page will be here';
    }
}
