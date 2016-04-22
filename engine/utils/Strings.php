<?php
/**
 * Функции работы со строками. По сути - это хелпер и классовая обертка тут точно не нужна. Но тогда автозагрузчик его
 * не подключит.
 */

namespace engine\utils;

class Strings
{
    /**
     * Склонение слов в зависимости от числа.
     *
     * Не использует возможности движка по локализации. Для этого потребуется отдельный вызов переводчика {@see App::t()}
     *
     * Пример передаваемых данных в массиве $s = ['комментарий', 'комментария', 'комментариев']:
     * 1 комментарий
     * 2 комментария
     * 5 комментариев
     *
     * @param int   $n     число
     * @param array $s     набор слов
     * @param bool  $glued объединить результат с числом? Объединение будет через пробел
     * @return string
     */
    public static function declination($n, array $s, $glued = true)
    {
        $n = $n % 100;
        $ln = $n % 10;
        $phrase = $s[(($n < 10 || $n > 20) && $ln >= 1 && $ln <= 4)
            ? (($ln == 1) ? 0 : 1)
            : 2];
        return $glued ? $n . ' ' . $phrase : $phrase;
    }

    /**
     * Транслит с русского на английский.
     *
     * @param string $str исходная строка
     * @return string
     */
    public static function rus2eng($str)
    {
        return strtr($str, self::_dictionary('rus'));
    }

    /**
     * Транслит с английского на русский.
     *
     * Преобразование сделано только для <b>однобуквенных</b> (в русском эквиваленте) строк. Больше нигде не требовалось
     * и не тестировалось.
     *
     * @param string $str исходная строка
     * @return string
     */
    public static function eng2rus($str)
    {
        return strtr($str, self::_dictionary('eng'));
    }

    /**
     * Словарь транслита. Раскладка нестандартная, зато без мудренной фигни типа 'э'=>'e`'.
     *
     * @param string $keys rus|eng ключи массива
     * @return array
     */
    private static function _dictionary($keys)
    {
        return $keys == 'eng'
            ? [ //удалены 'ь', 'ъ'
                'a'  => 'а', 'b' => 'б', 'v' => 'в', 'g' => 'г', 'd' => 'д', 'e' => 'е', 'jo' => 'ё', 'j' => 'ж',
                'z'  => 'з', 'i' => 'и', 'y' => 'й', 'k' => 'к', 'l' => 'л', 'm' => 'м', 'n' => 'н', 'o' => 'о',
                'p'  => 'п', 'r' => 'р', 's' => 'с', 't' => 'т', 'u' => 'у', 'f' => 'ф', 'h' => 'х', 'ts' => 'ц',
                'ch' => 'ч', 'sh' => 'ш', 'sch' => 'щ', 'yi' => 'ы', 'ye' => 'э', 'yu' => 'ю', 'ya' => 'я',
            ]
            : [
                'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'jo', 'ж' => 'j',
                'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
                'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts',
                'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'yi', 'ь' => '', 'э' => 'ye', 'ю' => 'yu',
                'я' => 'ya',
            ];
    }

    /**
     * Заглавная первая буква. Мультибайтная версия.
     *
     * Не нашел "из коробки" мультибайтный ucfirst(). Сделал свой.
     * @see http://php.net/manual/ru/function.ucfirst.php#57298
     *
     * @param string $str
     * @return string
     */
    public static function mb_ucfirst($str)
    {
        $fc = mb_strtoupper(mb_substr($str, 0, 1));
        return $fc . mb_substr($str, 1);
    }

    /**
     * Строчная первая буква. Мультибайтная версия.
     *
     * @param string $str
     * @return string
     */
    public static function mb_lcfirst($str)
    {
        $fc = mb_strtolower(mb_substr($str, 0, 1));
        return $fc . mb_substr($str, 1);
    }

    /**
     * Разбиение строки на части заданной длины в буквах.
     *
     * Безопасно для мультибайтных строк. Т.е. не разделит букву посредине ее кода. Такой проблемой страдают php-функции
     * chunk_split() и wordwrap() (в случае, когда ей разрешено разбивать слова). Эти функции делят строку по байтам.
     *
     * При указании требуемой длины следует учитывать символы конца строки. Функция их добавляет к итоговой длине.
     *
     * Идея отсюда {@see http://php.net/manual/ru/function.chunk-split.php#107711}
     *
     * @param string $str исходный текст
     * @param int    $len длина куска в буквах
     * @param string $end символы конца строки
     * @return string
     */
    public static function word_chunk($str, $len = 76, $end = "\r\n")
    {
        $pattern = '~.{1,' . $len . '}~u';
        $str = preg_replace($pattern, '$0' . $end, $str);
        return rtrim($str, $end);
    }

    /*
     * Константы для генерации пароля. Определяют обязательный набор символов, зайдествованных в пароле.
     * Можно использовать с побитовыми операторами, по аналогии c константами обработки ошибок PHP,
     * {@see http://php.net/manual/ru/errorfunc.constants.php}
     */
    const
        SET_LOWER = 1,
        SET_UPPER = 2,
        SET_DIGITS = 4,
        SET_SPECIAL = 8,
        SET_ALL = 15;

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
     * @param int $requireSet обязательный наборов символов, битовая маска. См. константы self::SET_*
     * @param int $length     требуемая длина пароля. Будет ровно столько, сколько требуется.
     * @return string
     */
    public static function generatePassword($requireSet = self::SET_ALL, $length = 10)
    {
        $sets = [
            self::SET_LOWER   => 'abcdefghijklmnopqrstuvwxyz',
            self::SET_UPPER   => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            self::SET_DIGITS  => '0123456789',
            self::SET_SPECIAL => '_-!@#$%^&`~',
        ];

        $setsLength = array_map('strlen', $sets);

        // обязательный минимум
        $customSet = '';
        $customLen = 0;
        $cnt = 0;
        foreach ($sets as $k => $v) {
            if ($k & $requireSet) {
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
}
