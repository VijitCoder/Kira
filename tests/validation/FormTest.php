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
     *
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
     * Результат валидации можно проверить, как минимум тремя способами, тестируем их все.
     */
    public function test_validate()
    {
        $isValid = $this
            ->form
            ->load($this->data)
            ->validate();
        $this->assertTrue($isValid, 'Валидация правильного набора данных провалена');
        $this->assertTrue($this->form->isValid());
        $this->assertFalse($this->form->hasErrors());
    }

    /**
     * Получаем валидированные данные
     */
    public function test_getValues()
    {
        $expect = [
            'path'            => 'valid/path/', // отличается от исходного. Нормализованный путь
            'namespace'       => 'some_ns',     // отличается от исходного. Нормализованная строка
            'do_not_validate' => 'что-нибудь без валидации',
            'email'           => 'admin@site.com',

            'db' => [
                'switch' => 'on',
                'server' => '127.0.0.1',
                'base'   => 'my_base',
            ],

            'modules' => ['user', 'admin'], // отличается от исходного. Нормализованная строка
            'IP'      => '127.0.0.1',       // IP был не той версии, подставилось дефолтное значение
            'page'    => 5,

            'required_array' => ['any value'],
        ];

        $this->form
            ->load($this->data)
            ->validate();

        $validatedValues = $this->form->getValues();

        $this->assertEquals($expect, $validatedValues, 'Валидированные данные не совпадают с ожиданием');
    }

    /**
     * Тест успешной валидации неполного набора данных, тут же проверяем подстановку данных по умолчанию.
     */
    public function test_validate_with_default()
    {
        $d = $this->data;
        unset($d['db']['switch'], $d['db']['server'], $d['db']['base'], $d['modules']);

        $form = $this->form;
        $form->load($d)->validate();

        $this->assertTrue($form->isValid(), 'Валидация правильного набора данных провалена');

        $this->assertEquals(
            [
                'switch' => 3,
                'server' => 'what ever',
                'base'   => null,
            ],
            $form->getValues('db'),
            'Неверно заданы значения по умолчанию'
        );
        $this->assertNull($form->getValues('modules'), 'Неверное пустое значение для ожидаемого массива данных');
    }

    /**
     * Проверка на ошибку валидации обязательных, но не переданных значений. Особый интерес к обязательному массиву
     * данных.
     */
    public function test_required_invalid()
    {
        $form = $this->form;
        $isValid = $form->load([])->validate();
        $errors = Arrays::array_filter_recursive($form->getErrors());
        $this->assertFalse($isValid, 'Заведомо неправильный набор данных неожиданно прошел валидацию');
        $this->assertEquals(2, count($errors), 'Количество полей с ошибками не равно ожидаемому');
        $this->assertArrayHasKey('path', $errors, 'Нет ошибки в ожидаемом поле');
        $this->assertArrayHasKey('required_array', $errors, 'Нет ошибки в ожидаемом поле');
    }

    /**
     * Получаем ошибки по сложному контракту, сообщения склеиваются в строку.
     *
     * Прим.: запись кастомных ошибок в модель формы - это необычный вариант использования. И он не преусматривает
     * массовую загрузку ошибок.
     */
    public function test_getErrorsAsStringPerField()
    {
        $form = $this->form;
        $form->load($this->data);
        $form->addError(['email' => 'Недопустимые символы.']);
        $form->addError(['db' => ['server' => 'Сервер не отвечает.']]);
        $form->addError(['db' => ['server' => 'Еще ошибка.']]);
        $form->addError(['db' => ['base' => 'Неизвестная БД.']]);

        $allUsefulErrors = array_filter($form->getErrorsAsStringPerField());
        $expect = [
            'email' => 'Недопустимые символы.',
            'db'    => 'Сервер не отвечает. Еще ошибка. Неизвестная БД.',
        ];
        $this->assertEquals($expect, $allUsefulErrors);

        $this->assertEquals('Недопустимые символы.', $form->getErrorsAsStringPerField('email'));
        $this->assertEquals('Сервер не отвечает. Еще ошибка. Неизвестная БД.', $form->getErrorsAsStringPerField('db'));
        $this->assertEquals('Сервер не отвечает. Еще ошибка.', $form->getErrorsAsStringPerField(['db' => 'server']));
        $this->assertEquals('Неизвестная БД.', $form->getErrorsAsStringPerField(['db' => 'base']));
    }

    /**
     * Добавим кастомную ошибку к глубоко вложенному полю. Проверяем, что такие ошибки не влияют на результат
     * проведенной валидации.
     */
    public function test_addCustomError()
    {
        $form = $this->form;
        $form->load($this->data)->validate();

        $this->assertFalse($form->hasErrors(), 'Найдены ошибки, хотя данные должны быть абсолютно валидные');
        $this->assertTrue($this->form->isValid());

        $form->addError(['db' => ['server' => 'Этот сервер отключен.']]);

        $this->assertTrue(
            $this->form->isValid(),
            'Добавление кастомной ошибки повлияло на результат проведенной валидации'
        );
        $this->assertTrue($form->hasErrors(), 'Все еще нет ошибок, хотя одна была добавлена вручную');
        $this->assertEquals(
            ['Этот сервер отключен.'],
            $form->getErrors(['db' => 'server']),
            'Кастомная ошибка не добавлена к нужному полю'
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
     *
     * @var array
     */
    private $data = [
        'path'            => '\valid\path',
        'namespace'       => 'some_ns',
        'do_not_validate' => 'что-нибудь без валидации',
        'email'           => 'admin@site.com',

        'db' => [
            'switch' => 'on',
            'server' => '127.0.0.1',
            'base'   => ' my_base ',
        ],

        'modules' => [' user ', " admin \x00"],
        'IP'      => '2001:0db8:11a3:09d7:1f34:8a2e:07a0:765d',
        'page'    => '5',

        'required_array' => ['any value'],
    ];

    /**
     * Контракт на поля формы. Описание валидаторов.
     *
     * Не задаю свои сообщения об ошибках, эта возможность проверяется другим тестом.
     *
     * @var array
     */
    private $contract = [
        'path' => [
            'validators' => [
                'required' => true,

                'filter_var' => [
                    'filter'  => FILTER_CALLBACK,
                    'options' => [self::class, 'normalizePath'],
                ],

                'limits' => ['max' => 12,],
            ],
        ],

        'namespace'       => [
            'validators' => [
                'filter_var' => [
                    'filter'  => FILTER_VALIDATE_REGEXP,
                    'options' => ['regexp' => '~^[0-9a-z\_-]+$~i'],
                ],
            ],
        ],

        // Принять, как есть, без валидации
        'do_not_validate' => null,

        'email'   => [
            'validators' => [
                'email' => ['black_servers' => ['server.com'],],
            ],
        ],

        // Вложенные именования полей формы. Запрос типа db[server]=xxx&db[base]=yyy
        // Тут же - набор дефолтных значений
        'db'      => [
            // поле принять без проверок. Установить дефолтное значение, если тут NULL
            'switch' => [
                'default' => 3,
            ],

            'server' => [
                'default'    => 'what ever',
                'validators' => ['normalize_string' => true,],
            ],

            'base' => [
                // Такое значение по умолчанию не имеет практического смысла, но не должно ронять приложение
                'default'    => null,
                'validators' => [
                    'expect_array'     => false, // умышленно добавил, чтобы проверить, что функционал не отвалится
                    'normalize_string' => true,
                    'limits'           => ['max' => 50,],
                ],
            ],
        ],

        // Пример валидации массива однотипных значений. Запрос типа modules[]=aaa&modules[]=bbb
        'modules' => [
            'validators' => [
                'expect_array'     => true,
                'normalize_string' => true,
            ],
        ],

        // Подстановка дефолтного значения через валидатор filter_var(). При этом ошибка валидации никогда не возникнет.
        'IP'      => [
            'validators' => [
                'filter_var' => [
                    'filter'  => FILTER_VALIDATE_IP,
                    'options' => ['default' => '127.0.0.1'],
                    'flags'   => FILTER_FLAG_IPV4,
                ],
            ],
        ],

        'page'           => [
            'validators' => ['expect_id' => ['message' => 'Неверный номер страницы'],],
        ],

        // Ожидаем непустой массив. Значения не важны.
        'required_array' => [
            'validators' => [
                'expect_array' => true,
                'required'     => true,
            ],
        ],
    ];
}
