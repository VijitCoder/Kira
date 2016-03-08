<?php
/**
 * Служебная фича. Не для использования в приложениях.
 *
 * Очень простой шабонизатор. Заменяет вставки {{var}} на значения переменных. Данные передаются в ассоциативном массиве
 * (хеше), поиск и замена - по ключам хеша.  Весь код - в одном скрипте :)
 *
 * Движку нужен шаблонизатор, чтобы некоторые свои html-ки не писать в скриптах в heredoc-стиле. Это напрягает.
 */

namespace engine\html;

class Render {
    /**
     * Замена переменных шаблона на данные.
     *
     * Подстановки в шаблоне должны быть оформлены в {{...}}
     *
     * Для удобства вызова путь не передаем, считая, что все шаблоны в этом же каталоге. Иначе прописываем относительный
     * путь из каталога с этим скриптом.
     *
     * TODO нормальное название методу придумай. Сейчас фантазии не хватает, но вызовы типа Render::render() не улыбают.
     *
     * @param string $file файл шаблона
     * @param array  $vars ассоциативный массив данных для замены
     * @return string готовый текст шаблона
     */
    public static function make($file, $vars = [])
    {
        $pattern = file_get_contents(__DIR__ . '/' . $file);

        preg_match_all('~\{\{(.*?)\}\}~', $pattern, $match, PREG_PATTERN_ORDER);

        $lost = array_diff_key(array_flip($match[1]), $vars);
        $lost = array_flip($lost);

        array_walk($lost, function (&$item) {
            $item = '{{' . $item . '}}';
        });

        $pattern = str_replace($lost, '', $pattern);

        if (!$vars) {
            return $pattern;
        }

        foreach ($vars as $k => $v) {
            unset($vars[$k]);
            $vars['{{'.$k.'}}'] = $v;
        }

        return str_replace(array_keys($vars), $vars, $pattern);
    }
}
