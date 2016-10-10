<?php
namespace kira;

use kira\exceptions\WriteException;
use kira\exceptions\ReadException;

/**
 * Глобальное хранилище данных приложения
 *
 * Шаблоны проектирования - Singleton, Registry + доп.фичи.
 */
final class Registry implements Serializable
{
    /**
     * Объект этого класса
     * @var self
     */
    private $instance;

    /**
     * Внутреннее хранилище данных
     * @var array
     */
    private $stack = [];

    /**
     * Получение объекта реестра в единственном экземпляре
     * @return Registry
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * Магический сеттер
     *
     * Никакой реакции при перезаписи данных по существующему ключу
     *
     * @param string $prop  в контексте этого класса - ключ в массиве внутреннего хранилища
     * @param mixed  $value значение для записи в хранилище
     */
    public function __set($prop, $value)
    {
        $this->stack[$prop] = $value;
    }

    /**
     * Магический геттер
     *
     * Никакой реакции на отсуствие данных по ключу
     *
     * @param string $prop в контексте этого класса - ключ в массиве внутреннего хранилища
     * @return mixed
     */
    public function __get($prop)
    {
        return $this->stack[$prop] ?? null;
    }

    /**
     * Проверка существования значения в хранилище по ключу
     * @param $key ключ в массиве внутреннего хранилища
     * @return bool
     */
    public function isExists($key)
    {
        return isset($this->stack[$key]);
    }

    /**
     * Запись значения в хранилище
     *
     * Принципиальная разница с магическим сеттером: если запрещена перезапись и значение уже есть - пробросим
     * исключение. Для более мягкой реакции см. метод self::isExists()
     *
     * @param string $key     ключ в массиве внутреннего хранилища
     * @param mixed  $value   значение для сохранения
     * @param bool   $rewrite разрешить перезапись значения при совпадении ключа
     * @throws WriteException
     * @internal param string $prop
     */
    public function set($key, $value, $rewrite = false)
    {
        if (!$rewrite && $this->isExists($key)) {
            throw new WriteException("Реестр уже хранит значение с заданным ключом ($key) и запрещена перезапись");
        }
        $this->stack[$key] = $value;
    }

    /**
     * Чтение значения из хранилища
     *
     * Принципиальная разница с магическим геттером: если значение не найдено и параметром задана строгая реакция -
     * пробросим исключение. Для более мягкой реакции см. метод self::isExists()
     *
     * Если режим нестрогий и значение не найдено, метод вернет NULL. Это может ввести в заблужение, поэтому нужно
     * правильно выбрать стратегию в клиентском коде.
     *
     * @param string $key    ключ в массиве внутреннего хранилища
     * @param bool   $strict строгая реакция на отсутствие значения
     * @return mixed
     * @throws ReadException
     */
    public function get($key, $strict = true)
    {
        if ($strict && $this->isExists($key)) {
            throw new ReadException(
                "В реестре не найдено значение по заданному ключу ($key) и установлен строгий режим реакции");
        }
        return $this->stack[$key] ?? null;
    }

    /**
     * Удаление значения из реестра
     * @param $key ключ в массиве внутреннего хранилища
     */
    public function delete($key)
    {
        unset($this->stack[$key]);
    }

    /**
     * Пробрасываем обычные функции работы с массивами на внутреннее хранилище реестра
     *
     * Теперь возможен вызов типа Registry::getInstance()->array_keys()
     *
     * @link http://php.net/manual/ru/class.arrayobject.php#107079
     * @param string $func
     * @param array  $argv
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call(string $func, array $argv)
    {
        if (!is_callable($func) || substr($func, 0, 6) !== 'array_') {
            throw new \BadMethodCallException(__CLASS__ . '->' . $func);
        }
        array_unshift($argv, $this->stack);
        return call_user_func_array($func, $argv);
    }

    /**
     * Поддержка сериализации класса
     * @see http://php.net/manual/ru/class.serializable.php
     * @return string
     */
    public function serialize()
    {
        return serialize($this->stack);
    }

    /**
     * Поддержка десериализации класса
     * @return string
     */
    public function unserialize($data)
    {
        $this->stack = unserialize($data);
    }

    /**
     * Запрещаем любое размножение объекта. Установка private доступа к этим методам не позволит выполнить
     * соответствующие действия над объектом из клиентского кода.
     */
    private function __construct()
    {
    }

    private function __clone()
    {
    }
}
