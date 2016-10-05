<?php
namespace engine;

use engine\html\Render;

/**
 * Перехватчик исключений, обработчики ошибок.
 *
 * Как правило, они назначены в autoloader.php, вызываются внутренним механизмом PHP и не ожидают прямого обращения
 * из кода приложения.
 */
class Handlers
{
    /**
     * Перехватчик исключений.
     *
     * Ловит исключения, которые не были пойманы ранее. Последний шанс обработать ошибку. Например, записать в лог или
     * намылить админу. Можно так же вежливо откланяться юзеру.
     *
     * После выполнения этого обработчика программа остановится, обеспечено PHP.
     *
     * Если указано предыдущее исключение, отсюда не пишем в лог. Сей факт указывает на то, что реальное исключение
     * уже было поймано и обработано. Считаем, что необходимость логирования была решена в предыдущих обработчиках.
     *
     * @param Exception $ex
     */
    public static function exceptionHandler($ex)
    {
        $class = get_class($ex);
        $message = nl2br($ex->getMessage());
        $file = str_replace(ROOT_PATH, '/', $ex->getFile());
        $line = $ex->getLine();
        $trace = $ex->getTraceAsString();

        if (!headers_sent()) {
            header('500 Internal Server Error');
            header('Content-Type: text/html; charset=UTF-8');
        }

        if (DEBUG) {
            echo Render::fetch('exception.htm', compact('class', 'message', 'file', 'line', 'trace'));
        } else {
            echo Render::fetch('exception_prod.htm', ['domain' => Env::domainName()]);
            if ($ex->getPrevious() === null) {
                App::log()->addTyped(
                    "Class: $class" . PHP_EOL .
                    "Message: $message" . PHP_EOL .
                    "Source: $file:$line" . PHP_EOL . PHP_EOL .
                    "Trace: $trace",
                    Log::EXCEPTION
                );
            }
        }
    }

    /**
     * Перехват ошибок PHP
     *
     * Свое оформление, трассировка и логирование. Если текущий тип ошибки отключен в error_reporting, то пишем
     * ее в temp-каталог приложения, в kira_php_error.log. Иначе - выдаем в браузер.
     *
     * Согласно мануала, такая функция вызывается независимо от настройки error_reporting. Поэтому проверяем, требуется
     * ли сообщать о полученной ошибке. Если да - рисуем ответ, иначе скидываем сообщение в лог. Обычно бывает так:
     * на локалке/деве всё включено - будут валиться сообщения в браузер. На проде все отключено - логируем.
     *
     * Опять же по мануалу, если этот обработчик не прервет выполнение вызвав die(), то программа продолжится. Если
     * вернет FALSE - ошибку получит стандартный обработчик и мы увидим еще и его сообщение. Поэтому делаем так:
     * если код соответствует какой-то ERROR - выходим; иначе возвращаем TRUE, чтоб стандартный обработчик
     * не дублировал сообщение в своем стиле.
     *
     * В случае фатальных ошибок трассировка вызовов уже неважна. К тому же я не могу ее получить почему-то. Поэтому
     * в таких сообщениях не будет стека вызовов.
     *
     * По константам кодов см. {@see http://php.net/manual/ru/errorfunc.constants.php}
     * Ликбез: {@link https://habrahabr.ru/post/134499/}
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

        $file = str_replace(ROOT_PATH, '', $file);

        $stack_output = $log_data = '';

        if (($code & (E_ERROR | E_PARSE | E_COMPILE_ERROR)) == 0) {
            $trace = array_reverse(debug_backtrace());
            array_pop($trace);
            foreach ($trace as $step) {
                $where = isset($step['file']) ? str_replace(ROOT_PATH, '', $step['file']) . ':' . $step['line'] : '';

                $func = isset($step['class'])
                    ? $step['class'] . $step['type'] . $step['function']
                    : $step['function'];

                if (isset($step['args'])) {
                    $args = $step['args'];
                    array_walk($args, function (&$i) {
                        if (is_string($i)) {
                            $i = "'$i'";
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

                $stack_output .= "<tr><td class='php-err-txtright'>$where</td><td>$func($args)</td></tr>" . PHP_EOL;
                $log_data .= "$where > $func($args)" . PHP_EOL;
            }

            $stack_output = "
                <p>Стек вызова в хронологическом порядке:</p>
                <table class = 'php-err-stack'>
                    {$stack_output}
                </table>
            ";
        }

        if (error_reporting() & $code) {
            if (!isset($_SERVER['REQUEST_URI'])) { // работаем в консоли
                echo $log_data;
            } else {
                if (!headers_sent()) {
                    header('500 Internal Server Error');
                    header('Content-Type: text/html; charset=UTF-8');
                }
                $msg = nl2br(htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'));
                echo Render::fetch('error_handler.htm', compact('codeTxt', 'msg', 'file', 'line', 'stack_output'));
            }
        } else {
            $rn = PHP_EOL;
            $log_data .= "$rn{$codeTxt}$rn$rn\t{$msg}$rn$rn";
            $log_data .= 'at ' . date('Y.m.d. H:i:s') . "$rn---$rn$rn";
            file_put_contents(TEMP_PATH . 'kira_php_error.log', $log_data, FILE_APPEND);
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
     * TODO Потерял первосточник. Где-то на Хабре.
     */
    public static function shutdown()
    {
        $error = error_get_last();
        if ($error && ($error['type'] & (E_ERROR | E_PARSE | E_COMPILE_ERROR))) {
            $output = ob_get_clean();
            echo preg_replace("~(<br />\r?\n<font size='1'><table class='xdebug-error .*?</table></font>)~s", '',
                $output);
            if (strpos($error['message'], 'Allowed memory size') === 0) {
                ini_set('memory_limit', (intval(ini_get('memory_limit')) + 32) . 'M');
            }
            self::errorHandler($error['type'], $error['message'], $error['file'], $error['line']);
        }
        ob_end_flush();
    }
}
