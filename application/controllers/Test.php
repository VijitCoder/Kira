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
        \core\utils\Dumper::dump($_GET);//DBG
    }

    public function some($v = null, $s = null)
    {
        echo "test controller\some: $v, $s<br>";
        \core\utils\Dumper::dump($_GET);//DBG
    }

    public function other($id = null)
    {
        echo "test controller\other: $id<br>";
        \core\utils\Dumper::dump($_GET);//DBG
    }
}
