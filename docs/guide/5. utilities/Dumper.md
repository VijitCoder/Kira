# Утилиты. Dumper

Класс `\kira\utils\Dumper`

Дамп переменной. Взял в Yii 1.x и слегка переделал под себя. Все публичные методы этой утилиты работают аналогично php::var_dump() и php::print_r(), но возвращают информацию в более приглядном виде.

В движке есть "shortcut"-функция для отладки - `dd()`. Обращается именно в этот класс, но доступна в любом месте сайта, без подключения класса, namespaces и т.д.
