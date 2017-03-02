<?php
namespace kira;

use kira\html\Render;
use kira\net\{Request, Response};
use kira\web\Env;
use kira\core\App;
use kira\utils\ColorConsole;

/**
 * Перехватчик исключений, обработчики ошибок.
 *
 * Как правило, они назначены в bootstrap.php, вызываются внутренним механизмом PHP и не ожидают прямого обращения
 * из кода приложения.
 *
 * См. документацию, "Перехват ошибок"
 *
 * @internal
 */
class Handlers
{
    /**
     * Перехватчик исключений. Ловит исключения, которые не были пойманы ранее.
     *
     * После выполнения этого обработчика программа остановится, обеспечено PHP.
     *
     * Если указано предыдущее исключение, отсюда не пишем в лог. Сей факт указывает на то, что реальное исключение
     * уже было поймано и обработано. Считаем, что необходимость логирования была решена в предыдущих обработчиках.
     *
     * Прим: для поддержки PHP 7.0 тип ожидаемого параметра расширен.
     *
     * @param \Throwable $ex исключение
     */
    public static function exceptionHandler($ex)
    {
        $class = get_class($ex);
        $message = nl2br($ex->getMessage());
        $file = str_replace(KIRA_ROOT_PATH, '/', $ex->getFile());
        $line = $ex->getLine();
        $trace = $ex->getTraceAsString();
        $rn = PHP_EOL;

        if (!KIRA_DEBUG && $ex->getPrevious() === null) {
            $logger = App::logger();
            $logger->addTyped(
                "Class: {$class}{$rn}" .
                "Message: {$message}{$rn}" .
                "Source: {$file}:{$line}{$rn}{$rn}" .
                "Trace: {$trace}{$rn}",
                $logger::EXCEPTION
            );
        }

        if (isConsoleInterface()) {
            (new ColorConsole)->setColor('red')->setStyle('bold')
                ->addText($rn . $class . $rn . $rn)->setColor('brown')->setBgColor('blue')
                ->addText($message)->reset()->setColor('brown')
                ->addText($rn . $rn . 'Стек вызова в обратном порядке:' . $rn . $rn)
                ->addText($trace)->reset()
                ->draw($rn);
            return;
        }

        if (Request::isAjax()) {
            $trace = $ex->getTrace();
            $data = KIRA_DEBUG
                ? compact('message', 'class', 'file', 'line', 'trace')
                : ['message' => 'В ajax-запросе произошла ошибка.'];
            (new Response(500))->sendAsJson($data);
            return;
        }

        if (!headers_sent()) {
            header('500 Internal Server Error');
            header('Content-Type: text/html; charset=UTF-8');
        }

        if (KIRA_DEBUG) {
            echo Render::fetch('exception.htm', compact('class', 'message', 'file', 'line', 'trace'));
        } else {
            echo Render::fetch('exception_prod.htm', ['domain' => Env::domainName()]);
        }
    }

    /**
     * Перехват ошибок PHP
     *
     * Свое оформление, трассировка и логирование. Если текущий тип ошибки отключен в error_reporting, то пишем
     * ее в temp-каталог приложения, в kira_php_error.log. Иначе - выдаем в браузер.
     *
     * В случае фатальных ошибок трассировка вызовов уже неважна. К тому же я не могу ее получить почему-то. Поэтому
     * в таких сообщениях не будет стека вызовов.
     *
     * @param int    $code уровень ошибки в виде целого числа
     * @param string $msg  сообщение об ошибке в виде строки
     * @param string $file имя файла, в котором произошла ошибка
     * @param int    $line номер строки, в которой произошла ошибка
     * @return bool
     */
    public static function errorHandler($code, $msg, $file, $line)
    {
        $codes = [
            1     => 'FATAL ERROR',
            2     => 'WARNING',
            4     => 'PARSE ERROR',
            8     => 'NOTICE',
            16    => 'CORE ERROR',
            32    => 'CORE WARNING',
            64    => 'COMPILE ERROR',
            128   => 'COMPILE WARNING',
            256   => 'USER ERROR',
            512   => 'USER WARNING',
            1024  => 'USER NOTICE',
            2048  => 'STRICT',
            4096  => 'RECOVERABLE ERROR',
            8192  => 'DEPRECATED',
            16384 => 'USER DEPRECATED',
        ];
        $codeTxt = $codes[$code];

        $file = str_replace(KIRA_ROOT_PATH, '', $file);

        $rn = PHP_EOL;
        $stack_html = '';
        $console_msg = (new ColorConsole)->setStyle('bold')->setColor('red')
            ->addText($rn . $codeTxt . $rn . $rn)->setColor('brown')->setBgColor('blue')
            ->addText($msg)->reset()
            ->addText($rn . $rn)->setColor('brown')
            ->addText('Стек вызова в обратном порядке:' . $rn . $rn);

        if (($code & (E_ERROR | E_PARSE | E_COMPILE_ERROR)) == 0) {
            $trace = debug_backtrace();
            array_shift($trace);
            foreach ($trace as $i => $step) {
                $where = isset($step['file'])
                    ? str_replace(KIRA_ROOT_PATH, '', $step['file']) . ':' . $step['line']
                    : '';

                $func = isset($step['class'])
                    ? $step['class'] . $step['type'] . $step['function']
                    : $step['function'];

                if (isset($step['args'])) {
                    $args = $step['args'];
                    array_walk($args, function (&$i) {
                        if (is_string($i)) {
                            $i = "'{$i}'";
                        } elseif (is_array($i)) {
                            $i = '[array]';
                        } elseif (is_bool($i)) {
                            $i = $i ? 'true' : 'false';
                        } elseif (is_object($i)) {
                            $i = get_class($i);
                        }
                    });
                    $args = implode(', ', $args);
                } else {
                    $args = '';
                }

                $stack_html .= "<tr><td class='php-err-txtright'>{$where}</td><td>{$func}({$args})</td></tr>{$rn}";
                $console_msg->addText("#{$i} {$where} > {$func}({$args}){$rn}");
            }

            $stack_html = "
                <p>Стек вызова в обратном порядке:</p>
                <table class = 'php-err-stack'>
                    {$stack_html}
                </table>
            ";
        }

        if (error_reporting() & $code) {
            if (isConsoleInterface()) {
                $console_msg->reset()->draw();
            } else {
                if (!headers_sent()) {
                    header('500 Internal Server Error');
                    header('Content-Type: text/html; charset=UTF-8');
                }
                $msg = nl2br(htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'));
                echo Render::fetch('error_handler.htm', compact('codeTxt', 'msg', 'file', 'line', 'stack_html'));
            }
        } else {
            $info = $console_msg
                ->addText($rn . '---' . $rn . $rn)
                ->getClearText();
            file_put_contents(KIRA_TEMP_PATH . 'kira_php_error.log', $info, FILE_APPEND);
            return false;
        }

        if ($code & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR)) {
            exit();
        } else {
            return true;
        }
    }

    /**
     * Функция, работающая при завершении программы.
     *
     * Ошибки типа E_ERROR | E_PARSE | E_COMPILE_ERROR - т.е. фатальные ошибки, не ловятся через функцию, заданную в
     * set_error_handler(). Делаем так: буферизируем вообще любой вывод. Если случится вышеуказанная ошибка, тут
     * забираем буфер, выпиливаем уже нарисованное сообщение об ошибке от PHP и вызываем свой обработчик ошибок.
     *
     * Если ошибок не будет, просто отдаем буфер.
     *
     * Есть еще одна ситуация: кончилась память. Так же корректно ее обрабатываем: выделяем немного памяти, чтоб
     * сообщить об этой ошибке, вызываем обработчик и закругляемся.
     *
     * @todo Потерял первосточник. Где-то на Хабре.
     */
    public static function shutdown()
    {
        $error = error_get_last();
        if ($error && ($error['type'] & (E_ERROR | E_PARSE | E_COMPILE_ERROR))) {
            $output = ob_get_clean();
            echo preg_replace(
                "~(<br />\r?\n<font size='1'><table class='xdebug-error .*?</table></font>)~s",
                '',
                $output
            );
            if (strpos($error['message'], 'Allowed memory size') === 0) {
                ini_set('memory_limit', (intval(ini_get('memory_limit')) + 32) . 'M');
            }
            self::errorHandler($error['type'], $error['message'], $error['file'], $error['line']);
        }
        ob_end_flush();
    }
}
