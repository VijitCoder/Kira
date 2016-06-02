<?php
namespace install\app;

/**
 * Модель формы "Создание нового приложения"
 */
class MasterForm extends \engine\web\Form
{
    /**
     * Имя таблицы логера по умолчанию. Не относится конкретно к модели формы, но требуется где-то хранить это значение.
     */
    const LOG_TABLE = 'kira_log';

    /**
     * Подключение к базе данных. NULL - не быпо попытки подключения, FALSE - подключение неудалось.
     * @var null|false|resource
     */
    private $dbh = null;

    /**
     * @var array контракт на поля формы
     */
    protected $contract = [
        'path' => [
            'app' => [
                'validators' => [
                    'required' => ['message' => 'Не указан каталог приложения'],

                    'external' => [
                        'function' => ['\install\app\MasterForm', 'normalizePath'],
                        'message'  => 'Каталог приложения должен быть в пределах сайта',
                    ],

                    'length' => [
                        'max'     => 1000,
                        'message' => 'Каталог приложения: очень длинный путь. Максимум 1000 символов',
                    ],
                ],
            ],

            'view' => [
                'validators' => [
                    'required' => ['message' => 'Не указан каталог шаблонов'],

                    'external' => [
                        'function' => ['\install\app\MasterForm', 'normalizePath'],
                        'message'  => 'Каталог шаблонов должен быть в пределах сайта',
                    ],

                    'length' => [
                        'max'     => 1000,
                        'message' => 'Каталог шаблонов: очень длинный путь. Максимум 1000 символов',
                    ],
                ],
            ],

            'temp' => [
                'validators' => [
                    'required' => ['message' => 'Не указан temp-каталог приложения'],

                    'external' => [
                        'function' => ['\install\app\MasterForm', 'normalizePath'],
                        'message'  => 'Temp-каталог приложения должен быть в пределах сайта',
                    ],

                    'length' => [
                        'max'     => 1000,
                        'message' => 'Temp-каталог приложения: очень длинный путь. Максимум 1000 символов',
                    ],
                ],
            ],
        ],

        'main_conf' => [
            'validators' => [
                'required' => ['message' => 'Не указан путь к конфигурации'],

                'external' => [
                    'function' => ['\install\app\MasterForm', 'normalizePath'],
                    'options'  => ['isFile' => true],
                    'message'  => 'Конфигурация приложения должна быть в пределах сайта',
                ],

                'length' => [
                    'max'     => 1000,
                    'message' => 'Конфигурация приложения: очень длинный путь. Максимум 1000 символов',
                ],
            ],
        ],

        'ns_prefix' => [
            'validators' => [
                'required' => ['message' => 'Необходимо указать префикс пространства имен'],

                'filter_var' => [
                    'filter'  => FILTER_VALIDATE_REGEXP,
                    'options' => ['regexp' => '~^[0-9a-z\_-]+$~i'],
                    'message' => 'Недопустимые символы в префиксе пространства имен. Ожидается [a-z\_-]',
                ],

                'length' => [
                    'max'     => 100,
                    'message' => 'Очень длинный префикс пространства имен. Максимум 100 символов',
                ],
            ],
        ],

        'email' => [
            'validators' => [
                'required' => ['message' => 'Нужен адрес админа'],

                'external' => [
                    'function' => ['\engine\utils\Validators', 'mail'],
                ],

                'length' => [
                    'max'     => 50,
                    'message' => 'Очень длинный email. Максимум 50 символов',
                ],
            ],
        ],

        'db' => [
            'switch' => null,

            'server' => [
                'validators' => [
                    'filter_var' => [
                        'filter'  => FILTER_CALLBACK,
                        'options' => ['\engine\utils\Validators', 'normalizeString'],
                    ],

                    'length' => [
                        'max'     => 100,
                        'message' => 'Сервер[порт]. Максимум 100 символов',
                    ],
                ],
            ],

            'base' => [
                'validators' => [
                    'filter_var' => [
                        'filter'  => FILTER_CALLBACK,
                        'options' => ['\engine\utils\Validators', 'normalizeString'],
                    ],

                    'length' => [
                        'max'     => 50,
                        'message' => 'Имя базы. Максимум 50 символов',
                    ],
                ],
            ],

            'charset' => [
                'validators' => [
                    'filter_var' => [
                        'filter'  => FILTER_VALIDATE_REGEXP,
                        'options' => ['regexp' => '~^utf8|cp1251~'],
                        'message' => 'Неправильное значение кодировки',
                    ],
                ],
            ],

            'user' => [
                'validators' => [
                    'filter_var' => [
                        'filter'  => FILTER_CALLBACK,
                        'options' => ['\engine\utils\Validators', 'normalizeString'],
                    ],

                    'length' => [
                        'max'     => 30,
                        'message' => 'Имя пользователя. Максимум 30 символов',
                    ],
                ],
            ],

            'password' => [
                'validators' => [
                    'filter_var' => [
                        'filter'  => FILTER_CALLBACK,
                        'options' => ['\engine\utils\Validators', 'normalizeString'],
                    ],

                    'length' => [
                        'max'     => 30,
                        'message' => 'Пароль. Максимум 30 символов',
                    ],
                ],
            ],
        ],

        'log' => [
            'switch' => null,

            'store' => [
                'validators' => [
                    'filter_var' => [
                        'filter'  => FILTER_VALIDATE_REGEXP,
                        'options' => ['regexp' => '~^db|files$~'],
                        'message' => 'Неправильное значение в указании, куда писать логи.',
                    ],
                ],
            ],

            'table' => [
                'validators' => [
                    'filter_var' => [
                        'filter'  => FILTER_CALLBACK,
                        'options' => ['\engine\utils\Validators', 'normalizeString'],
                    ],

                    'length' => [
                        'min'     => 1,  //проверка нужна. Предыдущий валидатор может укоротить строку
                        'max'     => 50,
                        'message' => 'Имя таблицы должно быть в пределах [1, 50] символов',
                    ],
                ],
            ],

            'path' => [
                'validators' => [
                    'external' => [
                        'function' => ['\install\app\MasterForm', 'normalizePath'],
                        'message'  => 'Каталог должен быть в пределах сайта',
                    ],

                    'length' => [
                        'max'     => 1000,
                        'message' => 'Очень длинный путь каталога. Максимум 1000 символов',
                    ],
                ],
            ],

            'timezone' => [
                'validators' => [
                    'filter_var' => [
                        'filter'  => FILTER_VALIDATE_REGEXP,
                        'options' => ['regexp' => '~^[0-9a-z_+-/]*$~i'],
                        'message' => 'Недопустимые символы в часовом поясе. Ожидается [0-9a-z_+-/]',
                    ],

                    'length' => [
                        'max'     => 50,
                        'message' => 'Часовой пояс максимум 50 символов',
                    ],
                ],
            ],
        ],

        'lang' => [
            'switch' => null,

            'other' => [
                'validators' => [
                    'filter_var' => [
                        'filter'  => FILTER_CALLBACK,
                        'options' => ['\install\app\MasterForm', 'langCodesValidator'],
                        'message' => 'Недопустимые символы в кодах языков. Ожидается [a-z\s,], каждый код 2-3 символа.',
                    ],

                    'length' => ['max' => 100, 'message' => 'Слишком длинная строка кодов. Максимум 100 символов'],
                ],
            ],

            'js_path' => [
                'validators' => [
                    'external' => [
                        'function' => ['\install\app\MasterForm', 'normalizePath'],
                        'message'  => 'Каталог должен быть в пределах сайта',
                    ],

                    'length' => [
                        'max'     => 1000,
                        'message' => 'Очень длинный путь каталога. Максимум 1000 символов',
                    ],
                ],
            ],
        ],
    ];

    /**
     * Тюнинг: нужно убрать из внимания валидаторов отключенные блоки. Иначе валидация не проходит в случае ошибок
     * в них, а по логике их вообще проверять не надо. При этом значения в блоках должны сохраняться, пусть даже
     * неправильные.
     * @return bool
     */
    public function validate()
    {
        foreach (['db', 'log', 'lang'] as $key) {
            if (!isset($this->rawData[$key]['switch'])) {
                unset($this->contract[$key]);
            }
        }

        return parent::validate();
    }

    /**
     * Дезинфекция каталога. Валидатор для типа "external".
     *
     * Заменяем слеши на прямые, в конце ставим слеш. Проверяем каталог на попытки переходов типа "../path/"
     * или "some/../../path/". Это недопустимо.
     *
     * Один параметр:
     * $options = ['isFile' => bool] // не дописывать слеш в конце, проверяемое значение - путь к файлу.
     *
     * @param $path
     * @return string|false
     */
    public static function normalizePath($path, $options)
    {
        if ($path) {
            $path = trim(str_replace('\\', '/', $path), '/');
            if (!isset($options['isFile'])) {
                $path .= '/';
            }

            if (preg_match('~^[.]{2}/|/[.]{2}/~', $path)) {
                return ['error' => 'Каталог должен быть в пределах сайта'];
            }
        }

        return ['value' => $path];
    }

    /**
     * Коды языков, валидатор для типа "filter_var".
     * Каждое имя в списке проверить регуляркой. Ожидаем [a-z\s,], каждый код 2-3 символа.
     * @param $value
     * @return string|false
     */
    public static function langCodesValidator($value)
    {
        if (!$value) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        $value = str_replace(' ', '', mb_strtolower($value));

        if (!preg_match('~^[a-z,]+$~', $value)) {
            return false;
        }

        $others = explode(',', $value);
        foreach ($others as $v) {
            if (!preg_match('~^[a-z]{2,3}$~', $v)) {
                return false;
            }
        }

        return $value;
    }

    /**
     * Доп.проверки, не покрытые валидацией:
     * <ul>
     * <li>проверить префикс приложения на совпадение с 'engine'.</li>
     * <li>проверить, что нет каталога будущего приложения.</li>
     * <li>проверить доступ на запись для каталога логов, если он задан.</li>
     * <li>проверить, что нет каталога статики js для словарей, если он нужен.</li>
     * <li>проверить подключение к БД, см. соответствующий метод</li>
     * <li>если включено логирование, проверить конфигурацию. См. соответствующий метод</li>
     * <li>языки. Если флаг есть, а кодов нет - ошибка.</li>
     * </ul>
     *
     * @return bool
     */
    public function logicChecks()
    {
        $v = &$this->values;

        if ($v['ns_prefix'] === 'engine') {
            $this->errors['ns_prefix'][] =
                'Недопустимый префикс приложения. "<i>engine</i>" - это префикс движка, вообще-то.';
        }

        if ($v['lang']['switch'] && !$v['lang']['other']) {
            $this->errors['lang'][] = 'Блок включен, но не задан ни один язык словарей.';
        }

        $this->checkPathAccess();
        $this->checkDbConfiguration();
        $this->checkLoggerConfiguration();

        return !$this->hasErrors();
    }

    /**
     * Проверка каталогов.
     *
     * Проверить существование каталога приложения. Его не должно быть, иначе - ошибка. Суть в том, что при
     * существовании каталога нельзя гарантировать, что копируемое в него приложение не перепишет там что-нибудь.
     *
     * Если включены блоки lang или log, проверить соответственно:
     * - каталог log.path. Если каталог есть, должен быть доступ на запись в него.
     * - каталог lang.js_path + 'i18n/'. Его не должно быть. Так же, если lang.js_path существует, должны быть права
     * записи в него, чтоб создать 'i18n/'.
     *
     * @return void
     */
    private function checkPathAccess()
    {
        $v = &$this->values;
        $e = &$this->errors;

        if (file_exists(ROOT_PATH . $v['path']['app'])) {
            $e['path']['app'][] = 'Каталог приложения уже существует. '
                . 'Нельзя создать в нем новое приложение без риска переписать что-нибудь.';
        }

        $path = ROOT_PATH . $v['log']['path'];
        if ($v['log']['switch'] && file_exists($path) && !is_writable($path)) {
            $e['log'][] = 'Каталог для логов не доступен для записи';
        }

        if ($v['lang']['switch']) {
            $path = ROOT_PATH . $v['lang']['js_path'];
            if (file_exists($path . 'i18n/')) {
                $e['lang'][] = 'Каталог для файлов словарей уже существует. '
                    . 'Нельзя в нем создать словари без риска переписать что-нибудь.';
            } elseif (file_exists($path) && !is_writable($path)) {
                $e['lang'][] = 'Указанный каталог недоступен для записи. Не смогу создать в нем подкаталог словарей.';
            }
        }
    }

    /**
     * Проверка конфигурации БД.
     *
     * Тут много тонкостей, но исходим из того, что если юзер описал конфиг, значит СУБД - MySQL и подключение должно
     * сработать. Кроме необходимого минимума (сервер, имя базы) проверяем подключение. В случае успеха сохраняем его
     * для последующей проверки логера.
     *
     * Прим: мы не можем использовать классы движка из engine\db\, потому что там параметры подключения читаются из
     * конфига приложения, а у нас его еще нет.
     *
     * TODO не удалось проверить подключение на порт. Любое значение порта подходило. Почему так?
     *
     * @return void
     */
    private function checkDbConfiguration()
    {
        $conf = $this->values['db'];

        if (!$conf['switch']) {
            return;
        }

        if (!($conf['server'] && $conf['base'])) {
            $this->errors['db'][] = 'Не указано имя сервера и базы данных';
        }

        $conf['server'] = str_replace(':', '; port=', $conf['server']);

        $conf['dsn'] = 'mysql:host=' . $conf['server'] . '; dbname=' . $conf['base']
            . ($conf['charset'] ? '; charset=' . $conf['charset'] : '');

        try {
            $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,];
            $this->dbh = new \PDO($conf['dsn'], $conf['user'], $conf['password'], $options);
        } catch (\PDOException $e) {
            $this->errors['db'][] = 'Не удалось подключиться в базе: ' . $e->getMessage();
            $this->dbh = false;
        }

        return true;
    }

    /**
     * Проверка конфигурации логера.
     *
     * Допускается работа логера без конфигурации. Поэтому ее проверка немного усложнена.
     *
     * Если включено логирование и выбран лог в базу, проверить отсутствие таблицы при логировании в базу. Следует
     * учесть, что перед этим была проверка подключения к БД в другом методе, и если она не удалась, тогда проверка
     * таблицы не может быть выполнена.
     *
     * Проверяем часовой пояс, если он задан. Путь к логам, если он задан, проверяется в другом методе.
     *
     * @return void
     */
    private function checkLoggerConfiguration()
    {
        $conf = $this->values['log'];

        if (!$conf['switch']) {
            return;
        }

        if ($conf['timezone']) {
            try {
                new \DateTime($conf['timezone']);
            } catch (\Exception $e) {
                $this->errors['log'][] = 'Неверный часовой пояс: ' . $e->getMessage();
            }
        }

        if (isset($conf['store']) && $conf['store'] == 'db') {
            if (!$conf['table']) {
                $conf['table'] = self::LOG_TABLE;
            }

            $errMsg = '';

            if (is_null($this->dbh)) {
                $errMsg = 'Нет конфига БД. Невозможно вести лог в базу.';
            } elseif ($this->dbh === false) {
                $errMsg = 'Подключение к базе не удалось. Не могу проверить отсутствие таблицы лога, а это обязательно';
            } else {
                try {
                    $sth = $this->dbh->prepare('SHOW TABLES LIKE ?');
                    $sth->execute([$conf['table']]);
                    if ($sth->fetch(\PDO::FETCH_NUM)) {
                        $errMsg = 'Таблица уже существует. Нельзя использовать ее для записи логов.';
                    }
                } catch (\PDOException $e) {
                    $errMsg = 'Проверка таблицы лога не удалась: ' . $e->getMessage();
                }
            }

            if ($errMsg) {
                $this->errors['log'][] = $errMsg;
            }
        }
    }
}
