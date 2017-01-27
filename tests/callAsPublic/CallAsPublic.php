<?php
/**
 * Вызываем непубличные методы для возможности их протестировать
 *
 * Трейт подключен напрямую, в bootstrap.php тестового окружения.
 */
trait CallAsPublic
{
    /**
     * Вызов непубличного метода класса используя рефлексию класса
     *
     * Эта функция вернет то, что должен вернуть вызываемый метод.
     *
     * @param mixed  $class      FQN класса, в котором находится вызываемый метод ИЛИ объект этого класса
     * @param string $methodName имя вызываемого метода
     * @param array  ...$args    параметры для передачи в метод
     * @return mixed
     */
    protected function callMethod($class, string $methodName, ...$args)
    {
        $method = new ReflectionMethod($class, $methodName);
        $method->setAccessible(true);
        $classObject = $method->isStatic() ? null : (is_object($class) ? $class : new $class);
        return $method->invoke($classObject, ...$args);
    }
}
