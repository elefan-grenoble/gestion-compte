// Recherche insensible aux accents dans les champs autocomplete de Materialize
// (ex : "helene" trouve "Hélène", et inversement).
// Materialize ne compare qu'en minuscules : on surcharge le filtrage et le surlignage.

// Replie chaque caractère séparément (minuscule, sans accent) pour que le texte
// replié garde la même longueur que l'original : les positions trouvées dans
// le texte replié sont donc valables dans le texte d'origine (surlignage).
function foldAccents(str) {
    return str.split('').map(function (c) {
        var folded = c.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        return folded.length === 1 ? folded : c.toLowerCase();
    }).join('');
}

// Reprend M.Autocomplete._renderDropdown (materialize-css 1.0) avec une comparaison sans accents
M.Autocomplete.prototype._renderDropdown = function (data, val) {
    this._resetAutocomplete();

    var foldedVal = foldAccents(val);
    var matchingData = [];

    for (var key in data) {
        if (data.hasOwnProperty(key) && foldAccents(key).indexOf(foldedVal) !== -1) {
            if (this.count >= this.options.limit) {
                break;
            }
            matchingData.push({ data: data[key], key: key });
            this.count++;
        }
    }

    if (this.options.sortFunction) {
        var sortFunction = this.options.sortFunction;
        matchingData.sort(function (a, b) {
            return sortFunction(foldAccents(a.key), foldAccents(b.key), foldedVal);
        });
    }

    for (var i = 0; i < matchingData.length; i++) {
        var entry = matchingData[i];
        var $autocompleteOption = $('<li></li>');
        if (!!entry.data) {
            $autocompleteOption.append(
                '<img src="' + entry.data + '" class="right circle"><span>' + entry.key + '</span>'
            );
        } else {
            $autocompleteOption.append('<span>' + entry.key + '</span>');
        }

        $(this.container).append($autocompleteOption);
        this._highlight(val, $autocompleteOption);
    }
};

// Reprend M.Autocomplete._highlight (materialize-css 1.0) avec une comparaison sans accents
M.Autocomplete.prototype._highlight = function (string, $el) {
    var img = $el.find('img');
    var text = $el.text();
    var matchStart = foldAccents(text).indexOf(foldAccents(string));
    if (matchStart === -1) {
        return;
    }
    var matchEnd = matchStart + string.length;
    $el.html(
        '<span>' + text.slice(0, matchStart) +
        "<span class='highlight'>" + text.slice(matchStart, matchEnd) + '</span>' +
        text.slice(matchEnd) + '</span>'
    );
    if (img.length) {
        $el.prepend(img);
    }
};
