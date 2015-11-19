<?php
/**
 * Супер-класс моделей форм (валидации форм)
 */
class Form
{
    /**
     * @var array фильры полей html-формы. Каждая запись - описание валидатора, пример в RegistrationForm.
     * Этот массив - контракт на поля. Если фильтрация не нужна, указывай пустой элемент, иначе поле
     * вообще не будет загружено в модель.
     */
    protected $filters;

    /**
     * Пример:
     * $filters = [
     *      'filter' => FILTER_*,
     *      'options' => [...], //по правилам filter_var()
     *      'msg' => 'ошибка такая-то',
     *      'required' => true,
     *      //Длина строки. Вынес отдельно от фильтров, мне так нужно.
     *      'min' => 10,
     *      'max' => 100,
     * ]
     */

    /** @var array данные с формы */
    private $_rawdata;

    /** @var array данные после валидации. Имя переменной специально подобрано, кроме прочего переводится как "ценности" */
    private $_values;

    /** @var array ошибки валидации. [имя_поля => массив_ошибок[]] */
    private $_errors;

    /**
     * @var bool флаг результата валидации, успешно или нет. Добавление других ошибок через addError() не должно
     * менять этот флаг.
     */
    public $isValid;

    public function __construct()
    {
        //инициализация массивов формы. Контроллер может отдать пустую модель (без вызова self::load()),
        //нужно, чтоб списки были, пусть и без значений.
        $this->_rawdata =
        $this->_errors =
        $this->_values = array_fill_keys(array_keys($this->filters), null);
    }

    /**
     * Загрузка в класс сырых данных c формы. Инициализация массивов формы.
     * @param array &$arr исходные данные
     * @return object указатель на себя для поддержания вызова по цепочке
     */
    public function load(&$arr)
    {
        //заполняем только массив сырых данных. Массив ошибок забьет валидация.
        $this->_rawdata = array_merge($this->_rawdata, $arr);

        return $this;
    }

    /**
     * Вернуть сырые данные. Весь массив или конкретный элемент. Обычно шаблоны используют для восстановления
     * формы при ошибках валидации.
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
     * @param string $key ключ в массиве данных
     * @return array | string
     */
    public function getValues($key = null)
    {
        return $this->_getData($this->_values, $key);
    }

    /**
     * Вернуть сообщения об ошибках валидации. Весь массив или конкретный элемент. Если ошибки нет, будет пустой
     * элемент, иначе - все сообщения, склееные через <br>.
     *
     * @param string $key ключ в массиве данных
     * @return array [поле => все ошибки поля в одну строку]
     */
    public function getErrors($key = null)
    {
        return $this->_getData($this->_errors, $key);
    }

    /**
     * Работающий метод. Обращается к private массивам класс и возвращает их данные по запросу.
     * @param array  &$data массив данных в текущем классе
     * @param string $key ключ в массиве данных
     * @return array | string
     */
    private function _getData(&$data, $key)
    {
        if ($key === null) {
            return $data;
        }

        return $data[$key];
    }

    /**
     * Добавить сообщение в массив ошибок. Обычно сервис хочет что-то дописать, чтоб шаблон показал.
     * Например, валидация логина прошла, но он, оказывается, занят уже. Это может сказать только сервис.
     *
     * @param string $key ключ в массиве данных
     * @param string $msg сообщение
     * @return array [поле => все ошибки поля в одну строку]
     */
    public function addError($key, $msg)
    {
        if (isset($this->_errors[$key])) {
            //см. так же self::validate() по части объединения ошибок
            $this->_errors[$key] .= ". {$msg}";
        } else {
            $this->_errors[$key] = $msg;
        }
    }

    /**
     * Есть в модели информация об ошибках? Необязательно ошибки валидации. Вообще какие-нибудь есть?
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
     * Свое значение в массив валидированных данных
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
     * Валидация по заданным фильтрам. Ошибки пишутся в сессию
     * Проверяем вообще все, что возможно, не прерываемся на первой же ошибке. Так удобнее юзеру,
     * указать сразу на все косяки.
     * @return bool
     */
    public function validate()
    {
        //пустой массив, т.е. данные передали с формы, но подходящих для модели нет.
        if (!$data = $this->_rawdata) return false;

        $result = $errors = array();
        $valid = true;
        $dummy = ['filter' => null, 'func'=> null, 'options' => null, 'msg' => '', 'required' => false,
            'min' => 0, 'max' => 0];

        //Перебираем именно фильтры, а не загруженные данные. Фильтры - контракт на поля, данные могут быть неполными.
        foreach ($this->filters as $k => $f) {
            //нет требований фильтрации
            if (!$f) {
                $result[$k] = $data[$k];
                continue;
            }

            //чтоб каждое на isset() не проверять. Так код читабельнее будет
            $tmp = array_merge($dummy, $this->filters[$k]);
            extract($tmp);

            # Необходимое поле

            if ($required && (!isset($data[$k]) || empty($data[$k]))) {
                $errors[$k] = [App::t('Поле должно быть заполнено')];
                $valid = false;
                continue;
            }

            //пустое НЕобязательное поле не нужно проверять. Его может даже не быть.
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

            //Длину проверяем именно у валидированного значения. Возможно была еще и дезинфекция (sanitize)
            if ($min && $len < $min) {
                $valid = $result[$k] = false;
                $errors[$k][] = App::t('Слишком короткое значение, минимум M', ['M' => $min]);

            } elseif ($max && $len > $max) {
                $valid = $result[$k] = false;
                $errors[$k][] = App::t('Слишком длинное значение, максимум M', ['M' => $max]);
            }
        }

        //Ошибки - массив с сообщениями по каждому полю. Объединяем все ошибки одного поля, сейчас - через пробел.
        //Можно сделать иное оформление, например через <p>...</p>. см. также self::addError()
        if ($errors) {
            foreach ($errors as &$v) {
                $v = implode('. ', $v);
            }
        }

        $this->_errors = array_merge($this->_errors, $errors);
        $this->isValid = $valid;
        $this->_values = $result;

        return $valid;
    }
}
