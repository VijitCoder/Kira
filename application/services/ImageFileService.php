<?php
/**
 * Сервис по работе с файлами картинок
 */
namespace app\services;

use engine\App;

class ImageFileService
{
    /**
     * @var int код последней ошибки при работе с картинкой
     */
    private $_errorCode = 0;

    /**
     * Загрузка картинки
     *
     * @param array $file данные по одному файлу из массива $_FILES
     * @param array $conf конфигурация, описывающая требования к картинке
     * @return string | null имя файла на временном хранении ИЛИ ничего в случае ошибки
     */
    public function loadImage($file, $conf)
    {
        if ($file['error']) {
            return $this->_setErrorCode($file['error']);
        }

        $tmpFile = $file['tmp_name'];

        if (!is_uploaded_file($tmpFile)) {
            return $this->_setErrorCode(101);
        }

        $fileSize = $file['size'] / 1024;

        if ($fileSize < $conf['min_weight']) {
            return $this->_setErrorCode(102);
        }
        if ($fileSize / 1024 > $conf['max_weight']) {
            return $this->_setErrorCode(103);
        }

        if (!$data = getimagesize($tmpFile)) {
            return $this->_setErrorCode(105);
        }

        //Проверяем тип загруженного файла
        //Можно разными способами. Получить из массива $_FILES, но там мало типов поддерживается.
        //Расширение "fileinfo" знает о файлах гораздо больше. Здесь полагаемся на вывод getimagesize()
        $ext = str_replace(', ', '|', $conf['format']);
        $data['mime'] = str_replace('jpeg', 'jpg', strtolower($data['mime']));
        if (!preg_match("~{$ext}~", $data['mime'])) {
            return $this->_setErrorCode(105);
        }

        if (min($data[0], $data[1]) < $conf['min_size']) {
            return $this->_setErrorCode(106);
        }

        $fn = uniqid('ava_') . '.png';
        $file = $conf['path'] . $fn;

        //делаем тумбу
        //В размер по меньшей стороне..
        $res = self::resize($tmpFile, $file, $conf['w'], $conf['h']);
        if (is_int($res)) {
            return $this->_setErrorCode($res);
        }
        //..и обрезаем не входящее в пропорции. Позиция по центру картинки.
        $res = self::crop($file, $file, $conf['w'], $conf['h']);
        if (is_int($res)) {
            return $this->_setErrorCode($res);
        }

        chmod($file, 0666);

        return $fn;
    }

    /**
     * Меняем размеры. Нужно сохранять пропорции, меньшую сторону подгоняем под размер, потом обрезка.
     *
     * @param string $input  имя исходного файла
     * @param string $output имя целевого файла
     * @param int    $w_tgt  размеры целевой картинки (tgt - target)
     * @param int    $h_tgt
     * @return int | bool код ошибки или логический результат создания картинки.
     */
    private function resize($input, $output, $w_tgt, $h_tgt)
    {
        list($w_org, $h_org, $imgType) = getimagesize($input);
        if (!$w_org || !$h_org) {
            return 108; //Невозможно получить информацию об изображении
        }

        if ($w_org == $w_tgt && $h_org == $h_tgt) {
            return move_uploaded_file($input, $output) ?: 100; //Ошибка перемещения файла
        }

        /*
         * $imgType содержит НОМЕР, который соответствует типу файла. Очевидно такое соответствие зашито в GD.
         * Готовим массив для перехода от номера к типу. Можно анализировать $imgType['mime'], проще не станет.
         * Нумерация типов, возращаемая getimagesize() начинается с 1, а массив types[] - c нуля, поэтому "-1".
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

        $prop = min($h_org / $h_tgt, $w_org / $w_tgt);
        $h_tgt = round($h_org / $prop);
        $w_tgt = round($w_org / $prop);

//DBG
//      echo "$h_org x $w_org<br>";
//      echo "$prop: $h_tgt x $w_tgt";
//      return false;

        $img_tgt = imagecreatetruecolor($w_tgt, $h_tgt);
        imagecopyresampled($img_tgt, $img, 0, 0, 0, 0, $w_tgt, $h_tgt, $w_org, $h_org);

        return imagepng($img_tgt, $output);
    }

    /**
     * Функция обрезки. Всегда обрезаем по центру.
     * Если один из размеров не будет задан, он приравнивается к оригиналу.
     * Если размер вырезки будет больше оригинала, он опять же приравнивается к оригиналу.
     * Под размером подразумевается ширина и/или высота.
     *
     * @param string $input  имя исходного файла
     * @param string $output имя целевого файла
     * @param int    $w_tgt  размеры целевой картинки (tgt - target)
     * @param int    $h_tgt
     * @return int | bool код ошибки или логический результат создания картинки.
     */
    private function crop($input, $output, $w_tgt, $h_tgt)
    {
        list($w_org, $h_org) = getimagesize($input);
        if (!$w_org || !$h_org) {
            return 108; //Невозможно получить информацию об изображении
        }

        if ($w_org == $w_tgt AND $h_org == $h_tgt) {
            return;
        }

        $img = imagecreatefrompng($input);

        if (!$w_tgt || $w_tgt > $w_org) {
            $w_tgt = $w_org;
        }
        if (!$h_tgt || $h_tgt > $h_org) {
            $h_tgt = $h_org;
        }

        $xc = round(($w_org - $w_tgt) / 2);
        $yc = round(($h_org - $h_tgt) / 2);

        $img_tgt = imagecreatetruecolor($w_tgt, $h_tgt);
        imagecopy($img_tgt, $img, 0, 0, $xc, $yc, $w_tgt, $h_tgt);

        return imagepng($img_tgt, $output);
    }

    /**
     * Перемещаем аватарку на постоянное хранение
     *
     * @param string $file имя файла. Он должен лежать в каталоге аватаров на временном хранении
     * @param int    $id   id юзера, кому принадлежит картинка
     * @return string | null относительный URL в каталоге с аватарами ИЛИ ничего в случае ошибки
     */
    public function moveToShard($file, $id)
    {
        $path = App::conf('avatar.path');

        $from = $path . $file;

        $shard = $id % 10;
        $fn = "{$shard}/{$id}.png";
        $to = $path . $fn;

        //Код 100 - Ошибка перемещения файла
        return rename($from, $to) ? $fn : $this->_setErrorCode(100);
    }

    /**
     * Запоминаем код последней ошибки.
     *
     * Можно еще что-то предпринимать, например запись в лог. Хотя сейчас функция нужна, только чтобы покороче
     * прерывать выполнение в клиентских методах, на одну строку меньше.
     *
     * @param $code
     * @return void
     */
    private function _setErrorCode($code)
    {
        $this->_errorCode = $code;
    }

    /**
     * Текст последней ошибки на заданном языке.
     *
     * @return string
     */
    public function getLastError()
    {
        switch ($this->_errorCode) {
            case 0:
                $msg = 'Нет ошибок';
                break;

            //ошибки протокола
            case 1:
                $msg = 'размер принятого файла превысил максимально допустимый размер, ';
                $msg .= 'который задан директивой upload_max_filesize.';
                break;
            case 2:
                $msg = "Размер загружаемого файла превысил значение MAX_FILE_SIZE, указанное в HTML-форме";
                break;
            case 3:
                $msg = 'Загружаемый файл был получен только частично.';
                break;
            case 4:
                $msg = 'Не выбран файл для загрузки.';
                break;

            //мои сообщения
            case 99:
                $msg = 'Тип изображения не поддерживается GD';
                break;
            case 100:
                $msg = 'Ошибка перемещения файла';
                break;
            case 101:
                $msg = 'Файл не был загружен при помощи HTTP POST';
                break;
            case 102:
                $msg = 'Файл слишком легкий';
                break;
            case 103:
                $msg = 'Файл слишком тяжелый';
                break;
            //case 104: $msg = 'Файл не является картинкой'; break; //в пользу 105
            case 105:
                $msg = 'Недопустимый формат файла';
                break;
            case 106:
                $msg = 'Изображение слишком маленькое';
                break;
            case 107:
                $msg = 'Изображение слишком большое';
                break;
            case 108:
                $msg = 'Невозможно получить информацию об изображении';
                break;
            default:
                $msg = 'Неизвестный код ошибки';
        }

        if ($this->_errorCode < 101 && !DEBUG) {
            $msg = 'Ошибка загрузки файла';
        }

        return App::t($msg);
    }
}
