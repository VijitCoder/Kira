/**
 * Мастер создания нового приложения. Скрипт сопровождения для формы мастера.
 */
$(document).ready(function () {
    // Старое значение app_path. По нему будет проведена замена в остальных путях.
    var oldApp = '';

    $('#app-path')
        .on('focus', function () {
            oldApp = $(this).val();
        })

        .on('change', function () {
            tailSlash(this);
            var app = $(this).val();

            var paths = {
                '#view-path': 'views/',
                '#temp-path': 'temp/',
                '#log-path': 'temp/',
                '#main-conf': 'conf/main.php'
            };

            for (let inputID in paths) {
                var tag = $(inputID);
                var text = $(tag).val();
                if (oldApp && text) {
                    var pattern = new RegExp('^' + oldApp.replace(/\//, '\\/'));

                    if (!app && pattern.test(text)) {
                        $(tag).val('');
                        continue;
                    }

                    text = text.replace(pattern, app);
                } else if (!text) {
                    text = app + paths[inputID];
                }
                $(tag).val(text);
            }
        });

    /**
     * TODO как получить event.target и сразу указать обработчиком внешнюю функцию, а не использовать обертку из
     * анонимной функции?
     */
    $(document).on('change', '#view-path, #temp-path, #log-path, #js-path', function () {
        tailSlash(this);
    });

    /**
     * Гарантируем наличие завершающего слеша.
     *
     * @param Object inputObj - jQuery input object
     * @return void
     */
    function tailSlash(inputObj) {
        var v = $(inputObj).val();
        if (v && v.slice(-1) != '/') {
            $(inputObj).val(v + '/');
        }
    }

    /**
     * Собираем DSN-строку подключения к базе.
     */
    $(document).on('change', '#db-server, #db-name, #db-charset', function () {
        var server = $("#db-server").val();
        var db = $("#db-name").val();
        var dsn = '';

        if (server || db) {
            var charset = $("#db-charset").val();
            server = server.split(':');
            dsn = "mysql:host=" + server[0] + '; ';
            if (server.length == 2) {
                dsn += "port=" + server[1] + '; ';
            }
            dsn += "dbname=" + db + "; charset=" + charset;
        } else {
            dsn = 'none';
        }

        $('#dsn').text(dsn);

        $('#log-store').trigger('change');
    });

    /**
     * Логер. При выборе записи логи в базу выдаем предупреждение, если нет конфигурации БД. Иначе - поле для ввода
     * таблицы.
     */
    $(document).on('change', '#log-store', function () {
        var logWarn = $("#log-db-warn"),
            logTable = $("#log-db-table");

        $(logWarn).hide();
        $(logTable).hide();

        if ($(this).val() == 'db') {
            if ($('#dsn').text() == 'none') {
                $(logWarn).show();
            } else {
                $(logTable).show();
            }
        }
    });

    /**
     * Переключатели disable-панелей на блоках БД, лога и языков. Поднят флаг - убрать панельку, закрывающую поля блока.
     */
    $(document).on('click', '#db-switch, #log-switch, #lang-switch', function () {
        var id = '#' + $(this).attr('id').replace('switch', 'disabler');
        if ($(this).prop("checked")) {
            $(id).hide();
        } else {
            $(id).show();
        }
    });

    // Если страницу вернули юзеру с ошибками валидации, нужно пересобрать DSN-строку, т.к. вероятно есть конфиг БД.
    $('#db-server').trigger('change');
});
