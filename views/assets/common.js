$(function () {
    /**
     * Переключалка языка интерфейса
     */
    $('#cmbLang').change(function() {
        var lang = this.value;
        if (lang == 'ru') {
            $.removeCookie('lang', { path: '/users' });
        } else {
            $.cookie('lang', this.value, {expires: 365, path: '/users'});
        }
        location.reload();
    });

    /**
     * Валидируем логин
     */
    $('#fmReg input[name="login"]').focusout(function(ev) {
        var err = $(this).siblings('span.error');

        if (/^[a-z0-9_-]+$/.test(this.value)) {
            $(err).html('');
        } else {
            $(err).html('Hедопустимые символы'.t());
            ev.stopImmediatePropagation();
        }
    });

   /**
     * Проверяем на доступность логин или мыло
     */
    $('#fmReg input[name="login"], #fmReg input[name="mail"]').focusout(function() {
        if (!this.value) return;
        var err = $(this).siblings('span.error');
        var gif = $(this).siblings('img').css('visibility', 'visible');
        $.get('/users/registration/check', {p:this.value},
            function(data, status) {
                if (status == 'success' && data) {
                    $(err).html(data);
                } else {
                    $(err).html('');
                }
            }
        )
        .always(function() { $(gif).css('visibility', 'hidden'); });
    });

    /**
     * Валидируем пароль
     * Есть косячок: \w == [a-z9-0-_]. В то время, как на PHP этот спец.символ принимает буквы
     * любых языков (с модификатором "u"). Вообщем допускаем, что пароль только на русском/английском может быть.
     */
    $('#fmReg input[name="password"]').focusout(function() {
        var err = [];
        var pass = this.value;

        if (!/^[\wа-яё!@#$%^&`\~]+$/i.test(pass)) {
          err.push('Hедопустимые символы'.t());
        }

        if (pass.length < $('#js-pwd').data('pass')) {
          err.push('Пароль слишком короткий'.t());
        }

        var cnt = 0;
        var tmp1 = pass.replace(/[^\wа-яё!@#$%^&`\~]+/i, ''); //убираем левое
        var tmp2 = tmp1.replace(/[!@#$%^&`\~_-]+/, '');       //убрали спец.символы
        cnt += tmp2 != tmp1 ? 1 : 0;                          //строка изменилась? Значит набор был
        tmp1 = tmp2.replace(/\d+/, '');                       //из оставшейся(!) строки убрали цифры
        cnt += tmp2 != tmp1 ? 1 : 0;                          //опять изменилась? Значит цифровой набор был
        cnt += tmp1 != tmp1.toUpperCase() && tmp1 != tmp1.toLowerCase() ? 1 : 0; //теперь в строке только буквы. Проверяем camelCase.
        if (tmp1) cnt++;

        var minComb = $('#js-pwd').data('comb');
        if (cnt < minComb) {
            err.push('Пароль слишком простой '.t() + String(cnt) + '/' + String(minComb));
        }

        $(this).siblings('span.error').html((err.length) ? err.join('. ') : '');
    });

    /**
     * Валидируем Имя Фамилию
     */
    $('#fmReg input[name="firstname"], #fmReg input[name="secondname"]').focusout(function() {
         $(this).siblings('span.error').html(/^[\sa-zа-яё-]*$/i.test(this.value) ? '' : 'Hедопустимые символы'.t());
    });

    //@link https://jqueryui.com/datepicker/
    var mdate = new Date();
    $('#fmReg input[name="birth_date"]').datepicker(
        $.extend(
            locNames, {
                changeMonth: true,
                changeYear: true,
                dateFormat: 'dd.mm.yy',
                yearRange: '1920:' + String(mdate.getFullYear()),
                firstDay: 1,
                nextText: '>>',
                prevText: '<<',
             }
        )
    );
});
