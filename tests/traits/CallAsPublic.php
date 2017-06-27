<?php
namespace kira\tests\traits;

/**
 * Вызываем непубличные методы для возможности их протестировать
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
     * @param array  $args       параметры в вызываемый метод
     * @return mixed
     */
    protected function callMethod($class, string $methodName, array $args = [])
    {
        $method = new \ReflectionMethod($class, $methodName);
        $method->setAccessible(true);
        $classObject = $method->isStatic() ? null : (is_object($class) ? $class : new $class);
        return $method->invokeArgs($classObject, $args);
    }
}
