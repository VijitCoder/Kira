<?php
namespace kira\validation\validators;

use kira\core\App;

/**
 * Валидатор пароля
 *
 * В пароле разрешены следующие наборы символов:
 * <ul>
 *  <li>цифры</li>
 *  <li>буквы в нижнем регистре</li>
 *  <li>буквы в верхнем регистре</li>
 *  <li>спецсимволы: _ - ! @ # $ % ^ & ` ~</li>
 * </ul>
 *
 * Буквы в верхнем/нижнем регистре считаются разными наборами при проверке минимальной комбинации символов.
 *
 * Настройки валидатора (указаны значения по умолчанию):
 * <pre>
 * [
 *    'min_length' => 0,      // минимальная длина пароля. 0 = любая длина подходит.
 *    'min_combination' => 1, // минимальная комбинация наборов символов в пароле
 *    'glue' => ' ',          // чем склеить ошибки валидации. Их может быть несколько, применится php::implode()
 * ]
 * </pre>
 */
class Password extends AbstractValidator
{
    /**
     * Настройки валидатора по умолчанию
     * @var array
     */
    protected $options = [
        'min_length'      => 1,
        'min_combination' => 0,
        'glue'            => ' ',
    ];

    /**
     * Валидатор пароля
     * @param mixed $value проверяемое значение
     * @return bool
     */
    public function validate($value)
    {
        $this->value = $value;
        $errors = [];

        if (!preg_match('~^[\w!@#$%^&`\~]+$~u', $value)) {
            $errors[] = App::t('Недопустимые символы');
        }

        if (mb_strlen($value) < $this->options['min_length']) {
            $errors[] = App::t('Пароль слишком короткий');
        }

        $minCombination = $this->options['min_combination'];
        if ($minCombination) {
            $combinationsCount = 0;
            // Убираем левое
            $temp1 = preg_replace('~[^\w!@#$%^&`\~]+~u', '', $value);

            // Убраем спец.символы
            $temp2 = preg_replace('~[!@#$%^&`\~_-]+~', '', $temp1);

            // Строка изменилась? Значит набор был.
            if ($temp2 != $temp1) {
                $combinationsCount++;
            }

            // Из оставшейся(!) строки убраем цифры.
            $temp1 = preg_replace('~\d+~', '', $temp2);

            // Опять изменилась? Значит цифровой набор был.
            if ($temp2 != $temp1) {
                $combinationsCount++;
            }

            // Теперь в строке только буквы. Проверяем camelCase.
            if ($temp1 != mb_strtoupper($temp1) && $temp1 != mb_strtolower($temp1)) {
                $combinationsCount++;
            }

            // Факт того, что к этому моменту строка не опустела
            if ($temp1) {
                $combinationsCount++;
            }

            if ($combinationsCount < $minCombination) {
                $errors[] = App::t('Пароль слишком простой') . ", {$combinationsCount}/{$minCombination}";
            }
        }

        if ($errors) {
            $this->options['message'] = implode($this->options['glue'], $errors);
            return false;
        }

        return true;
    }
}
