# Утилиты. Enum

Класс `\kira\utils\Enum`

Абстрактный класс. Наследники должны представлять собой классы, содержащие только константы. В классе `Enum` несколько полезных методов по работе с такими константами.

На практике выделение класса под константы оказывается удобно. Пример: есть статусы пользователя: `new`, `active`, `banned`, `deleted`. Обычно такие статусы в базе хранятся в поле `enum`. В коде желательно избегать строковых значений, следовательно нужны константы для каждого статуса. Но где их хранить? Первая мысль - в модели, т.к. есть соответствие данным базы. Но что, если нет четкого равенства между моделью пользователя и таблицами пользователя? К тому же, статусы только в базе хранятся в каком-то поле, в коде бэкенда действия с ними могут происходить в любом слое приложения.

Вот для этого заводим отдельный класс `UserStatus`, и в нем описываем все статусы пользователя. Причем класс не относится к какому-то слою приложения, он общедоступен из любого из них.

Кстати, иногда требуется так же передать все статусы на фронт, используйте `Enum::getConstants()`.
