/**
 * Перевод рус->англ
 */
String.prototype.t = function() {
    var lexicon = {
        'Не учили не щекать куда-попало?': 'Not taught not cheek somewhere horrible?',
    };

  return (lexicon[this] == undefined) ? String(this) : lexicon[this];
}
