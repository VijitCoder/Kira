<?php
namespace kira;

use kira\exceptions\DbException;
use kira\html\Render;
use kira\net\Request;
use kira\net\Response;
use kira\utils\System;
use kira\utils\ColorConsole;
use kira\web\Env;
use kira\core\App;

/**
 * Перехватчик исключений, обработчики ошибок.
 *
 * См. документацию, "Перехват ошибок"
 *
 * @internal
 */
class Handlers
{
    /**
     * Коды PHP шибок и их расшифровка
     */
    const ERROR_CODES = [
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

    /**
     * Перехватчик исключений
     *
     * Ловит исключения, которые не были пойманы ранее. Разматывает стек перехваченных исключений через
     * \Throwable::$previous. Пишет в лог, если на сайте отключена отладка. Вообще не логируется исключение базы данных,
     * у такого исключения свое логирование.
     *
     * После выполнения этого обработчика программа остановится, обеспечено PHP.
     *
     * @param \Throwable $ex исключение
     */
    public static function exceptionHandler(\Throwable $ex)
    {
        $stack = self::getExceptionsStack($ex);
        $currentEx = array_shift($stack);
        $exceptionsStackString = self::exceptionsStackToString($stack);

        $class = $currentEx['class'];
        $message = $currentEx['message'];
        $file = $currentEx['file'];
        $line = $currentEx['line'];
        $trace = $ex->getTraceAsString();
        $rn = PHP_EOL;

        if (!KIRA_DEBUG && !($ex instanceOf DbException)) {
            $logger = App::logger();
            $logger->addTyped(
                "Класс: {$class}{$rn}" .
                "Сообщение: {$message}{$rn}" .
                "Источник: {$file}:{$line}{$rn}{$rn}" .
                $exceptionsStackString .
                "Стек вызова в обратном порядке: {$trace}{$rn}",
                $logger::EXCEPTION
            );
        }

        if (System::isConsoleInterface()) {
            if (KIRA_DEBUG) {
                (new ColorConsole)->setColor('red')->setStyle('bold')
                    ->addText($rn . $class . $rn . $rn)->setColor('brown')->setBgColor('blue')
                    ->addText($message . $rn . $rn)->reset()->setColor('brown')
                    ->addText($exceptionsStackString)
                    ->addText("Стек вызова в обратном порядке:{$rn}{$rn}")
                    ->addText($trace)->reset()
                    ->draw($rn);
            } else {
                (new ColorConsole)->setColor('red')->setStyle('bold')
                    ->addText("{$rn}Возникло исключение{$rn}")
                    ->reset()
                    ->draw($rn);
            }
            return;
        }

        if (Request::isAjax()) {
            $trace = $ex->getTrace();
            $data = KIRA_DEBUG
                ? compact('message', 'class', 'file', 'line', 'exceptionsStackString', 'trace')
                : ['message' => 'В ajax-запросе произошла ошибка.'];
            self::responseForAjax($data);
            return;
        }

        if (KIRA_DEBUG) {
            $view = 'exception.htm';
            $data = compact('class', 'message', 'file', 'line', 'exceptionsStackString', 'trace');
        } else {
            $view = 'exception_prod.htm';
            $data = ['domain' => Env::domainName()];
        }
        self::responseForHtml($view, $data);
    }

    /**
     * Разматываем стек перехваченных исключений, от текущего к самому первому
     *
     * @param \Throwable $ex текущее исключение
     * @return array
     */
    private static function getExceptionsStack(\Throwable $ex): array
    {
        $stack = [];

        do {
            $stack[] = [
                'class'   => get_class($ex),
                'message' => nl2br($ex->getMessage()),
                'file'    => str_replace(KIRA_ROOT_PATH, '/', $ex->getFile()),
                'line'    => $ex->getLine(),
            ];
        } while ($ex = $ex->getPrevious());

        return $stack;
    }

    /**
     * Собираем стек исключений в строку
     *
     * В силу простоты используемого шаблонизатора, я не могу в нем выполнить цикл по переданным данным. Приходится
     * делать это здесь.
     *
     * TODO может пересмотреть шаблонизатор? Но нужно помнить, что здесь обрабатываются падения кода, не надо черезмерно
     * усложнять решение.
     *
     * @param array $stack стек перехваченных исключений
     * @return string
     */
    private static function exceptionsStackToString(array $stack): string
    {
        if (!$stack) {
            return '';
        }

        $rn = PHP_EOL;
        $result = 'Стек перехваченных исключений' . $rn . $rn;
        foreach ($stack as $idx => $ex) {
            $result .=
                "#{$idx} {$ex['class']}: {$ex['message']}{$rn}"
                . "{$ex['file']}:{$ex['line']}{$rn}{$rn}";
        }

        return $result;
    }

    /**
     * Перехват ошибок PHP
     *
     * Свое оформление, трассировка и логирование. Если текущий тип ошибки отключен в error_reporting, то пишем
     * подробности в temp-каталог приложения, в kira_php_error.log, а в браузер - короткий неинформативный текст.
     * Если же тип ошибки включен, выдаем в браузер все, как есть.
     *
     * @param int    $code     уровень ошибки в виде целого числа
     * @param string $message  сообщение об ошибке в виде строки
     * @param string $fileName имя файла, в котором произошла ошибка
     * @param int    $line     номер строки, в которой произошла ошибка
     * @return bool
     */
    public static function errorHandler($code, $message, $fileName, $line)
    {
        $rn = PHP_EOL;
        $fileName = str_replace(KIRA_ROOT_PATH, '', $fileName);
        $codeTxt = self::ERROR_CODES[$code];
        $stack = self::parseStackTrace($code);

        $console_msg = (new ColorConsole)->setStyle('bold')->setColor('red')
            ->addText($rn . $codeTxt . $rn . $rn)->setColor('brown')->setBgColor('blue')
            ->addText($message)->reset()
            ->addText($stack['console']);

        if (error_reporting() & $code) {
            if (System::isConsoleInterface()) {
                $console_msg->draw($rn);
            } else if (Request::isAjax()) {
                $stack_ajax = &$stack['ajax'];
                self::responseForAjax(compact('codeTxt', 'message', 'fileName', 'line', 'stack_ajax'));
            } else {
                $message = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
                $stack_html = &$stack['html'];
                self::responseForHtml(
                    'error_handler.htm',
                    compact('codeTxt', 'message', 'fileName', 'line', 'stack_html')
                );
            }
        } else {
            if (System::isConsoleInterface()) {
                echo 'Произошла ошибка PHP' . $rn;
            } else if (Request::isAjax()) {
                self::responseForAjax(['message' => 'В ajax-запросе произошла ошибка PHP']);
            } else {
                self::responseForHtml('', 'Произошла ошибка PHP');
            }

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
     * Парсим трассировку вызова при возникновении ошибки PHP
     *
     * Результат возвращаем для всех возможных вариантов использования: для консоли, ajax-запроса или обычного запроса
     *
     * В случае фатальных ошибок трассировка вызовов уже неважна. К тому же я не могу ее получить почему-то. Поэтому
     * в таких сообщениях не будет стека вызовов.
     *
     * @param int $code код PHP ошибки
     * @return array ['console' => string, 'html' => string, 'ajax' => array]
     */
    private static function parseStackTrace(int $code)
    {
        $rn = PHP_EOL;
        $console = '';
        $html = '';
        $ajax = [];

        if (($code & (E_ERROR | E_PARSE | E_COMPILE_ERROR)) == 0) {
            $console = (new ColorConsole)
                ->addText($rn . $rn)->setColor('brown')
                ->addText('Стек вызова в обратном порядке:' . $rn . $rn);

            $trace = array_slice(debug_backtrace(), 2);
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

                $html .= "<tr><td class='php-err-txtright'>{$where}</td><td>{$func}({$args})</td></tr>{$rn}";
                $clearInfo = "{$where} > {$func}({$args})";
                $console->addText("#{$i} {$clearInfo}{$rn}");
                $ajax[] = $clearInfo;
            }

            $html = "
                <p>Стек вызова в обратном порядке:</p>
                <table class = 'php-err-stack'>
                    {$html}
                </table>
            ";

            $console = $console->reset()->getText();
        }

        return compact('console', 'html', 'ajax');
    }

    /**
     * Ответ на ajax-запрос
     *
     * Если ошибка произошла не в режиме отладки, заменяем передаваемые данные на типовую заглушку.
     *
     * @param array $data данные для передачи в ответе
     */
    private static function responseForAjax(array $data)
    {
        (new Response(500))->sendAsJson($data);
    }

    /**
     * Ответ на обычный запрос, когда браузер ожидает html
     *
     * @param string       $view название шаблона для заполнения
     * @param array|string $data данные в шаблон
     */
    private static function responseForHtml(string $view, $data)
    {
        if (!headers_sent()) {
            header('500 Internal Server Error');
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo($view ? Render::fetch($view, $data) : $data);
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
        if ($error && ($error['type'] & (E_ERROR | E_CORE_ERROR | E_PARSE | E_COMPILE_ERROR))) {
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
