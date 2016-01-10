<?php
/**
 * Среда окружения сайта.
 */

namespace app;

use Exception,
    engine\App;

class Env extends \engine\Env
{
    /**
     * Определение среды окружения (local, dev, stage, production, mobile).
     *
     * Перекрытие родительского метода. Определяем среду через явное указание в конфиге приложения. Мобильное окружение
     * определяем по имени домена, начинается с 'm.'.
     *
     * @return int
     * @throws Exception
     */
    public static function detectEnvironment()
    {
        if (isset($_SERVER['HOST']) && substr($_SERVER['HOST'], 0, 2) === 'm.') {
            return self::D_MOBILE;
        }
        switch (App::conf('environment')) {
            case 'local':      return self::D_LOCAL;
            case 'dev':        return self::D_DEV;
            case 'stage':      return self::D_STAGE;
            case 'production': return self::D_PROD;
            default:;
        }

        throw new Exception ('Не могу определить среду окружения (сервер).');
    }
}
