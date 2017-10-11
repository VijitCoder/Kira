<?php
namespace kira\validation\validators;

use kira\core\App;
use kira\exceptions\FormException;

/**
 * Абстрактный класс для реализации любого валидатора
 * @property-read mixed  $value проверенное значение
 * @property-read string $error сообщение об ошибке валидации
 */
abstract class AbstractValidator
{
    /**
     * Проверенное значение
     *
     * Если значение валидное, оно может так же быть приведено к нужному типу, или очищенно от лишних символов и т.п.
     * Это свойство хранит новое чистое значение.
     *
     * @var mixed
     */
    protected $value;

    /**
     * Библиотека возможных сообщений об ошибках, если валидатор их поддерживает несколько
     * @var array одномерный ассоциативный массив
     */
    protected $messageLibrary = [];

    /**
     * Окончательное сообщение об ошибке валидации
     *
     * Если валидатор может вернуть только одно сообщение, пишем его тут. Иначе оно будет выбрано из библиотеки
     * возможных сообщений и записано сюда по факту валидации в соответствии с ошибкой.
     *
     * @var string
     */
    protected $error;

    /**
     * Дефолтные параметры валидатора. После создания объекта тут хранятся запрошенные настройки валидатора, которые
     * могут переопределить дефолтные.
     * @var mixed
     */
    protected $options = [];

    /**
     * Запоминаем настройки валидатора
     *
     * @param array|true $options настройки валидатора
     * @throws FormException
     */
    public function __construct($options)
    {
        if ($options === true) {
            $options = [];
        }

        if (!is_array($options)) {
            throw new FormException('Неправильно описаны настройки валидатора. Ожидается либо массив, либо TRUE.');
        };

        $options = array_merge($this->options, $options);

        $this->prepareMessageLibrary($options['message'] ?? '');
        unset($options['message']);

        $this->options = $options;
    }

    /**
     * Подготовка кастомных сообщений об ошибках. Перевод дефолтных сообщений.
     *
     * Валидатор может иметь дефолтное сообщение, которое можно заменить. У некоторых валидаторов может быть несколько
     * сообщений по принципу или-или в результате проверки. Такие сообщения тоже можно заменить один-к-одному или
     * на одно общее сообщение на все случаи ошибок. Любая замена делается через $options['message'].
     *
     * Прогоняем через переводчик всё, что есть. Если в итоге сообщений несколько - пишем их в библиотеку, валидатор
     * должен сам брать оттуда нужное. Если сообщение в валидаторе может быть только одно, сразу присваиваем его
     * в self::$error.
     *
     * @param string|array $message сообщение, заданное в self::$options['message'] или переопределенное через настройку
     */
    protected function prepareMessageLibrary($message)
    {
        $this->messageLibrary = array_map([App::class, 't'], $this->messageLibrary);

        if (!$message) {
            $this->error = App::t($this->error);
            return;
        }

        $message = App::t($message);
        $library = $this->messageLibrary;

        if ($library) {
            if (is_array($message)) {
                $library = array_merge($library, $message);
            } else {
                foreach ($library as &$item) {
                    $item = $message;
                }
            }
            $this->messageLibrary = $library;
        } else {
            $this->error = $message;
        }
    }

    /**
     * Магический геттер валидированного значения и сообщения об ошибке валидации
     * @param string $name имя свойства класса
     * @return mixed
     * @throws FormException
     */
    public function __get(string $name)
    {
        if ($name == 'error') {
            return $this->error;
        } else if ($name == 'value') {
            return $this->value;
        } else {
            throw new FormException('Неизвестное свойство класса валидатора - ' . $name);
        }

    }

    /**
     * Функция валидации
     *
     * Она никуда не сохраняет результат валидации. Все предельно просто: она только проверяет значение по заданной
     * логике и согласно настроек валидатора.
     *
     * @param mixed $value проверяемое значение
     * @return bool
     * @throws FormException
     */
    abstract public function validate($value);
}
