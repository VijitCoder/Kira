<?php
/**
 * Почтовый класс
 */

namespace app\helpers;

class MailHelper
{
    /**
     * Отправка html-версии письма с соответствующими заголовками
     * @param string $to      кому
     * @param string $subject тема
     * @param string $html    текст письма
     * @return bool
     */
    public static function sendHtml($to, $subject, $html)
    {
        $from = \engine\App::conf('admin_mail');
        $rn = "\r\n";
        // Не указываем "To:" и "Subject:", это допишет функция mail()
        $headers = 'From: ' . $from . $rn;
        $headers .= 'Date: ' . date('r') . $rn;
        $headers .= 'X-Mailer: script on PHP '.phpversion() . $rn;
        $headers .= 'MIME-Version: 1.0' . $rn;
        $headers .= 'Content-Type: text/html; charset=utf-8' . $rn;
        return mail($to, $subject, $html, $headers);
    }
}
