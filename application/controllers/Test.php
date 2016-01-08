<?php
/**
 *
 */

namespace app\controllers;

class Test extends \core\Controller
{
    public function index()
    {
        echo 'test controller';
       dd($_GET);//DBG
    }

    public function some($v = null, $s = null)
    {
        echo "test controller\some: $v, $s<br>";
        dd($_GET);//DBG
    }

    public function other($id = null)
    {
        echo "test controller\other: $id<br>";
        dd($_GET);//DBG
    }
}
