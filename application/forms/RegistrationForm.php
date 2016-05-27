<?php
/**
 * Форма регистрации
 */
namespace app\forms;

class RegistrationForm extends \engine\web\Form
{
    /** @var array фильтры полей html-формы. Каждая запись - описание валидатора */
    protected $filters = [
        'login' => [
            'filter'   => FILTER_VALIDATE_REGEXP,
            'options'  => ['regexp' => '~^[a-z0-9-_]+$~i'],
            'message'  => 'Недопустимые символы',
            'required' => true,
            'max'      => 30,
        ],

        'password' => [
            'filter'   => ['\engine\utils\Validators', 'password'],
            'options'  => [
                'min_len'  => 5,
                'min_comb' => 3,
            ],
            'required' => true,
            'max'      => 30,
        ],

        'mail' => [
            'filter'   => ['\engine\utils\Validators', 'mail'],
            // список серверов читаем из настройки в конструкторе этого класса
            'required' => true,
            'max'      => 50,
        ],

        'firstname' => [
            'filter'  => FILTER_VALIDATE_REGEXP,
            'options' => ['regexp' => '~^[\sa-zа-яё-]*$~ui'],
            'message' => 'Недопустимые символы',
            'max'     => 50,
        ],

        'secondname' => [
            'filter'  => FILTER_VALIDATE_REGEXP,
            'options' => ['regexp' => '~^[\sa-zа-яё-]*$~ui'],
            'message' => 'Недопустимые символы',
            'max'     => 50,
        ],

        'sex' => [
            'filter'  => FILTER_VALIDATE_REGEXP,
            'options' => ['regexp' => '~^none|male|female$~'],
            'message' => 'неверно указан пол',
        ],

        'birth_date' => [
            'filter'  => ['\engine\utils\Validators', 'date'],
        ],

        'town' => [
            'filter'  => FILTER_CALLBACK,
            'options' => ['\engine\utils\Validators', 'normalizeString'],
            'max'     => 100,
        ],

        'avatar' => null,
    ];

    public function __construct()
    {
        $this->filters['mail']['options']['black_servers'] = require APP_PATH . 'conf/black_servers.php';
        parent::__construct();
    }
}