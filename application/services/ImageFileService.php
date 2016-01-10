<?php
/**
 * Сервис по работе с файлами картинок
 */
namespace app\services;

use engine\App;

class ImageFileService
{
    //Относительный путь. ХАРДКОД. Пока нет смысла делать сложнее.
    const TEMP_DIR = 'files/avatar/';

    /**
     * Загрузка картинки
     * @param array $file данные по одному файлу из массива $_FILES
     * @param array $constraints ограничения, накладываемые на картинку
     * @return string либо ошибка либо URL. Различать по слешу в начале.
     */
    public static function loadImage($file, $constraints)
    {
        //var_dump($file);//DBG

        if ($file['error']) {
            //Возврат встроенных ошибок протокола
            return self::_errorHandler($file['error']);
        }

        $tmpFile = $file['tmp_name'];

        //проверяем, что файл загружен через HTTP POST
        if (!is_uploaded_file($tmpFile)) {
            return self::_errorHandler(101);
        }

        $fileSize = $file['size'] / 1024; //В килобайтах

        //Проверка минимума веса
        if ($fileSize < $constraints['minWeight']) {
            return self::_errorHandler(102);
        }
        //Проверка максимума веса. В мегабайтах
        if ($fileSize / 1024 > $constraints['maxWeight']) {
            return self::_errorHandler(103);
        }

        //Пробуем получить данные по картинке
        if (!$data = getimagesize($tmpFile)) {
            return self::_errorHandler(105);
        }

        //Проверяем тип загруженного файла
        //Можно разными способами. Получить из массива $_FILES, но там мало типов поддерживается.
        //Расширение "fileinfo" знает о файлах гораздо больше. Здесь полагаемся на вывод getimagesize()
        $ext = str_replace(', ', '|', $constraints['format']);
        $data['mime'] = str_replace('jpeg', 'jpg', strtolower($data['mime']));
        if (!preg_match("~{$ext}~", $data['mime'], $m)) {
            return self::_errorHandler(105);
        }
        $ext = '.'.$m[0];

        //Требование к минимальному размеру изображения
        if (min($data[0], $data[1]) < $constraints['minSize']) {
            return self::_errorHandler(106);
        }

        $relPath = self::TEMP_DIR;
        $fn = uniqid('ava_') . '.png';
        $pathAndFile = ROOT_PATH . $relPath . $fn;
        $fileURI = ROOT_URL . $relPath . $fn;

        //делаем тумбу
        //В размер по меньшей стороне..
        $res = self::resize($tmpFile, $pathAndFile, $constraints['w'], $constraints['h']);
        if (is_int($res)) {
            return self::_errorHandler($res);
        }
        //..и обрезаем не входящее в пропорции. Позиция по центру картинки.
        $res = self::crop($pathAndFile, $pathAndFile, $constraints['w'], $constraints['h']);
        if (is_int($res)) {
            return self::_errorHandler($res);
        }

        chmod($pathAndFile, 0666);

        return $fileURI;
    }

    /**
     * Управляем ошибками загрузки и валидации файлов. Из кода в текст на заданном языке.
     * @param int $errcode код
     * @return string
     */
    private static function _errorHandler($errcode)
    {
        switch ($errcode) {
            //ошибки протокола
            case 1: $msg = 'размер принятого файла превысил максимально допустимый размер, ';
                    $msg .= 'который задан директивой upload_max_filesize.'; break;
            case 2: $msg = "Размер загружаемого файла превысил значение MAX_FILE_SIZE, указанное в HTML-форме"; break;
            case 3: $msg = 'Загружаемый файл был получен только частично.'; break;
            case 4: $msg = 'Не выбран файл для загрузки.'; break;

            //мои сообщения
             case 99:  $msg = 'Тип изображения не поддерживается GD'; break;
             case 100: $msg = 'Ошибка перемещения файла'; break;
            case 101: $msg = 'Файл не был загружен при помощи HTTP POST'; break;
             case 102: $msg = 'Файл слишком легкий'; break;
             case 103: $msg = 'Файл слишком тяжелый'; break;
             //case 104: $msg = 'Файл не является картинкой'; break; //в пользу 105
             case 105: $msg = 'Недопустимый формат файла'; break;
             case 106: $msg = 'Изображение слишком маленькое'; break;
             case 107: $msg = 'Изображение слишком большое'; break;
             case 108: $msg = 'Невозможно получить информацию об изображении'; break;
            default:  $msg = 'CODE - неизвестный код ошибки';
        }

        //На боевом ошибки протокола и 100-ю не показываем, просто общий ответ.
        if ($errcode < 101 && !DEBUG) $msg='Ошибка загрузки файла';

        return App::t($msg, ['CODE' => $errcode]);
    }

    /**
     * Меняем размеры. Нужно сохранять пропорции, меньшую сторону подгоняем под размер, потом обрезка.
     *
     * @param string $input имя исходного файла
     * @param string $output имя целевого файла
     * @param int $w_tgt размеры целевой картинки (tgt - target)
     * @param int $h_tgt
     * @return int | null код ошибки или ничего. Файл сохраняется в png-формате по указанному имени,
     * клиентский код и так его знает.
     */
    private static function resize($input, $output, $w_tgt, $h_tgt)
    {
        list($w_org, $h_org, $imgType) = getimagesize($input);
        if (!$w_org || !$h_org) {
            return 108; //Невозможно получить информацию об изображении
        }

        //если картинка уже в размерах, ничего не нужно обрабатывать
        if ($w_org == $w_tgt && $h_org == $h_tgt) {
           return move_uploaded_file($input, $output) ? : 100; //Ошибка перемещения файла
        }

           /*
        $imgType содержит НОМЕР, который соответствует типу файла. Очевидно такое соответствие зашито в GD.
        Готовим массив для перехода от номера к типу. Можно анализировать $imgType['mime'], проще не станет.
        Нумерация типов, возращаемая getimagesize() начинается с 1, а массив types[] - c нуля, поэтому "-1".
        */
        $imgType--;
        $types = ['gif', 'jpeg', 'png'];

        //Кроме прочего, переход от номера к текстовому названию типа файла.
        if (isset($types[$imgType])) {
            $imgType = $types[$imgType];
            $func = 'imagecreatefrom' . $imgType;
            $img = $func($input);
        } else {
           return 99; //тип изображения не поддерживается GD
        }

        //Подгонять картинку будем под большую сторону, пропорционально. Лишнее потом обрежем в другой функции.
        $prop = min($h_org / $h_tgt, $w_org / $w_tgt);
        $h_tgt = round($h_org / $prop);
        $w_tgt = round($w_org / $prop);

//DBG
//      echo "$h_org x $w_org<br>";
//      echo "$prop: $h_tgt x $w_tgt";
//      return false;

        $img_tgt = imagecreatetruecolor($w_tgt, $h_tgt);
        imagecopyresampled($img_tgt, $img, 0, 0, 0, 0, $w_tgt, $h_tgt, $w_org, $h_org);

        imagepng($img_tgt, $output);//всегда сохраняем в png. Мне так нравится.
    }

    /**
     * Функция обрезки. Всегда обрезаем по центру.
     * Если один из размеров не будет задан, он приравнивается к оригиналу.
     * Если размер вырезки будет больше оригинала, он опять же приравнивается к оригиналу.
     * Под размером подразумевается ширина и/или высота.
     *
     * @param string $input имя исходного файла
     * @param string $output имя целевого файла
     * @param int $w_tgt размеры целевой картинки (tgt - target)
     * @param int $h_tgt
     * @return int | null код ошибки или ничего. Файл сохраняется в png-формате по указанному имени,
     * клиентский код и так его знает.
     */
    private static function crop($input, $output, $w_tgt, $h_tgt)
    {
        list($w_org, $h_org) = getimagesize($input);
        if (!$w_org || !$h_org) {
            return 108; //Невозможно получить информацию об изображении
        }

        //если картинка уже в размерах, ничего не нужно обрабатывать
        if ($w_org == $w_tgt AND $h_org == $h_tgt) {
            return;
        }

        $img = imagecreatefrompng($input);

        //Проверяем, чтобы оригинал был задан и был больше целевой картинки, иначе чего обрезать-то?
        if (!$w_tgt || $w_tgt > $w_org) { $w_tgt = $w_org; }
        if (!$h_tgt || $h_tgt > $h_org) { $h_tgt = $h_org; }

        //Теперь центруем область вырезки
        $xc = round(($w_org - $w_tgt) / 2);
        $yc = round(($h_org - $h_tgt) / 2);

        $img_tgt = imagecreatetruecolor($w_tgt, $h_tgt);
        imagecopy($img_tgt, $img, 0, 0, $xc, $yc, $w_tgt, $h_tgt);

        return imagepng($img_tgt, $output);
    }

    /**
     * Перемещаем аватарку на постоянное хранение
     * @param string $from откуда. Относительный путь от корня сайта
     * @param int $id id юзера, кому принадлежит картинка
     * @return string либо ошибка либо URL. Различать по слешу в начале.
     */
    public static function moveToShard($from, $id)
    {
        $shard = $id % 10;
        //необходимый костыль с поправкой на каталоги тестового задания
        $from = str_replace(ROOT_URL, '', $from);
        $to = self::TEMP_DIR  . "{$shard}/{$id}.png";

        //Код 100 - Ошибка перемещения файла
        return rename(ROOT_PATH . $from, ROOT_PATH . $to) ? '/' . $to : self::_errorHandler(100);
    }
}
