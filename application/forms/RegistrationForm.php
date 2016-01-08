<?php
/**
 * Форма регистрации
 */
namespace app\forms;

class RegistrationForm extends \core\Form
{
    /** @var array фильтры полей html-формы. Каждая запись - описание валидатора */
    protected $filters = [
        'login' => [
            'filter'  => FILTER_VALIDATE_REGEXP,
            'options' => ['regexp' => '~^[a-z0-9-_]+$~i'],
            'msg' => 'Недопустимые символы',
            'required' => true,
            'max' => 30,
        ],

        'password' => [
            'filter'  => FILTER_CALLBACK,
            'options' => ['Validator', 'password'],
            'required' => true,
            'max' => 30,
        ],

        'mail' => [
            'filter'  => FILTER_CALLBACK,
            'options' => ['Validator', 'mail'],
            'required' => true,
            'max' => 50,
        ],

        'firstname' => [
            'filter'  => FILTER_VALIDATE_REGEXP,
            'options' => ['regexp' => '~^[\sa-zа-яё-]*$~ui'],
            'msg' => 'Недопустимые символы',
            'max' => 50,
        ],

        'secondname' => [
            'filter'  => FILTER_VALIDATE_REGEXP,
            'options' => ['regexp' => '~^[\sa-zа-яё-]*$~ui'],
            'msg' => 'Недопустимые символы',
            'max' => 50,
        ],

        'sex' => [
            'filter'  =>  FILTER_VALIDATE_REGEXP,
            'options' => ['regexp' => '~^none|male|female$~'],
            'msg' => 'неверно указан пол',
        ],

        'birth_date' => [
            'filter'  => FILTER_CALLBACK,
            'options' => ['Validator', 'date'],
        ],

        'town' => [
            'filter'  => FILTER_CALLBACK,
            'options' => ['Validator', 'normalizeString'],
            'max' => 100,
        ],

        'avatar' => [],
    ];
}