<?php
/**
 * Супер-класс моделей форм (валидации форм)
 */

namespace engine\web;

class Form
{
    /**
     * @var array фильтры полей html-формы.
     *
     * Этот массив - контракт на поля. Каждая запись - описание валидатора. Если фильтрация не нужна, указываем пустой
     * элемент, иначе поле вообще не будет загружено в модель.
     *
     * Ключи 'filter' и 'options' описываются по правилам php::filter_var()
     * {@link http://php.net/manual/ru/function.filter-var.php}
     *
     * Константы для 'filter' см. в {@link http://php.net/manual/ru/filter.filters.php}
     */
    protected $filters;

    /**
     * Пример всех возможных полей:
     * $filters = [
     *      'filter' => FILTER_*,
     *      'options' => [...], //по правилам filter_var()
     *      'msg' => 'ошибка такая-то',
     *      'required' => true,
     *      //Длина строки. Вынес отдельно от фильтров, это удобно.
     *      'min' => 10,
     *      'max' => 100,
     * ]
     */

    /** @var array данные с формы. По умолчанию массив заполнен ключами, но без данных. */
    private $_rawdata;

    /**
     * @var array данные после валидации. Имя переменной специально подобрано, кроме прочего переводится
     * как "ценности". По умолчанию массив заполнен ключами, но без данных.
     */
    private $_values;

    /**
     * @var array ошибки валидации. [имя_поля => массив_ошибок[]]. По умолчанию массив заполнен ключами, но без данных.
     */
    private $_errors;

    /**
     * @var bool флаг результата валидации, успешно или нет.
     *
     * Добавление других ошибок через addError() <b>не должно менять</b> этот флаг. Он только для информации
     * о проведенной валидации. У него есть свой геттер. Для проверки наличия ошибок в целом есть метод hasErrors().
     */
    private $_isValid;

    /**
     * Инициализация массивов формы. Контроллер может отдать пустую модель (без вызова self::load()), нужно, чтоб списки
     * были, пусть и без значений.
     */
    public function __construct()
    {
        $this->_rawdata =
        $this->_errors =
        $this->_values = array_fill_keys(array_keys($this->filters), null);
    }

    /**
     * Загрузка в класс сырых данных c html-формы.
     *
     * Суть: в конструкторе подготовлен ассоциативный массив, у него объявлены только ключи согласно "контракта"
     * {@see Form::$filters}. Данных в массиве нет. Здесь объединяем пустой массив с данными. Все левое в нем тоже
     * сохранится, но валидатор проигнорирует поля, не заявленные в контракте. Т.е. в сырых данных будет всё, а в
     * проверенных (self::$_values) - только то, что заявлено в контракте.
     *
     * @param array &$arr исходные данные
     * @return object указатель на себя для поддержания вызова по цепочке
     */
    public function load(&$arr)
    {
        $this->_rawdata = array_merge($this->_rawdata, $arr);
        return $this;
    }

    /**
     * Вернуть сырые данные. Весь массив или конкретный элемент. Обычно его используют шаблоны для восстановления
     * формы при ошибках валидации.
     *
     * @param string $key ключ в массиве данных
     * @return array | string
     */
    public function getRawdata($key = null)
    {
        return $this->_getData($this->_rawdata, $key);
    }

    /**
     * Вернуть результаты валидации. Весь массив или конкретный элемент. В каждом элементе либо строка
     * либо false (не прошло валидацию). Инфу о результе валидации в целом можно узнать по по флагу self::$isValid
     *
     * @param string $key ключ в массиве данных
     * @return array | string
     */
    public function getValues($key = null)
    {
        return $this->_getData($this->_values, $key);
    }

    /**
     * Вернуть сообщения об ошибках. Весь массив или конкретный элемент. Если ошибки нет, будет пустой элемент.
     * Зачем возвращать ошибки в массиве, а не в строке? Чтобы была возможность оформить каждую из них в html-теги.
     *
     * Важно помнить, что по умолчанию массив заполнен ключами, но без данных. Это работа конструктора модели.
     *
     * @see Form::getErrorsAsString()
     *
     * @param string $key ключ в массиве данных
     * @return array [поле => массив ошибок]
     */
    public function getErrors($key = null)
    {
        return $this->_getData($this->_errors, $key);
    }

    /**
     * Собираем ошибки по каждому полю в строку. Если ключ задан, значит только ошибки конкретного поля.
     * Этот метод - синтаксический сахар, когда нет необходимости оборачивать каждую ошибку в свои html-теги.
     *
     * Важно помнить, что по умолчанию массив заполнен ключами, но без данных. Это работа конструктора модели.
     *
     * @see Form::getErrors()
     *
     * @param null $key
     * @return mixed
     */
    public function getErrorsAsString($key = null)
    {
        if ($errors = $this->getErrors($key)) {
            if ($key) {
                $errors = implode(' ', $errors);
            } else {
                foreach ($errors as &$v) {
                    if ($v) {
                        $v = implode(' ', $v);
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Работающий метод. Обращается к private массивам класса и возвращает их данные по запросу.
     *
     * @param array  &$data массив данных в текущем классе
     * @param string $key ключ в массиве данных
     * @return array | string | null
     */
    private function _getData(&$data, $key)
    {
        if ($key === null) {
            return $data;
        }

        return isset($data[$key]) ? $data[$key] : null;
    }

    /**
     * Добавить сообщение в массив ошибок. Обычно сервис хочет что-то дописать, чтоб шаблон показал. Например, валидация
     * логина прошла, но он, оказывается, занят уже. Это может сказать только сервис.
     *
     * Важно помнить, что по умолчанию массив заполнен ключами, но без данных. Это работа конструктора модели.
     *
     * @param string $key ключ в массиве данных
     * @param string $msg сообщение
     * @return void
     */
    public function addError($key, $msg)
    {
        $this->_errors[$key][] = $msg;
    }

    /**
     * Есть в модели информация об ошибках? Необязательно ошибки валидации. Вообще какие-нибудь есть?
     *
     * В методе сложная проверка. Она необходима! По умолчанию массив заполнен ключами, но без данных. Заполнение
     * происходит в конструкторе. Так сделано, чтобы была возможность использовать пустую инициализированную модель
     * формы.
     *
     * @return bool
     */
    public function hasErrors()
    {
        foreach ($this->_errors as $v) {
            if ($v) return true;
        }
        return false;
    }

    /**
     * Свое значение в массив валидированных данных.
     *
     * Наличие поля не проверяется, что дает больше возможностей для управления массивом итоговых данных.
     *
     * @TODO это плохо или хорошо? С одной стороны, можно добавить что-то после валидации, например, спец.данные,
     * нужные программисту. С другой же стороны, ошибка кодера в имени поля может оказаться незамеченной.
     *
     * @param string $key ключ в массиве данных
     * @param string $val значение
     * @return void
     */
    public function setValue($key, $val)
    {
        $this->_values[$key] = $val;
    }

    /**
     * Валидация полей html-формы по заданным в модели фильтрам.
     *
     * Перед валидацией должен быть вызван метод Form::load() для загрузки данных в модель. Перебираем именно фильтры,
     * а не загруженные данные. Фильтры - контракт на поля, данные могут быть неполными.
     *
     * Если фильтр неопределен - есть только ключ в массиве $filters, но без значения, - тогда принимаем данные поля
     * "как есть", вообще без проверок.
     *
     * Если валидатором назначена callback-функция, она должна принимать один параметр - проверяемое значение.
     * Возвращать должна либо строку - проверенное значение, либо неассоциативный массив с текстами ошибок. Даже если
     * всего одна ошибка - все равно в массиве. По типу возвращенного значения этот метод определят результат проверки.
     *
     * Кроме непосредственно валидации проверяем требование "required" и длину значения, если они описаны в контракте.
     * Длину проверяем именно у валидированного значения. Возможно была еще и дезинфекция (sanitize).
     *
     * Проверяем вообще все, что возможно, не прерываемся на первой же ошибке. Так удобнее юзеру, указать сразу на
     * все косяки.
     *
     * @return bool успешна пройдена валидация или нет
     */
    public final function validate()
    {
        if (!$data = $this->_rawdata)
            return false;

        $result = $errors = [];
        $valid = true;
        $dummy = ['filter' => null, 'func'=> null, 'options' => null, 'msg' => '', 'required' => false,
            'min' => 0, 'max' => 0];

        foreach ($this->filters as $k => $f) {
            if (!$f) {
                $result[$k] = $data[$k];
                continue;
            }

            //чтоб каждое на isset() не проверять. Так код читабельнее будет.
            $tmp = array_merge($dummy, $this->filters[$k]);
            extract($tmp);

            # Необходимое поле

            if ($required && (!isset($data[$k]) || empty($data[$k]))) {
                $errors[$k] = [App::t('Поле должно быть заполнено')];
                $valid = false;
                continue;
            }

            if (!isset($data[$k])) {
                 continue;
            }

            if ($data[$k] == '') {
                $result[$k] = '';
                continue;
            }

            # Фильтры

            $value = $data[$k];
            $len = mb_strlen($value);

            if ($filter) {
                $res = filter_var($value, $filter, ['options' => $options]);
                if ($filter == FILTER_CALLBACK && is_array($res)) {
                    $errors[$k] = $res;
                    $value = false;
                } else {
                    $value = $res;
                }

                if ($value === false) {
                    $valid = false;
                    if ($msg) $errors[$k][] = App::t($msg);
                }

                $result[$k] = $value;
            }

            # Длина

            if ($min && $len < $min) {
                $valid = $result[$k] = false;
                $errors[$k][] = App::t('Слишком короткое значение, минимум M', ['M' => $min]);

            } elseif ($max && $len > $max) {
                $valid = $result[$k] = false;
                $errors[$k][] = App::t('Слишком длинное значение, максимум M', ['M' => $max]);
            }
        }

        $this->_errors = array_merge($this->_errors, $errors);
        $this->_isValid = $valid;
        $this->_values = $result;

        return $valid;
    }

    /**
     * Результат проведенной валидации. Геттер.
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->_isValid;
    }
}
