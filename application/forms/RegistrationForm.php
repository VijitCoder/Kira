<?php
/**
 * Форма регистрации
 */
namespace app\forms;

class RegistrationForm extends \engine\web\Form
{
    protected $contract = [
        'login' => [
            'validators' => [
                'filter_var' => [
                    'filter'  => FILTER_VALIDATE_REGEXP,
                    'options' => ['regexp' => '~^[a-z0-9-_]+$~i'],
                    'message' => 'Недопустимые символы',
                ],

                'required' => true,

                'length' => ['max' => 30,],
            ],
        ],

        'password' => [
            'validators' => [
                'external' => [
                    'function' => ['\engine\utils\Validators', 'password'],
                    'options'  => [
                        'min_len'  => 5,
                        'min_comb' => 3,
                    ],
                ],

                'required' => true,

                'length' => ['max' => 30,],
            ],
        ],

        'mail' => [
            'validators' => [
                'external' => [
                    'function' => ['\engine\utils\Validators', 'mail'],
                    // список серверов читаем из настройки в конструкторе этого класса
                ],

                'required' => true,

                'length' => ['max' => 50,],
            ],
        ],

        'firstname' => [
            'validators' => [
                'filter_var' => [
                    'filter'  => FILTER_VALIDATE_REGEXP,
                    'options' => ['regexp' => '~^[\sa-zа-яё-]*$~ui'],
                    'message' => 'Недопустимые символы',
                ],

                'length' => ['min' => 2, 'max' => 50,],
            ],
        ],

        'secondname' => [
            'validators' => [
                'filter_var' => [
                    'filter'  => FILTER_VALIDATE_REGEXP,
                    'options' => ['regexp' => '~^[\sa-zа-яё-]*$~ui'],
                    'message' => 'Недопустимые символы',
                ],

                'length' => ['min' => 2, 'max' => 50,],
            ],
        ],

        'sex' => [
            'validators' => [
                'filter_var' => [
                    'filter'  => FILTER_VALIDATE_REGEXP,
                    'options' => ['regexp' => '~^none|male|female$~'],
                    'message' => 'неверно указан пол',
                ],
            ],
        ],

        'birth_date' => [
            'validators' => [
                'external' => [
                    'function' => ['\engine\utils\Validators', 'date'],
                ],
            ],
        ],

        'town' => [
            'validators' => [
                'filter_var' => [
                    'filter'  => FILTER_CALLBACK,
                    'options' => ['\engine\utils\Validators', 'normalizeString'],
                ],

                'length' => ['min' => 2, 'max' => 100,],
            ],
        ],

        'avatar' => null,
    ];

    public function __construct()
    {
        $this->contract['mail']['validators']['external']['options'] = [
            'black_servers' => require APP_PATH . 'conf/black_servers.php'
        ];
        parent::__construct();
    }
}
