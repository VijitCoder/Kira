/**
 * Перевод рус->другой язык
 */
String.prototype.t = function() {
    var lexicon = {
        '': '', // сюда пишем пары фраз, русский : перевод
    };

  return (lexicon[this] == undefined) ? String(this) : lexicon[this];
}
