<?php
use kira\configuration\ConfigManager;
use kira\configuration\PhpConfigProvider;
use PHPUnit\Framework\TestCase;

/**
 * Интеграционный тест на реальную пошаговую загрузку конфигов с зависимостями.
 */
class ConfigManagerMultiMainsTest extends TestCase
{
    /**
     * Менеджер конфигурации реализует шаблон проектирования "Одиночка" (Singleton). И я периодически забываю сбрасывать
     * его состояние после тестов какого-нибудь классса. Тем более, там неявная связь. Проще поготовить его перед
     * тестом тут.
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        ConfigManager::reset();
    }

    public function test_multi_mains_dependency()
    {
        $provider = new PhpConfigProvider(__DIR__ . '/fixtures/main.php');
        $value = ConfigManager::getInstance($provider)->getValue('wrapper');
        $expect = [
            'domain' => 'server.com',
            'routes' => '...',
            'SCP'    => 'report-uri server.com',
        ];

        $this->assertEquals($expect, $value);
    }

    /**
     * Менеджер конфигурации реализует шаблон проектирования "Одиночка" (Singleton), нужно сбрасывать его состояние
     * между тестами.
     */
    public function tearDown()
    {
        ConfigManager::reset();
    }
}
