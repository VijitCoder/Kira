<?php
namespace kira\utils;

/**
 * Функции работы со строками
 *
 * См. документацию, "Утилиты"
 */
class Strings
{
    /**
     * Константы для генерации пароля. Определяют обязательный набор символов, зайдествованных в пароле.
     * Можно использовать с побитовыми операторами, по аналогии c константами обработки ошибок PHP
     */
    const
        SET_LOWER = 1,
        SET_UPPER = 2,
        SET_DIGITS = 4,
        SET_SPECIAL = 8,
        SET_ALL = 15;

    /**
     * Приведение булева значения к строке
     */
    const
        BOOL_EN = 1,        // true|false
        BOOL_RU = 2,        // истина|ложь
        BOOL_YESNO_EN = 3,  // yes|no
        BOOL_YESNO_RU = 4,  // да|нет
        BOOL_DIGIT = 5;     // 1|0

    /**
     * Склонение слов в зависимости от числа.
     *
     * Не использует возможности движка по локализации. Для этого потребуется отдельный вызов
     * переводчика {@see App::t()}.
     *
     * Пример передаваемых данных в массиве $s = ['комментарий', 'комментария', 'комментариев']:
     * <pre>
     * 1 комментарий
     * 2 комментария
     * 5 комментариев
     * </pre>
     *
     * @param int   $num     число
     * @param array $strings набор слов
     * @param bool  $glued   объединить результат с числом? Объединение будет через пробел
     * @return string
     */
    public static function declination(int $num, array $strings, $glued = true): string
    {
        $mod100 = $num % 100;
        $mod10Mod100 = $mod100 % 10;

        $idx = (($mod100 < 10 || $mod100 > 20) && $mod10Mod100 >= 1 && $mod10Mod100 <= 4)
            ? ($mod10Mod100 == 1 ? 0 : 1)
            : 2;

        $phrase = $strings[$idx];

        return $glued ? $num . ' ' . $phrase : $phrase;
    }

    /**
     * Транслит с русского на английский
     *
     * Поддерживается только нижний регистр букв. Больше нигде не требовалось и не тестировалось.
     *
     * @param string $str исходная строка
     * @return string
     */
    public static function rus2eng(string $str): string
    {
        return strtr($str, self::dictionary('rus'));
    }

    /**
     * Транслит с английского на русский.
     *
     * Поддерживается только нижний регистр букв.
     *
     * Преобразование сделано только для <b>однобуквенных</b> (в русском эквиваленте) строк. Больше нигде не требовалось
     * и не тестировалось.
     *
     * @param string $str исходная строка
     * @return string
     */
    public static function eng2rus(string $str): string
    {
        return strtr($str, self::dictionary('eng'));
    }

    /**
     * Словарь транслита. Раскладка нестандартная, зато без мудренной фигни типа 'э'=>'e`'.
     *
     * @param string $keys rus|eng ключи массива
     * @return array
     */
    private static function dictionary(string $keys): array
    {
        return $keys == 'eng'
            ? [ //удалены 'ь', 'ъ'
                'a'   => 'а',
                'b'   => 'б',
                'v'   => 'в',
                'g'   => 'г',
                'd'   => 'д',
                'e'   => 'е',
                'jo'  => 'ё',
                'j'   => 'ж',
                'z'   => 'з',
                'i'   => 'и',
                'y'   => 'й',
                'k'   => 'к',
                'l'   => 'л',
                'm'   => 'м',
                'n'   => 'н',
                'o'   => 'о',
                'p'   => 'п',
                'r'   => 'р',
                's'   => 'с',
                't'   => 'т',
                'u'   => 'у',
                'f'   => 'ф',
                'h'   => 'х',
                'ts'  => 'ц',
                'ch'  => 'ч',
                'sh'  => 'ш',
                'sch' => 'щ',
                'yi'  => 'ы',
                'ye'  => 'э',
                'yu'  => 'ю',
                'ya'  => 'я',
            ]
            : [
                'а' => 'a',
                'б' => 'b',
                'в' => 'v',
                'г' => 'g',
                'д' => 'd',
                'е' => 'e',
                'ё' => 'jo',
                'ж' => 'j',
                'з' => 'z',
                'и' => 'i',
                'й' => 'y',
                'к' => 'k',
                'л' => 'l',
                'м' => 'm',
                'н' => 'n',
                'о' => 'o',
                'п' => 'p',
                'р' => 'r',
                'с' => 's',
                'т' => 't',
                'у' => 'u',
                'ф' => 'f',
                'х' => 'h',
                'ц' => 'ts',
                'ч' => 'ch',
                'ш' => 'sh',
                'щ' => 'sch',
                'ъ' => '',
                'ы' => 'yi',
                'ь' => '',
                'э' => 'ye',
                'ю' => 'yu',
                'я' => 'ya',
            ];
    }

    /**
     * Заглавная первая буква. Мультибайтная версия
     *
     * Не нашел "из коробки" мультибайтный ucfirst(). Сделал свой.
     *
     * @see http://php.net/manual/ru/function.ucfirst.php#57298
     *
     * @param string $str
     * @return string
     */
    public static function upperCaseFirst(string $str): string
    {
        $fc = mb_strtoupper(mb_substr($str, 0, 1));
        return $fc . mb_substr($str, 1);
    }

    /**
     * Строчная первая буква. Мультибайтная версия
     *
     * @param string $str
     * @return string
     */
    public static function lowerCaseFirst(string $str): string
    {
        $fc = mb_strtolower(mb_substr($str, 0, 1));
        return $fc . mb_substr($str, 1);
    }

    /**
     * Разбиение строки на части заданной длины в буквах.
     *
     * Безопасно для мультибайтных строк, т.е. не разделит букву посредине ее кода.
     *
     * Идея отсюда {@see http://php.net/manual/ru/function.chunk-split.php#107711}
     *
     * @param string $str исходный текст
     * @param int    $len длина куска в буквах
     * @param string $end символы конца строки
     * @return string
     */
    public static function wordChunk(string $str, int $len = 76, string $end = "\r\n"): string
    {
        $pattern = '~.{1,' . $len . '}~u';
        $str = preg_replace($pattern, '$0' . $end, $str);
        return rtrim($str, $end);
    }

    /**
     * Генератор пароля
     *
     * Возможные наборы символов: латинские буквы в обоих регистрах, цифры и некоторые спец.символы. Параметром задается
     * обязательная минимальная комбинация символов. Если требуемая длина пароля меньше количества обязательных наборов
     * символов, результат непредсказуем.
     *
     * Пример: в пароле обязательно должны быть буквы двух регистров и числа.
     * Маска: SET_LOWER | SET_UPPER | SET_DIGITS. Длину требовать минимум 3 символа, меньше не имеет смысла.
     *
     * По умолчанию пароль 10 символов, из всех наборов.
     *
     * Прим: unit-теста нет. Его реализация будет сложнее, чем сам этот метод.
     *
     * @param int $strength стойкость пароля, битовая маска. См. константы self::SET_*
     * @param int $length   требуемая длина пароля. Будет ровно столько, сколько требуется.
     * @return string
     */
    public static function generatePassword($strength = self::SET_ALL, $length = 10)
    {
        $sets = [
            self::SET_LOWER   => 'abcdefghijklmnopqrstuvwxyz',
            self::SET_UPPER   => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            self::SET_DIGITS  => '0123456789',
            self::SET_SPECIAL => '_-!@#$%^&`~',
        ];

        $setsLength = array_map('strlen', $sets);

        // собираем минимум под требуемую стойкость
        $customSet = '';
        $customLen = 0;
        $cnt = 0;
        $arr = [];
        foreach ($sets as $k => $v) {
            if ($k & $strength) {
                $arr[] = $v[rand(0, $setsLength[$k] - 1)];
                $customSet .= $v;
                $customLen += $setsLength[$k];
                $cnt++;
            }
        }

        // добираем до требуемой длины
        if ($cnt > $length) {
            $arr = array_slice($arr, 0, $length);
        } else {
            $length -= $cnt;
            for ($i = 0; $i < $length; $i++) {
                $arr[] = $customSet[rand(0, $customLen - 1)];
            }
        }

        shuffle($arr);
        return implode('', $arr);
    }

    /**
     * Генерация псевдослучайной строки из читабельных символов
     *
     * Генератор случайностей - openssl
     *
     * @param int $bytes
     * @return string
     */
    public static function randomString(int $bytes): string
    {
        if ($bytes < 1) {
            return '';
        }
        $str = openssl_random_pseudo_bytes($bytes);
        $str = base64_encode($str);
        return substr($str, 0, $bytes);
    }

    /**
     * Проверка: символ экранирован
     *
     * Сюда нужно передавать весь текст, идущий ДО проверяемого символа. Идея проверки: считаем обратные слеши с конца
     * текста. Если их будет нечетное количество, значит символ в исходном тексте экранирован.
     *
     * @param string $text весь текст до проверяемого символа
     * @return bool
     */
    public static function isShielded(string $text): bool
    {
        return preg_match('#\\\\+$#', $text, $match) && (strlen($match[0]) & 1);
    }

    /**
     * Превращение булева значения в строковое название
     *
     * Это не приведение типа. Значение проверяется по очень мягким требованиям, практически как boolval().
     *
     * @param mixed $value любое значение, об истинности которого можно утверждать
     * @param int   $type  тип строкового представления полученного значения, см. self::BOOL_*
     * @return string
     */
    public static function strBool($value, $type = self::BOOL_EN)
    {
        $value = Typecast::bool($value);
        switch ($type) {
            case self::BOOL_RU:
                return $value ? 'истина' : 'ложь';
            case self::BOOL_YESNO_EN:
                return $value ? 'yes' : 'no';
            case self::BOOL_YESNO_RU:
                return $value ? 'да' : 'нет';
            case self::BOOL_DIGIT:
                return (string)intval($value);
            default:
                return $value ? 'true' : 'false';
        }
    }

    /**
     * Преобразование html-тега [br] в перенос строки, принятый в текущей ОС
     *
     * Это обратная замена результата функции php::nl2br()
     *
     * @param string $text текст для обработки
     * @return string
     */
    public static function br2nl(string $text): string
    {
        return preg_replace('~<br\s*/?>~i', PHP_EOL, $text);
    }

    /**
     * Алиас для Strings::upperCaseFirst()
     *
     * @deprecated Будет удалена в v.3
     *
     * @param $str
     * @return string
     */
    public static function mb_ucfirst($str): string
    {
        return self::upperCaseFirst($str);

    }

    /**
     * Алиас для Strings::lowerCaseFirst()
     *
     * @deprecated Будет удалена в v.3
     *
     * @param $str
     * @return string
     */
    public static function mb_lcfirst($str): string
    {
        return self::lowerCaseFirst($str);

    }

    /**
     * Алиас для Strings::wordChunk()
     *
     * @deprecated Будет удалена в v.3
     *
     * @param string $str
     * @param int    $len
     * @param string $end
     * @return string
     */
    public static function word_chunk(string $str, int $len = 76, string $end = "\r\n"): string
    {
        return self::wordChunk($str, $len, $end);

    }
}
