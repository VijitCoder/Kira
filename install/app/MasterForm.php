<?php
/**
 * Форма создания нового приложения
 */
namespace install\app;

class MasterForm extends \engine\web\Form
{
    protected $contract = [
        'path' => [
            'expectArray' => true,
            'validators'  => null, // валидаторы заряжает конструктор
        ],

        'main_conf' => [
            'validators' => null,  // валидаторы заряжает конструктор
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
                    'max' => 50,
                    'message' => 'Очень длинный email. Максимум 50 символов',
                ],
            ],
        ],

        'db' => [
            'expectArray' => true,
            'validators'  => [
                'filter_var' => [
                    'filter'  => FILTER_CALLBACK,
                    'options' => ['\engine\utils\Validators', 'normalizeString'],
                ],

                'length' => [
                    'max' => 100,
                    'message' => 'Поля этого блока ограничены длиной 100 символов',
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
                        'min' => 1,  //проверка нужна. Предыдущий валидатор может укоротить строку
                        'max' => 50,
                        'message' => 'Имя таблицы должно быть в пределах [1, 50] символов',
                    ],
                ],
            ],

            'path' => [
                'validators' => null, // валидаторы заряжает конструктор
            ],

            'timezone' => [
                'validators' => [
                    'filter_var' => [
                        'filter'  => FILTER_VALIDATE_REGEXP,
                        'options' => ['regexp' => '~^[0-9a-z_+-/]*$~i'],
                        'message' => 'Недопустимые символы в часовом поясе. Ожидается [0-9a-z_+-/]',
                    ],

                    'length' => [
                        'max' => 50,
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
                        'filter'  => FILTER_VALIDATE_REGEXP,
                        'options' => ['regexp' => '~^[a-z\s,]*$~i'],
                        'message' => 'Недопустимые символы в кодах языков. Ожидается [a-z\s,]',
                    ],

                    'length' => ['max' => 100, 'message' => 'Слишком длинная строка кодов. Максимум 100 символов'],
                ],
            ],

            'js_path' => [
                'validators' => null, // валидаторы заряжает конструктор
            ],
        ],
    ];

    /**
     * MasterForm constructor.
     */
    public function __construct()
    {
        $pathValidators = [
            'required' => true,

            'length' => [
                'max'     => 1000,
                'message' => 'Очень длинный путь. Максимум 1000 символов',
            ],
        ];

        $this->contract['path']['validators'] = $pathValidators;
        $pathValidators['required'] = ['message' => 'Не указан путь к конфигурации'];
        $this->contract['main_conf']['validators'] = $pathValidators;
        $pathValidators['required'] = false;
        $this->contract['log']['path']['validators'] = $pathValidators;
        $this->contract['lang']['js_path']['validators'] = $pathValidators;

        parent::__construct();
    }
}
