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
        $this->assertTrue(FS::hasDots('./path/'), 'Есть точки на текущий каталог');
        $this->assertTrue(FS::hasDots('../../path/'), 'Есть точки, переход на два каталога выше');
        $this->assertTrue(FS::hasDots('public/../protected/lib/'), 'Есть точки перехода внутри пути');
        $this->assertFalse(FS::hasDots('public/.hidden/path/'), 'Точка есть, но не управляющая. Норм');
    }

    public function test_normalizePath()
    {
        $this->assertEquals('c:/', FS::normalizePath('c:\\'), 'Нормализация. Корень диска [C:]');
        $this->assertEquals('c:/www/', FS::normalizePath('c:\\www\\'),
            'Нормализация. Обычный Windows путь со слешем в конце');
        $this->assertEquals('c:/temp/', FS::normalizePath('c:\\temp'), 'Нормализация. Windows-путь без слеша в конце');
        $this->assertEquals('etc/', FS::normalizePath('/etc/'), 'Нормализация. Linux-путь со слешем в конце');
        $this->assertEquals('var/tmp/', FS::normalizePath('/var/tmp'), 'Нормализация. Linux-путь без слеша в конце');
        $this->assertEquals('home/user/../tmp/', FS::normalizePath('/home/user/../tmp'),
            'Нормализация. Не заменился переход на каталог выше');
        $this->assertEquals('c:/file.ext', FS::normalizePath('c:\file.ext', true),
            'Нормализация. Путь заканчивается на имя файла');

    }

    /**
     * Тест. Проверка: переданный каталог похож на абсолютный в стиле Windows
     */
    public function test_isWindowsRootedPath()
    {
        $this->assertTrue(FS::isWindowsRootedPath('c:\\'), 'Корень диска [C:]');
        $this->assertTrue(FS::isWindowsRootedPath('wfs:/temp/'), 'Виртуальная ФС, каталог от корня');
        $this->assertFalse(FS::isWindowsRootedPath('/home/user'), 'Linux, каталог от корня');
    }

    public function test_makeDir()
    {
        $newPath = 'some/new';

        $this->assertFalse($this->root->hasChild('some'), 'Еще нет целевого каталога и его родителя');

        $result = FS::makeDir($this->rootPath . $newPath, 0755);

        $this->assertNull($result, 'Успешное создание каталогов, метод не пробросил исключений.');
        $this->assertTrue($this->root->hasChild($newPath), 'Созданный каталог реально существует');
        $this->assertEquals(0755, $this->root->getChild('some')->getPermissions(),
            'Назначены верные права на промежуточный каталог');
        $this->assertEquals(0755, $this->root->getChild($newPath)->getPermissions(),
            'Назначены верные права на конечный каталог');
    }

    public function test_removeDir()
    {
        $this->assertTrue($this->root->hasChild('level1/level2/deepInside.php'),
            'Структура каталогов и файлов создана');

        FS::removeDir(vfsStream::url('root_path') . '/level1', 3);
        $this->assertFalse($this->root->hasChild('delIt'), 'Все каталоги и файлы удалены');
    }

    /**
     * Проверяем работу предохранителя в рекурсивном удалении каталогов
     *
     * Явно нарушаем условия успешного выполнения метода, ожидаем исключение. При этом удаление не должно быть
     * выполнено.
     */
    public function test_fuse_removeDir()
    {
        $this->expectException(FSException::class);

        $dirLevel4 = vfsStream::newDirectory('level4')
            ->at($this->root->getChild('level1/level2/level3'));

        vfsStream::newFile('level4.txt')
            ->withContent('level 4 from [delIt/], but 5 from the root path')
            ->at($dirLevel4);

        $deepestChild = 'level1/level2/level3/level4/level4.txt';

        $this->assertTrue($this->root->hasChild($deepestChild),
            'Проверка предохранителя. Структура каталогов и файлов создана');

        FS::removeDir($this->rootPath . '/level1', 3);
        $this->assertTrue($this->root->hasChild($deepestChild),
            'Проверка предохранителя. Реальная вложенность больше заданной. Удаление не выполнено');

        FS::removeDir($this->rootPath, 5); // от корня вложенность - 5, превышает допустимый максимум
        $this->assertTrue($this->root->hasChild($deepestChild),
            'Проверка предохранителя. Требуемая вложенность больше максимально допустимой. Удаление не выполнено');
    }

    public function test_dirList()
    {
        $targetDir = 'level1/level2/level3';

        $phpFiles = ['test.php', 'other.php'];
        $fileNames = FS::dirList($this->rootPath . $targetDir, '~\.php$~');
        $this->assertEquals($phpFiles, $fileNames, 'Список php-файлов в указанном каталоге');

        $allFiles = array_merge($phpFiles, ['log.csv', 'justFile.txt',]);
        $fileNames = FS::dirList($this->rootPath . $targetDir);
        $this->assertEquals($allFiles, $fileNames, 'Список всех файлов в указанном каталоге');
    }

    public function test_clearDir()
    {
        $targetDir = 'level1/level2/level3';
        $target = $this->root->getChild($targetDir);

        $this->assertTrue($target->hasChild('test.php'), 'Целевой файл перед очисткой каталога существует');

        FS::clearDir($this->rootPath . $targetDir, '~\.php$~');

        $this->assertFalse($target->hasChild('test.php'), 'Очистка каталога с фильтром выполнена');
        $this->assertEquals(2, count($target->getChildren()), 'Очистка каталога с фильтром не затронула другие файлы');

        FS::clearDir($this->rootPath . $targetDir);
        $this->assertFalse($target->hasChildren(), 'Полная очистка каталога выполнена');
    }

    /**
     * Проверка копирования файла
     *
     * Не провожу проверку содержимого, только размеры файлов. Этого достаточно.
     */
    public function test_copyFile()
    {
        $this->assertTrue($this->root->hasChild('some.ext'), 'Целевой файл перед копированием существует');

        FS::copyFile($this->rootPath . 'some.ext', $this->rootPath . 'double.ext');

        $this->assertTrue($this->root->hasChild('some.ext'), 'Целевой файл после копирования существует');
        $this->assertTrue($this->root->hasChild('double.ext'), 'Копированием создан новый файл');
        $this->assertEquals(
            $this->root->getChild('some.ext')->size(),
            $this->root->getChild('double.ext')->size(),
            'Размеры файлов после копирования совпадают'
        );
    }

    /**
     * Проверка переименовки файла
     *
     * Не провожу проверку содержимого, только размеры файлов. Этого достаточно.
     */
    public function test_renameFile()
    {
        $this->assertTrue($this->root->hasChild('some.ext'), 'Целевой файл перед переименовкой существует');

        $size = $this->root->getChild('some.ext')->size();
        FS::renameFile($this->rootPath . 'some.ext', $this->rootPath . 'new_some.ext');

        $this->assertFalse($this->root->hasChild('some.ext'), 'Целевой файл после переименовки исчез');
        $this->assertTrue($this->root->hasChild('new_some.ext'), 'Файл с новым именем появился');
        $this->assertEquals(
            $size,
            $this->root->getChild('new_some.ext')->size(),
            'Размеры файла после переименовки не изменился'
        );
    }

    /**
     * Проверка перемещения файла
     *
     * Не провожу проверку содержимого, только размеры файлов. Этого достаточно.
     */
    public function test_moveFile()
    {
        $this->assertTrue($this->root->hasChild('some.ext'), 'Целевой файл перед перемещением существует');
        $orgSize = $this->root->getChild('some.ext')->size();

        FS::moveFile($this->rootPath . 'some.ext', $this->rootPath . 'level1/newSome.ext');

        $this->assertFalse($this->root->hasChild('some.ext'), 'Целевой файл после перемещения исчез');
        $this->assertTrue($this->root->hasChild('level1/newSome.ext'), 'Перемещением создан новый файл');
        $this->assertEquals($orgSize, $this->root->getChild('level1/newSome.ext')->size(),
            'Размеры файла после перемещения не изменился');
    }

    public function test_deleteFile()
    {
        $this->assertTrue($this->root->hasChild('some.ext'), 'Целевой файл перед удалением существует');
        FS::deleteFile($this->rootPath . 'some.ext');
        $this->assertFalse($this->root->hasChild('some.ext'), 'Целевой файл после удаления исчез');
    }
}
