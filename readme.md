# Kira Web Engine
*Since 20/09/2015*

![PHP 7.0] (https://img.shields.io/badge/PHP%207-%3E%3D7.0-blue.svg)

Мое представление об удобном web-движке.

Движок очень простой, не претендует на звание фреймворка. Функционал дописываю по мере необходимости. Ожидается стабильная работа движка только в наиболее популярном "окружении": MySQL, UTF-8, Apache. Другие условия могут привести к непредвиденным результатам. Например, иная СУБД может потребовать ручной настройки логера или даже переопределения части его функционала, а другая кодировка вообще может порождать неуловимые баги. Короче, мне лень писать двиг на все вероятности, для этого есть крутые фреймворки :)

Придерживаюсь парадигмы MVC + сервисы. Тонкие контроллеры, бизнес-логика в сервисах, запросы в базу и валидаторы форм - в моделях. Шаблонизатора нет, есть поддержка представлений типа "макет" и "вставки". Но для простоты *представления* называю *шаблонами*.

Реализована поддержка локализации (i18n). Можно перевести вообще всё: flash-сообщения пользователю, js-сообщения, даже отдельные страницы сайта (для этого придется дублировать шаблоны).

Несколько идей взяты из Yii 1.x Не считаю это плагиатом, поскольку реализации мои, а если идеи действительно стоящие, так почему бы их не использовать? :) Там, где это требуется, сохранены авторские реквизиты и отсылки к первоисточнику.

Стиль оформления кода близок к стандарту [PRS-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md). Перенос строк в районе 120 символов.

Адрес проекта на [Github] (https://github.com/VijitCoder/Kira)

*P.S.: название движку взял по имени кошки, которую очень любил. Это не значит, что я так же тащусь от этого проекта :), просто на ум пришло, когда нужно было назвать. Кстати, коша была строптивая, независимая и злая. Посмотрим, что покажет двиг имени нее.*

#### С чего вам следует начать?

Полная документация в подкаталоге [docs/]. Так же смотрите комментарии в коде.

Есть два варианта: создавать приложение вручную или через [мастер приложения](https://github.com/VijitCoder/kira_app_master). Это отдельный репозиторий, все нужные шаги там описаны.

##### Ручное создание приложения

Движок поддерживает *Composer*. Выполните

```sh
composer require vijitcoder/kira
composer install
```

Удачи.