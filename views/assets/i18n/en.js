/**
 * Перевод рус->англ
 */
String.prototype.t = function() {
    var lexicon = {
        'Hедопустимые символы': 'Unacceptable symbols',
        'Пароль слишком короткий': 'Password is too short',
        'Пароль слишком простой ': 'Password is too easy ',
     //   'неверный формат почтового адреса': 'incorrect format of email address',
     //   'Неверный формат даты': 'Wrong date format',
    };

  return (lexicon[this] == undefined) ? String(this) : lexicon[this];
}

//календарь (Datepicker)
var locNames = {}
