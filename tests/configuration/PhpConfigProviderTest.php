<?php
use kira\configuration\PhpConfigProvider;
use kira\exceptions\ConfigException;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;

/**
 * Тестируем поставщика конфигурации из php-файлов.
 */
class PhpConfigProviderTest extends TestCase
{
    /**
     * Абсолютный путь к каталогу конфигурации, созданному в виртуальной ФС
     *
     * @var string
     */
    private $configPath;

    /**
     * Конфиг на php-файлах. Они подключаются через инструкцию `php::require`. Поэтому их содержимое должно быть
     * настоящим PHP кодом, иначе тест будет падать там, где этого не должно быть.
     *
     * Каждый из файлов тут имеет значение. Тестируем не только пошаговую загрузку группы основных файлов конфигурации,
     * но загрузку конфига вообще, в разных условиях.
     */
    public function setUp(): void
    {
        $structure = [
            'main.php'   => '<?php return ["main#0" => 0];',  // Первый основной файл конфигурации в группе.
            'main2.php'  => '<?php return ["main#2" => 2];',  // Еще один файл в группе основных к загрузке.
            'main 1.php' => '<?php return ["main#1" => 1];',  // Этот файл не входит в группу, имя не подходит.
            'main3.txt'  => 'bla-bla-bla',                    // Просто текстовик :), не файл конфига в принципе.
            'routes.php' => '<?php return ["routes" => []];', // Этот файл не является одним и главных конфигов.

            // Не будет загружен как основной в группе, из подкаталогов они не подхватываются.
            'subfolder' => [
                'main.php' => '<?php return ["main" => "single"];',
            ],
        ];

        vfsStream::setup('config', null, $structure);
        $this->configPath = vfsStream::url('config') . '/';
    }

    /**
     * Тест: проверяем, что главные конфиги подхватываются в правильном порядке и списке. Сама загрузка файлов
     * не происходит, только получение списка целей к загрузке.
     */
    public function test_dryLoad()
    {
        $mains = PhpConfigProvider::dryLoad($this->configPath . 'main.php');
        $expected = [
            'vfs://config/main.php',
            'vfs://config/main2.php',
        ];
        $this->assertEquals($expected, $mains);
    }

    /**
     * Тест: загружаем главные файлы конфигураций, пошаговая загрузка.
     */
    public function test_loadConfiguration_multi_mains()
    {
        $provider = new PhpConfigProvider($this->configPath . 'main.php');

        $config = $provider->loadConfiguration();
        $this->assertEquals(['main#0' => 0], $config);
        $this->assertFalse($provider->isFullyLoaded());

        $config = $provider->loadConfiguration();
        $this->assertEquals(['main#2' => 2], $config);
        $this->assertTrue($provider->isFullyLoaded());
    }

    /**
     * Тест: загружаем главный файл конфигураций, всего один файл.
     *
     * Тут достаем файл из подкаталога, но это не значит, что так будет в реальном приложении. Это имитация простой
     * конфигурации, когда ее вообще не требуется грузить по шагам, она представлена всего одним основным файлом.
     */
    public function test_loadConfiguration_single_main()
    {
        $provider = new PhpConfigProvider($this->configPath . 'subfolder/main.php');
        $config = $provider->loadConfiguration();
        $this->assertEquals(['main' => 'single'], $config);
        $this->assertTrue($provider->isFullyLoaded());
    }

    /**
     * Тест: попытка загрузить конфиг из несуществующего файла должна приводить к исключению.
     */
    public function test_loadConfiguration_absent_main()
    {
        $provider = new PhpConfigProvider($this->configPath . 'absent.php');
        $this->expectException(ConfigException::class);
        $provider->loadConfiguration();
    }
}
