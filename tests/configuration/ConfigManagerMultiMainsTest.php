<?php
use kira\configuration\ConfigManager;
use kira\configuration\PhpConfigProvider;
use PHPUnit\Framework\TestCase;

/**
 * Интеграционный тест на реальную пошаговую загрузку конфигов с зависимостями.
 */
class ConfigManagerMultiMainsTest extends TestCase
{
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
}
