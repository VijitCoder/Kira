<?php
use PHPUnit\Framework\TestCase;
use kira\web\Form;
use kira\utils\Validators;
use kira\utils\FS;
use kira\utils\Arrays;

/**
 * Тестируем валидатор форм. Это скорее интеграционный тест, нежели unit.
 *
 * Прогоняем все этапы работы с моделью формы, от загрузки данных, до получения значений. Поэтому важен порядок
 * выполнения тестов.
 *
 * Прим: используемый контракт - это частичный копипаст из мастера приложения. Его модель формы, его кастомный валидатор.
 */
class FromValidationTest extends TestCase
{
    /**
     * Объект формы
     * @var Form
     */
    private static $form;

    // private static $contract Контракт описан внизу класса. Он большой.

    /**
     * Создание модели формы. Явно задаем контракт валидации, т.к. у данной модели нет своего контракта.
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::$form = new Form(self::$contract);
    }

    /**
     * Загрузка данных в модель
     */
    public function test_load()
    {
        $data = [
            'app_path'      => '\valid\path', // прим.: максимум 12 символов, задано в контракте
            'app_namespace' => 'some_ns',
            'email'         => 'admin@site.com',

            'db' => [
                'switch' => 'on',
                'server' => '127.0.0.1',
                'base'   => ' my_base ',
            ],

            'modules' => [' user ', " admin \x00"],
        ];

        self::$form->load($data);

        $this->assertEquals($data, self::$form->getRawData(), 'Сырые данные загружены в модель формы');
    }

    /**
     * Валидация
     *
     * Результат валидации можно проверить, как минимум тремя способами. Разумеется, вызовы методов необязательно делать
     * сразу после валидации.
     */
    public function test_validate()
    {
        $result = self::$form->validate();
        $this->assertTrue($result, 'Успешная валидация');
        $this->assertTrue(self::$form->isValid(), 'Результат проведенной валидации - данные валидные');
        $this->assertFalse(self::$form->hasErrors(), 'Ошибок нет');
    }

    /**
     * Получаем валидированные данные
     */
    public function test_getValues()
    {
        $expect = [
            'app_path'      => 'valid/path/', // отличается от исходного. Нормализованный путь
            'app_namespace' => 'some_ns',     // отличается от исходного. Нормализованная строка
            'email'         => 'admin@site.com',

            'db' => [
                'switch' => 'on',
                'server' => '127.0.0.1',
                'base'   => 'my_base',
            ],

            'modules' => ['user', 'admin'], // отличается от исходного. Нормализованная строка
        ];

        $this->assertEquals($expect, self::$form->getValues(), 'Все валидированные данные');
    }

    /**
     * Добавим кастомные ошибки к глубоко вложенному полю. Получим эти ошибки разными способами. Такие ошибки не влияют
     * на результат проведенной валидации.
     */
    public function test_addCustomError()
    {
        $form = self::$form;

        $this->assertFalse($form->hasErrors(), 'Пока ошибок нет');
        $this->assertTrue(self::$form->isValid(), 'Результат проведенной ранее валидации - данные валидные');

        $errServerMessage = 'Этот сервер отключен';
        $form->addError(['db' => ['server' => $errServerMessage]]);

        $this->assertTrue(self::$form->isValid(),
            'Добавление кастомной ошибки не влияет на результат проведенной валидации');
        $this->assertTrue($form->hasErrors(), 'Теперь есть ошибка. Добавлена вручную');
        $this->assertEquals([$errServerMessage], $form->getErrors(['db' => 'server']),
            'Кастомная ошибка добавлена к нужному полю');

        $errBaseMessage = 'Неизвестная БД';
        $form->addError(['db' => ['base' => $errBaseMessage]]);
        $allDbErrors = Arrays::array_filter_recursive($form->getErrorsAsString('db'));
        $expect = [
            'server' => $errServerMessage,
            'base' => $errBaseMessage,
        ];
        $this->assertEquals($expect, $allDbErrors, 'Кастомные ошибки, собранные по каждому полю.');
    }

    /**
     * Пробуем пробить валидаторы
     *
     * Результаты можно получить несколькими способами, аналогично с self::test_validate()
     *
     * Прим.: пересоздаем модель формы, т.к. ее повторное использование не предусмотрено.
     */
    public function test_invalid_data()
    {
        self::setUpBeforeClass();

        $form = self::$form;
        $data = [
            'app_path'      => '\valid\path\tooLong', // слишком длинная строка
            'app_namespace' => null,                  // необходимо значение
            'email'         => 'admin@server.com',    // сервер в черном списке

            'db' => [
                'switch' => 'on',
                //'server' => '127.0.0.1',
                'base'   => [' my_base ', 'base2'],   // массив значений там, где ждем строку
            ],

            'modules' => ' user ',                    // строка там, где ждем массив
        ];

        $result = $form->load($data)->validate();

        $this->assertFalse($result, 'Валидация не прошла');
        $this->assertFalse($form->isValid(), 'Результат проведенной валидации - неверные данные');
        $this->assertTrue($form->hasErrors(), 'Есть ошибки валидации');

        $errorsByField = Arrays::array_filter_recursive($form->getErrorsAsString());
        $this->assertEquals(5, count($errorsByField), 'Есть ошибки по 5 полям');
    }

    /**
     * Пример кастомного валидатора
     *
     * Дезинфекция каталога. Валидатор для типа "filter_var".
     *
     * Заменяем слеши на прямые, в конце ставим слеш. Проверяем каталог на попытки переходов типа "../path/"
     * или "some/../../path/". Это недопустимо.
     *
     * @param string $value проверяемое значение
     * @return false|string
     */
    public static function normalizePath($value)
    {
        if ($value) {
            $value = trim(str_replace('\\', '/', $value), '/') . '/';

            if (FS::hasDots($value)) {
                return false;
            }
        }

        return $value;
    }

    /**
     * Контракт на поля формы. Описание валидаторов.
     * @var array
     */
    private static $contract = [
        'app_path' => [
            'some_thing' => 'Что-то левое в контракте. Должно быть проигнорировано',

            'validators' => [
                'required' => ['message' => 'Не указан каталог приложения'],

                'filter_var' => [
                    'filter'  => FILTER_CALLBACK,
                    'options' => [FromValidationTest::class, 'normalizePath'],
                    'message' => 'Каталог должен быть в пределах сайта',
                ],

                'length' => [
                    'max'     => 12, // утрировано, для удобства тестирования
                    'message' => 'Каталог приложения: очень длинный путь. Максимум 12 символов',
                ],
            ],
        ],

        'app_namespace' => [
            'validators' => [
                'required' => ['message' => 'Необходимо указать корень пространства имен приложения'],

                'filter_var' => [
                    'filter'  => FILTER_VALIDATE_REGEXP,
                    'options' => ['regexp' => '~^[0-9a-z\_-]+$~i'],
                    'message' => 'Недопустимые символы в корне пространства имен. Ожидается [a-z\_-]',
                ],

                'length' => [
                    'max'     => 100,
                    'message' => 'Очень длинный корень пространства имен. Максимум 100 символов',
                ],
            ],
        ],

        'email' => [
            'validators' => [
                'required' => ['message' => 'Нужен адрес админа'],

                'external' => [
                    'function' => [Validators::class, 'mail'],
                    'options'  => [
                        'black_servers' => ['server.com'],
                    ],
                ],

                'length' => [
                    'max'     => 50,
                    'message' => 'Очень длинный email. Максимум 50 символов',
                ],
            ],
        ],

        // Вложенные именования полей формы. Запрос типа db[server]=xxx&db[base]=yyy
        'db' => [
            'switch' => null, // поле принять без проверок

            'server' => [
                'validators' => [
                    'filter_var' => [
                        'filter'  => FILTER_CALLBACK,
                        'options' => [Validators::class, 'normalizeString'],
                    ],

                    'length' => [
                        'max'     => 100,
                        'message' => 'Сервер[порт]. Максимум 100 символов',
                    ],
                ],
            ],

            'base' => [
                'validators' => [
                    'expect_array' => false, // умышленно добавил, чтобы проверить, что функционал не отвалится
                    'filter_var' => [
                        'filter'  => FILTER_CALLBACK,
                        'options' => [Validators::class, 'normalizeString'],
                    ],

                    'length' => [
                        'max'     => 50,
                        'message' => 'Имя базы. Максимум 50 символов',
                    ],
                ],
            ],
        ],

        // Пример валидации массива однотипных значений. Запрос типа modules[]=aaa&modules[]=bbb
        'modules' => [
            'validators'  => [
                'expect_array' => true,
                'filter_var' => [
                    'filter'  => FILTER_CALLBACK,
                    'options' => [Validators::class, 'normalizeString'],
                ],
            ],
        ],
    ];
}
