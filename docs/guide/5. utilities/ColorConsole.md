# Утилиты. ColorConsole

Класс `\kira\utils\ColorConsole`

Работа с консолью через управляющие последовательности: цвет, стиль текста и т.п. Так же можно рисовать в консоли, перемещая курсор и используя псевдографику.

Для кодов псевдографики рекомендую заглянуть [сюда](https://unicode-table.com/ru/blocks/box-drawing/)

Пример использования:

```php
(new ColorConsole)
    ->setBgColor('white')

    ->setColor('green')
    ->addText('test')

    ->setColor('red')
    ->setStyle('italic')
    ->addText(' some')

    ->reset()
    ->draw(PHP_EOL)
    ->flush();
```

Получится два слова на белом фоне, первое - зеленым текстом, второе - красным курсивом.

**Важный момент**: все сеттеры на самом деле добавляют управляющие последовательности в текст. Поэтому если вы сбросили текст, например функцией `ColorConsole::setText()`, то заданные ранее цвета и стили будут стерты.

Ссылки по теме:

[Управление курсором, цветом и звуком консоли Linux](https://www.opennet.ru/base/dev/console_ctl.txt.html)
[man 4 console_codes](https://www.opennet.ru/man.shtml?topic=console_codes&category=4&russian=0)
[Box drawing in PHP](http://jonathonhill.net/2012-11-26/box-drawing-in-php/)
