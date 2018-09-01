<?php
use PHPUnit\Framework\TestCase;
use kira\utils\FS;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use kira\exceptions\FSException;

/**
 * Тестируем утилиту по работе с файловой системой
 *
 * Прим: библиотека vfsStream вообще не любит слеши. Например, создание каталога с концевым слешем или без него - это
 * два разных набора в vfsStream::getChildren(). Поэтому не меняй текущее использование слешей. Сейчас оно описано
 * правильно.
 */
class FSTest extends TestCase
{
    /**
     * Объект, представляющий иерархию каталогов и файлов в виртуальной файловой системе
     * @var vfsStreamDirectory
     */
    private $root;

    /**
     * Путь к корню созданной файловой структуры
     * @var string
     */
    private $rootPath;

    public function setUp()
    {
        $structure = [
            'level1'   => [
                'level2'    => [
                    // Не меняй расширения и количество файлов в каталоге [level3/]. На нем построен test_clearDir()
                    'level3'         => [
                        'test.php'     => 'some text content',
                        'other.php'    => 'Some more text content',
                        'log.csv'      => 'Something else',
                        'justFile.txt' => 'just a text',
                    ],
                    'AnEmptyFolder'  => [],
                    'deepInside.php' => 'some content',
                ],
                'lvl1.file' => 'nop',
            ],
            'some.ext' => 'will be renamed, moved and deleted by tests',
        ];

        $this->root = vfsStream::setup('home', null, $structure);
        $this->rootPath = vfsStream::url('home') . '/';
    }

    public function test_hasDots()
    {
        $this->assertTrue(FS::hasDots('./path/'), 'Не обнаружена точка на текущий каталог');
        $this->assertTrue(FS::hasDots('../../path/'), 'Не обнаружены точки, как переход на два каталога выше');
        $this->assertTrue(FS::hasDots('public/../protected/lib/'), 'Не обнаружены точки перехода внутри пути');
        $this->assertFalse(FS::hasDots('public/.hidden/path/'), 'Обычная точка расценена как управляющая');
    }

    public function test_normalizePath()
    {
        $this->assertEquals('c:/', FS::normalizePath('c:\\'), 'Корень диска [C:], неверная нормализация');
        $this->assertEquals('c:/www/', FS::normalizePath('c:\\www\\'),
            'Обычный Windows путь со слешем в конце, неверная нормализация');
        $this->assertEquals('c:/temp/', FS::normalizePath('c:\\temp'),
            'Windows-путь без слеша в конце, неверная нормализация');
        $this->assertEquals('etc/', FS::normalizePath('/etc/'), 'Linux-путь со слешем в конце, неверная нормализация');
        $this->assertEquals('var/tmp/', FS::normalizePath('/var/tmp'),
            'Linux-путь без слеша в конце, неверная нормализация');
        $this->assertEquals('home/user/../tmp/', FS::normalizePath('/home/user/../tmp'),
            'Неверная нормализация, заменился переход на каталог выше');
        $this->assertEquals('c:/file.ext', FS::normalizePath('c:\file.ext', true),
            'Неверная нормализация для пути заканчивающегося на имя файла');

    }

    /**
     * Тест. Проверка: переданный каталог похож на абсолютный в стиле Windows
     */
    public function test_isWindowsRootedPath()
    {
        $this->assertTrue(FS::isWindowsRootedPath('c:\\'), 'Корень диска [C:]. Путь неопределен в стиле Windows');
        $this->assertTrue(FS::isWindowsRootedPath('wfs:/temp/'),
            'Виртуальная ФС, каталог от корня, путь неопределен в стиле Windows');
        $this->assertFalse(FS::isWindowsRootedPath('/home/user'),
            'Linux, каталог от корня, но путь определен в стиле Windows');
    }

    public function test_makeDir()
    {
        $newPath = 'some/new';

        $this->assertFalse($this->root->hasChild('some'), ' Слишком рано появился целевой каталог и его родитель');

        $result = FS::makeDir($this->rootPath . $newPath, 0755);

        $this->assertNull($result, 'Не удалось создание каталогов');
        $this->assertTrue($this->root->hasChild($newPath), 'Созданный каталог реально не существует');
        $this->assertEquals(0755, $this->root->getChild('some')->getPermissions(),
            'Назначены неверные права на промежуточный каталог');
        $this->assertEquals(0755, $this->root->getChild($newPath)->getPermissions(),
            'Назначены неверные права на конечный каталог');
    }

    public function test_removeDir()
    {
        $this->assertTrue($this->root->hasChild('level1/level2/deepInside.php'),
            'Структура каталогов и файлов не создана');

        FS::removeDir($this->rootPath . 'level1', 3);
        $this->assertFalse($this->root->hasChild('delIt'), 'Удалены не все каталоги и файлы');
    }

    /**
     * Тест: проверяем работу предохранителя в рекурсивном удалении каталогов
     *
     * Явно нарушаем условия успешного выполнения метода, ожидаем исключение.
     *
     * @dataProvider fuseDataProvider
     *
     * @param string $path        относительный путь к удаляемому каталогу
     * @param int    $expectLevel ожидаемый уровень вложенности подкаталогов в удаляемой цели
     */
    public function test_fuse_removeDir(string $path, int $expectLevel)
    {
        $dirLevel4 = vfsStream::newDirectory('level4')
            ->at($this->root->getChild('level1/level2/level3'));

        vfsStream::newFile('level4.txt')
            ->withContent('4-й уровень от удаляемого каталога, при этом 5-й - от корня')
            ->at($dirLevel4);

        $deepestChild = 'level1/level2/level3/level4/level4.txt';

        $this->assertTrue($this->root->hasChild($deepestChild),
            'Подготовка к проверке предохранителя: не удалось создать нужную структуру каталогов и файлов');

        $this->expectException(FSException::class);
        FS::removeDir($this->rootPath . $path, $expectLevel);
    }

    /**
     * Данные: проверяем работу предохранителя в рекурсивном удалении каталогов
     * Есть разные ситуации, когда метод должен пробросить исключение вместо удаления каталогов.
     * @return array
     */
    public function fuseDataProvider()
    {
        return [
            // Реальная вложенность больше заданной
            [
                'dir'         => 'level1',
                'expectLevel' => 3,
            ],
            // Требуемая вложенность больше максимально допустимой
            [
                'dir'         => '',
                'expectLevel' => 5,
            ],
        ];
    }

    /**
     * Тест: Получение списка файлов в указанном каталоге.
     *
     * Прим: проверяемый метод применяет natsort() к результату. Такая сортировка сохраняет ключи, даже числовые. Но для
     * теста эти ключи не важны, поэтому сбрасываем их во всех сравниваемых массивах. Если не сбросить, тест не пройдет.
     */
    public function test_dirList()
    {
        $targetDir = 'level1/level2/level3';

        # Тест №1

        $phpFiles = ['other.php', 'test.php',];
        $fileNames = FS::dirList($this->rootPath . $targetDir, '~\.php$~');
        $fileNames  = array_values($fileNames);
        $this->assertEquals($phpFiles, $fileNames, 'Неверный список php-файлов в указанном каталоге');

        # Тест №2

        // Arrange
        $allFiles = array_merge($phpFiles, ['justFile.txt', 'log.csv',]);
        natsort($allFiles);
        $allFiles  = array_values($allFiles);

        // Act
        $fileNames = FS::dirList($this->rootPath . $targetDir);
        $fileNames  = array_values($fileNames);

        // Assert
        $this->assertEquals($allFiles, $fileNames, 'Неверный список всех файлов в указанном каталоге');
    }

    public function test_clearDir()
    {
        $targetDir = 'level1/level2/level3';
        $target = $this->root->getChild($targetDir);

        $this->assertTrue($target->hasChild('test.php'), 'Целевой файл перед очисткой каталога отсутствует');

        FS::clearDir($this->rootPath . $targetDir, '~\.php$~');

        $this->assertFalse($target->hasChild('test.php'), 'Очистка каталога с фильтром не выполнена');
        $this->assertEquals(2, count($target->getChildren()), 'Очистка каталога с фильтром затронула другие файлы');

        FS::clearDir($this->rootPath . $targetDir);
        $this->assertFalse($target->hasChildren(), 'Полная очистка каталога не выполнена');
    }

    /**
     * Проверка копирования файла
     *
     * Не провожу проверку содержимого, только размеры файлов. Этого достаточно.
     */
    public function test_copyFile()
    {
        $this->assertTrue($this->root->hasChild('some.ext'), 'Целевой файл перед копированием отсутствует');

        FS::copyFile($this->rootPath . 'some.ext', $this->rootPath . 'double.ext');

        $this->assertTrue($this->root->hasChild('some.ext'), 'Целевой файл после копирования отсутствует');
        $this->assertTrue($this->root->hasChild('double.ext'), 'Копированием не создан новый файл');
        $this->assertEquals(
            $this->root->getChild('some.ext')->size(),
            $this->root->getChild('double.ext')->size(),
            'Размеры файлов после копирования отличаются'
        );
    }

    /**
     * Проверка переименовки файла
     *
     * Не провожу проверку содержимого, только размеры файлов. Этого достаточно.
     */
    public function test_renameFile()
    {
        $this->assertTrue($this->root->hasChild('some.ext'), 'Целевой файл перед переименовкой отсутствует');

        $size = $this->root->getChild('some.ext')->size();
        FS::renameFile($this->rootPath . 'some.ext', $this->rootPath . 'new_some.ext');

        $this->assertFalse($this->root->hasChild('some.ext'), 'Целевой файл после переименовки остался');
        $this->assertTrue($this->root->hasChild('new_some.ext'), 'Файл с новым именем не появился');
        $this->assertEquals(
            $size,
            $this->root->getChild('new_some.ext')->size(),
            'Размеры файла после переименовки отличаются'
        );
    }

    /**
     * Проверка перемещения файла
     *
     * Не провожу проверку содержимого, только размеры файлов. Этого достаточно.
     */
    public function test_moveFile()
    {
        $this->assertTrue($this->root->hasChild('some.ext'), 'Целевой файл перед перемещением отсутствует');
        $orgSize = $this->root->getChild('some.ext')->size();

        FS::moveFile($this->rootPath . 'some.ext', $this->rootPath . 'level1/newSome.ext');

        $this->assertFalse($this->root->hasChild('some.ext'), 'Целевой файл после перемещения остался');
        $this->assertTrue($this->root->hasChild('level1/newSome.ext'), 'Перемещением не создан новый файл');
        $this->assertEquals($orgSize, $this->root->getChild('level1/newSome.ext')->size(),
            'Размеры файла после перемещения отличаются');
    }

    public function test_deleteFile()
    {
        $this->assertTrue($this->root->hasChild('some.ext'), 'Целевой файл перед удалением отсутствует');
        FS::deleteFile($this->rootPath . 'some.ext');
        $this->assertFalse($this->root->hasChild('some.ext'), 'Целевой файл после удаления остался');
    }

    /**
     * Тест: парсинг файлового пути.
     *
     * @dataProvider pathInfoData
     *
     * @param string $source        путь для анализа
     * @param array  $expectedParts ожидаемый результат
     */
    public function test_pathInfo(string $source, array $expectedParts)
    {
        $parts = FS::pathInfo($source)->toArray(true);
        $expectedParts['source'] = $source;
        $this->assertEquals($expectedParts, $parts);
    }

    /**
     * Данные: парсинг файлового пути.
     *
     * @return array
     */
    public function pathInfoData()
    {
        $dummyParts = [
            'path'      => null,
            'fullName'  => null,
            'shortName' => null,
            'extension' => null,
        ];

        return [
            'Linux. Абсолютный каталог' => [
                'source' => '/var/www/',
                'parts'  => array_merge($dummyParts, [
                    'path' => '/var/www/',
                ]),
            ],

            'Linux. Относительный каталог' => [
                'source' => 'www/',
                'parts'  => array_merge($dummyParts, [
                    'path' => 'www/',
                ]),
            ],

            'Linux. Абсолютный каталог + файл без расширения' => [
                'source' => '/var/www/file',
                'parts'  => array_merge($dummyParts, [
                    'path'      => '/var/www/',
                    'fullName'  => 'file',
                    'shortName' => 'file',
                ]),
            ],

            'Linux. Файл с ведущей точкой' => [
                'source' => '/var/www/.hidden_file',
                'parts'  => array_merge($dummyParts, [
                    'path'      => '/var/www/',
                    'fullName'  => '.hidden_file',
                    'shortName' => '.hidden_file',
                ]),
            ],

            'Linux. Файл с расширением' => [
                'source' => '/var/www/file.lib.co',
                'parts'  => array_merge($dummyParts, [
                    'path'      => '/var/www/',
                    'fullName'  => 'file.lib.co',
                    'shortName' => 'file.lib',
                    'extension' => 'co',
                ]),
            ],

            'Windows. Абсолютный каталог' => [
                'source' => 'd:\temp\\',
                'parts'  => array_merge($dummyParts, [
                    'path' => 'd:\temp\\',
                ]),
            ],

            'Windows. Относительный каталог' => [
                'source' => 'temp\\',
                'parts'  => array_merge($dummyParts, [
                    'path' => 'temp\\',
                ]),
            ],

            'Windows. Путь + файл' => [
                'path'  => 'd:\temp\some.doc.txt',
                'parts' => array_merge($dummyParts, [
                    'path'      => 'd:\temp\\',
                    'fullName'  => 'some.doc.txt',
                    'shortName' => 'some.doc',
                    'extension' => 'txt',
                ]),
            ],

            'Нет пути, только файл' => [
                'source' => 'some.doc.txt',
                'parts'  => array_merge($dummyParts, [
                    'fullName'  => 'some.doc.txt',
                    'shortName' => 'some.doc',
                    'extension' => 'txt',
                ]),
            ],

            'Кириллица' => [
                'path'  => 'd:\мой\каталог\заметки_new.txt',
                'parts' => array_merge($dummyParts, [
                    'path'      => 'd:\мой\каталог\\',
                    'fullName'  => 'заметки_new.txt',
                    'shortName' => 'заметки_new',
                    'extension' => 'txt',
                ]),
            ],

            'Вообще ничего нет' => [
                'source' => '',
                'parts'  => $dummyParts,
            ],
        ];
    }
}
