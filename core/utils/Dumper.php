<?php
/**
 * Дампер позаимствован из Yii 1.x, класс CVarDumper. Авторские реквизиты:
 * @author    Qiang Xue <qiang.xue@gmail.com>
 * @link      http://www.yiiframework.com/
 * @copyright 2008-2013 Yii Software LLC
 * @license   http://www.yiiframework.com/license/
 */

namespace core\utils;

class Dumper
{
    private static $_objects;
    private static $_output;
    private static $_depth;

    /**
     * Выводим дамп переменной.
     *
     * @param mixed   $var       variable to be dumped
     * @param integer $depth     maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param boolean $highlight whether the result should be syntax-highlighted
     */
    public static function dump($var, $depth = 10, $highlight = false)
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo self::dumpAsString($var, $depth, $highlight);
    }

    /**
     * Дамп переменной и выход.
     *
     * @param mixed $var
     * @param int   $depth
     * @param bool  $highlight
     */
    public static function dumpEx($var, $depth = 10, $highlight = true)
    {
        self::dump($var, $depth, $highlight);
        exit;
    }

    /**
     * Возвращаем дамп переменной в строку.
     *
     * Метод работает аналогично var_dump и print_r, но возвращаем информацию в более приглядном виде.
     *
     * @param mixed $var       переменная для дампа
     * @param int   $depth     максимальная глубина вложений в данных переменной, на которую должен идти дампер.
     * @param bool  $highlight флаг "подсвечивать результат". Подсветка средствами PHP
     * @return string строка, представляющая дампированное содержимое переменной
     */
    public static function dumpAsString($var, $depth = 10, $highlight = true)
    {
        self::$_output = '';
        self::$_objects = array();
        self::$_depth = $depth;
        self::_dumpInternal($var, 0);
        if ($highlight) {
            $result = highlight_string("<?php\n" . self::$_output, true);
            self::$_output = preg_replace('/&lt;\\?php<br \\/>/', '', $result, 1);
        }
        return self::$_output;
    }

    /**
     * Сборка ответа о данных переменной. Рекурсия.
     *
     * @param mixed   $var   переменная для дампа
     * @param integer $level глубина вложений
     */
    private static function _dumpInternal($var, $level)
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
                        self::_dumpInternal($key, 0);
                        self::$_output .= ' => ';
                        self::_dumpInternal($var[$key], $level + 1);
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
                        self::$_output .= self::_dumpInternal($value, $level + 1);
                    }
                    self::$_output .= "\n" . $spaces . ')';
                }
                break;
            case 'unknown type':
                ;
            default:
                self::$_output .= '{unknown}';
        }
    }
}
