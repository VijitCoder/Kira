<?php
/**
 * Создаем новое приложение по заданной конфигурации.
 */

namespace install\app;

class MasterService
{
    /**
     * Ошибки валидации
     * @var array
     */
    public $errors = [];

    /**
     *
     * @var resource
     */
    private $_confHandle;

    /**
     * Конструктор
     */
    public function __construct()
    {
        dd($_POST); exit; //DBG
        $expect = array_flip(['path', 'db', 'log', 'lang', 'main_conf', 'ns_prefix', 'email',]);

        $post = array_intersect_key($_POST, $expect);

        if (count($post) != count($expect)) {
            exit('Переданы не все параметры.');
        }

        $paths = array_flip(['app', 'view', 'temp', 'js']);
        $post['path'] = array_intersect_key($post['path'], $paths);
        $paths = array_filter($post['path']);

        $conf = pathinfo($post['main_conf']);
        $paths['conf'] = $conf['dirname'];

        if ($structReady = $this->_sanitizePaths($paths)) {
            //$structReady = $this->_createPaths($paths);    // <-------------- TODO включить после отладки
            $post['main_conf'] = $paths['conf'] . $conf['basename'];
            $post['path'] = $paths;
        }
        unset($paths);

        if ($structReady) {
            $this->_writeIndex($post);
        }
    }

    private function __writeIndex(&$post)
    {
        $data = [
            '' => '',
            '' => '',
            '' => '',
            '' => '',
            '' => '',
        ];

        $file = Render::make(ROOT_PATH . 'install/source/index.php.ptrn', $data);
exit($file);
    }

    /**
     * Валидация каталогов
     *
     * Проверяем каталог на попытки переходов типа "../path/" или "some/../../path/". Это недопустимо.
     *
     * Каталог НЕ должен существовать, чтобы была возможность его создать без риска перезаписи чего-нибудь.
     *
     * @param array &$paths
     * @return bool если ошибок нет - TRUE.
     */
    private function _sanitizePaths(&$paths)
    {
        $errors = [];
        foreach ($paths as $k => &$path) {
            $path = trim(str_replace('\\', '/', $path), '/') . '/';

            if (preg_match('~^[.]{2}/|/[.]{2}/~', $path)) {
                $errors[$k] = 'Неправильный каталог. Он должен быть в пределах сайта.';
                continue;
            }

            $p = ROOT_PATH . $path;
            if (file_exists($p)) {
                $errors[$k] = "Каталог [$p] уже существует.";
            }
        }

        if ($errors) {
             $this->errors['path'] = $errors;
        }

        return empty($errors);
    }

    /**
     * Создание каталогов
     *
     * Только после валидации!
     *
     * @param array &$paths
     * @return bool если ошибок нет - TRUE.
     */
    private function _createPaths(&$paths)
    {
        $hasErrors = false;

        // @see http://stackoverflow.com/questions/1241728/can-i-try-catch-a-warning
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        foreach ($paths as $k => $path) {
            try {
                $path = ROOT_PATH . $path;
                mkdir($path, 0777, true);
                chmod($path, 0777);
            } catch (\ErrorException $e) {
                $this->errors['path'][$k] = "Не удалось создать каталог [$path]. Причина: " . $e->getMessage();
                $hasErrors = true;
            }
        }

        restore_error_handler();

        return !$hasErrors;
    }
}
