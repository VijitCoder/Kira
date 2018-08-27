<?php
use kira\configuration\AbstractConfigProvider;
use kira\configuration\ConfigManager;
use kira\exceptions\ConfigException;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

/**
 * Тестируем менеджер конфигурации приложения
 */
class ConfigManagerTest extends TestCase
{
    /** @var ConfigManager */
    private $manager;

    public function setUp()
    {
        $configSources = new \ArrayIterator([
            'main.php' => [
                'domain' => 'http://server.com',
                'env'    => 'local',
                'auth'   => [
                    'login' => [
                        'tries'   => 5,
                        'penalty' => 10,
                    ],
                ],
            ],
        ]);

        $provider = $this->createMock(AbstractConfigProvider::class);
        $provider
            ->method('loadConfiguration')
            ->willReturn($configSources);

        ConfigManager::reset();
        $this->manager = ConfigManager::getInstance($provider);
    }

    /**
     * Тест: Чтение конечного значения из конфигурации.
     */
    public function test_getValue()
    {
        $this->assertEquals(5, $this->manager->getValue('auth.login.tries'));
    }

    /**
     * Тест: Чтение блока настроек из конфигурации.
     */
    public function test_getValuesBlock()
    {
        $this->assertEquals(['tries' => 5, 'penalty' => 10], $this->manager->getValue('auth.login'));
    }

    /**
     * Тест: Чтение несуществующего значения из конфигурации.
     *
     * Две разных реакции, в зависимости от требуемой строгости.
     */
    public function test_getAbsentValue()
    {
        $this->assertNull($this->manager->getValue('wrong_key', false));

        $this->expectException(ConfigException::class);
        $this->manager->getValue('wrong_key');
    }

    /**
     * Тест: Установка нового значения в конфигурацию
     */
    public function test_setValue()
    {
        $key = 'auth.login.tries';

        // Сначала проверяем, что в конфиге исходное значение
        $this->assertEquals(5, $this->manager->getValue($key));

        // Переписываем значение и проверяем, что оно действительно изменилось
        $newValue = 12;
        $this->manager->setValue($key, $newValue);
        $this->assertEquals($newValue, $this->manager->getValue($key));
    }

    /**
     * Тест: Перезапись целого блока значений в конфигурации
     */
    public function test_setValuesBlock()
    {
        // Ключ указывает на целый блок настроек, относящихся к логину.
        $key = 'auth.login';

        // Сначала проверяем, что в конфиге исходное значение
        $this->assertEquals(['tries' => 5, 'penalty' => 10], $this->manager->getValue('auth.login'));

        // Переписываем значение и проверяем, что оно действительно изменилось
        $newValue = ['tries' => 7, 'penalty' => 20];
        $this->manager->setValue($key, $newValue);
        $this->assertEquals($newValue, $this->manager->getValue($key));

        // Меняем значение до неузнаваемости. Да, и так тоже можно, хотя на практике вряд ли нужно.
        $newValue = 'whatever';
        $this->manager->setValue($key, $newValue);
        $this->assertEquals($newValue, $this->manager->getValue($key));
    }

    /**
     * Тест: Попытка установить значение неизвестному ключу в конфигурации
     */
    public function test_setWrongValue()
    {
        $this->expectException(ConfigException::class);
        $this->manager->setValue('wrong_key', 'some');
    }
}
