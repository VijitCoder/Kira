<?php
namespace kira\web;

use kira\utils\FS;

/**
 * Супер-класс виджетов, поддерживаемых движком
 *
 * Класс объявлен абстрактным, чтобы его нельзя было использовать без наследования. Он не содержит абстрактных методов.
 */
abstract class Widget
{
    /**
     * Имя файла шаблона, без расширения
     */
    protected $view = '';

    /**
     * Расширение файла шаблона
     * @var string
     */
    protected $viewExt = '.htm';

    /**
     * Параметры, переданные в виджет
     * @var array
     */
    protected $params;

    /**
     * Инициализация виджета с параметрами, переданными при его вызове
     * @param array $params параметры инициализации виджета
     */
    public function __construct(array $params = [])
    {
        $this->params = $params;
    }

    /**
     * Шаблон для отрисовки. Абсолютный путь + файл + расширение
     *
     * Данный метод описывает наиболее простую ситуацию нахождения шаблона: файл лежит в каталоге вместе с классом
     * виджета. Так же метод успешно находит шаблон в подкаталоге.
     *
     * @return string
     */
    public function getView(): string
    {
        return FS::getMyDirectory($this) . $this->view . $this->viewExt;
    }

    /**
     * Данные для использования в шаблоне
     * @return array
     */
    public function getData(): array
    {
        return [];
    }
}
