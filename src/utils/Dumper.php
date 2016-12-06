<?php
namespace kira\utils;

/**
 * Дампер позаимствован из Yii 1.x, класс CVarDumper.
 *
 * См. документацию, "Утилиты"
 *
 * @author    Qiang Xue <qiang.xue@gmail.com>
 * @link      http://www.yiiframework.com/
 * @copyright 2008-2013 Yii Software LLC
 * @license   http://www.yiiframework.com/license/
 */
class Dumper
{
    private static $_objects;
    private static $_output;
    private static $_depth;

    /**
     * Выводим дамп переменной
     *
     * Метод работает аналогично var_dump и print_r, но возвращаем информацию в более приглядном виде.
     *
     * @param mixed $var       переменная, с которой нужно получить дамп
     * @param int   $depth     максимальная глубина вложений в данных переменной, на которую должен идти дампер
     * @param bool  $highlight нужно ли подсвечивать (раскрашивать) результат. Подсветка средствами PHP
     */
    public static function dump($var, int $depth = 10, bool $highlight = true)
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo self::dumpAsString($var, $depth, $highlight);
    }

    /**
     * Дамп переменной с завешением приложения
     * @param mixed $var       переменная, с которой нужно получить дамп
     * @param int   $depth     максимальная глубина вложений в данных переменной, на которую должен идти дампер
     * @param bool  $highlight нужно ли подсвечивать (раскрашивать) результат. Подсветка средствами PHP
     */
    public static function dumpEx($var, int $depth = 10, bool $highlight = true)
    {
        self::dump($var, $depth, $highlight);
        exit;
    }

    /**
     * Возвращаем дамп переменной в строку
     * @param mixed $var       переменная, с которой нужно получить дамп
     * @param int   $depth     максимальная глубина вложений в данных переменной, на которую должен идти дампер
     * @param bool  $highlight нужно ли подсвечивать (раскрашивать) результат. Подсветка средствами PHP
     * @return string
     */
    public static function dumpAsString($var, int $depth = 10, bool $highlight = true)
    {
        self::$_output = '';
        self::$_objects = array();
        self::$_depth = $depth;
        self::dumpInternal($var, 0);
        if ($highlight) {
            $result = highlight_string("<?php\n" . self::$_output . "\n", true);
            self::$_output = preg_replace('/&lt;\\?php<br \\/>/', '', $result, 1);
        }
        return self::$_output;
    }

    /**
     * Сборка ответа о данных переменной. Рекурсия.
     * @param mixed $var   переменная, с которой нужно получить дамп
     * @param int   $level текущий уровень вложения в данных переменной
     */
    private static function dumpInternal($var, int $level)
    {
        switch (gettype($var)) {
            case 'boolean':
                self::$_output .= $var ? 'true' : 'false';
                break;
            case 'integer':
                self::$_output .= "$var";
                break;
            case 'double':
                self::$_output .= "$var";
                break;
            case 'string':
                self::$_output .= "'" . addslashes($var) . "'";
                break;
            case 'resource':
                self::$_output .= '{resource}';
                break;
            case 'NULL':
                self::$_output .= 'null';
                break;
            case 'array':
                if (self::$_depth <= $level) {
                    self::$_output .= 'array(...)';
                } elseif (empty($var)) {
                    self::$_output .= 'array()';
                } else {
                    $keys = array_keys($var);
                    $spaces = str_repeat(' ', $level * 4);
                    self::$_output .= "array\n" . $spaces . '(';
                    foreach ($keys as $key) {
                        self::$_output .= "\n" . $spaces . '    ';
                        self::dumpInternal($key, 0);
                        self::$_output .= ' => ';
                        self::dumpInternal($var[$key], $level + 1);
                    }
                    self::$_output .= "\n" . $spaces . ')';
                }
                break;
            case 'object':
                if (($id = array_search($var, self::$_objects, true)) !== false) {
                    self::$_output .= get_class($var) . '#' . ($id + 1) . '(...)';
                } elseif (self::$_depth <= $level) {
                    self::$_output .= get_class($var) . '(...)';
                } else {
                    $id = array_push(self::$_objects, $var);
                    $className = get_class($var);
                    $members = (array)$var;
                    $spaces = str_repeat(' ', $level * 4);
                    self::$_output .= "$className#$id\n" . $spaces . '(';
                    foreach ($members as $key => $value) {
                        $keyDisplay = strtr(trim($key), array("\0" => ':'));
                        self::$_output .= "\n" . $spaces . "    [$keyDisplay] => ";
                        self::dumpInternal($value, $level + 1);
                    }
                    self::$_output .= "\n" . $spaces . ')';
                }
                break;
            default:
                self::$_output .= '{unknown}';
        }
    }
}
