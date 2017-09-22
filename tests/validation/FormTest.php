<?php
use PHPUnit\Framework\TestCase;
use kira\validation\Form;
use kira\utils\FS;
use kira\utils\Arrays;

/**
 * Тестируем валидатор форм. Это скорее интеграционный тест, нежели unit, конечные валидаторы не подменяются. Но в этом
 * и смысл теста - убедиться, что вся фича работает в целом.
 */
class FromTest extends TestCase
{
    /**
     * Объект формы
     * @var Form
     */
    private $form;

    // private $data Данные описаны внизу класса
    // private $contract Контракт описан внизу класса. Он большой.

    /**
     * Создание модели формы. Явно задаем контракт валидации, т.к. у данной модели нет своего контракта.
     */
    public function setUp()
    {
        $this->form = new Form($this->contract);
    }

    /**
     * Загрузка данных в модель
     */
    public function test_load()
    {
        $this->form->load($this->data);
        $this->assertEquals($this->data, $this->form->getRawData(), 'Сырые данные неверно загружены в модель формы');
    }

    /**
     * Валидация
     *
     * Результат валидации можно проверить, как минимум тремя способами. Разумеется, вызовы методов необязательно делать
     * сразу после валидации.
     */
    public function test_validate()
    {
        $result = $this
            ->form
            ->load($this->data)
            ->validate();
        $this->assertTrue($result, 'Валидация правильного набора данных провалена');
        $this->assertTrue($this->form->isValid());
        $this->assertFalse($this->form->hasErrors());
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
            'IP'      => '127.0.0.1',            // IP был не той версии, подставилось дефолтное значение
            'id'      => 345,
            'page'    => 5,
        ];

        $this->form
            ->load($this->data)
            ->validate();

        $validatedValues = $this->form->getValues();

        $this->assertEquals($expect, $validatedValues, 'Валидированные данные не совпадают с ожиданием');
    }

    /**
     * Добавим кастомные ошибки к глубоко вложенному полю. Получим эти ошибки разными способами. Такие ошибки не влияют
     * на результат проведенной валидации.
     */
    public function test_addCustomError()
    {
        $form = $this->form;
        $form->load($this->data)->validate();

        $this->assertFalse($form->hasErrors(), 'Найдены ошибки, хотя данные должны быть абсолютно валидные');
        $this->assertTrue($this->form->isValid());

        $errServerMessage = 'Этот сервер отключен';
        $form->addError(['db' => ['server' => $errServerMessage]]);

        $this->assertTrue($this->form->isValid(),
            'Добавление кастомной ошибки повлияло на результат проведенной валидации');
        $this->assertTrue($form->hasErrors(), 'Все еще нет ошибок, хотя одна была добавлена вручную');
        $this->assertEquals([$errServerMessage], $form->getErrors(['db' => 'server']),
            'Кастомная ошибка не добавлена к нужному полю');

        $errBaseMessage = 'Неизвестная БД';
        $form->addError(['db' => ['base' => $errBaseMessage]]);
        $allDbErrors = Arrays::array_filter_recursive($form->getErrorsAsStringPerField('db'));
        $expect = [
            'server' => $errServerMessage,
            'base'   => $errBaseMessage,
        ];
        $this->assertEquals(
            $expect,
            $allDbErrors,
            'Кастомные ошибки, собранные по каждому полю, не соответствуют ожиданиям'
        );
    }

    /**
     * Пример валидатора с вызовом через filter_var(FILTER_CALLBACK)
     *
     * Дезинфекция каталога. Заменяем слеши на прямые, в конце ставим слеш. Проверяем каталог на попытки переходов типа
     * "../path/" или "some/../../path/". Это недопустимо.
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
     * Некоторые данные, подлежащие валидации
     * @var array
     */
    private $data = [
        'app_path'      => '\valid\path', // прим.: максимум 12 символов, задано в контракте
        'app_namespace' => 'some_ns',
        'email'         => 'admin@site.com',

        'db' => [
            'switch' => 'on',
            'server' => '127.0.0.1',
            'base'   => ' my_base ',
        ],

        'modules' => [' user ', " admin \x00"],
        'IP'      => '2001:0db8:11a3:09d7:1f34:8a2e:07a0:765d',
        'id'      => '345',
        'page'    => '5',
    ];

    /**
     * Контракт на поля формы. Описание валидаторов.
     * @var array
     */
    private $contract = [
        'app_path' => [
            'some_thing' => 'Что-то левое в контракте. Должно быть проигнорировано',

            'validators' => [
                'required' => ['message' => 'Не указан каталог приложения'],

                'filter_var' => [
                    'filter'  => FILTER_CALLBACK,
                    'options' => [self::class, 'normalizePath'],
                    'message' => 'Каталог должен быть в пределах сайта',
                ],

                'limits' => [
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

                'limits' => [
                    'max'     => 100,
                    'message' => 'Очень длинный корень пространства имен. Максимум 100 символов',
                ],
            ],
        ],

        'email'   => [
            'validators' => [
                'required' => ['message' => 'Нужен адрес админа'],

                'email' => ['black_servers' => ['server.com'],],

                'limits' => [
                    'max'     => 50,
                    'message' => 'Очень длинный email. Максимум 50 символов',
                ],
            ],
        ],

        // Вложенные именования полей формы. Запрос типа db[server]=xxx&db[base]=yyy
        'db'      => [
            'switch' => null, // поле принять без проверок

            'server' => [
                'validators' => [
                    'normalize_string' => true,
                    'limits'          => [
                        'max'     => 100,
                        'message' => 'Сервер[порт]. Максимум 100 символов',
                    ],
                ],
            ],

            'base' => [
                'validators' => [
                    'expect_array'    => false, // умышленно добавил, чтобы проверить, что функционал не отвалится
                    'normalize_string' => true,
                    'limits'          => [
                        'max'     => 50,
                        'message' => 'Имя базы. Максимум 50 символов',
                    ],
                ],
            ],
        ],

        // Пример валидации массива однотипных значений. Запрос типа modules[]=aaa&modules[]=bbb
        'modules' => [
            'validators' => [
                'expect_array'    => true,
                'normalize_string' => true,
            ],
        ],

        'IP' => [
            'validators' => [
                'filter_var' => [
                    'filter'  => FILTER_VALIDATE_IP,
                    'options' => ['default' => '127.0.0.1'],
                    'flags'   => FILTER_FLAG_IPV4,
                    'message' => 'Ожидается IPv4',
                ],
            ],
        ],

        'id' => [
            'validators' => ['expect_id' => true,],
        ],

        'page' => [
            'validators' => ['expect_id' => ['message' => 'Неверный номер страницы'],],
        ],
    ];
}
