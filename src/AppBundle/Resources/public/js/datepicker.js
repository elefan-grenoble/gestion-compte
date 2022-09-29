
jQuery.extend(jQuery.fn.datepicker.defaults, {
    monthsFull: [ 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre' ],
    monthsShort: [ 'Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aou', 'Sep', 'Oct', 'Nov', 'Dec' ],
    weekdaysFull: [ 'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi' ],
    weekdaysShort: [ 'Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam' ],
    today: 'Aujourd\'hui',
    clear: 'Effacer',
    close: 'Fermer',
    firstDay: 1,
    format: 'yyyy-mm-dd',
    formatSubmit: 'yyyy-mm-dd',
    labelMonthNext: 'Mois suivant',
    labelMonthPrev: 'Mois précédent',
    labelMonthSelect: 'Sélectionner un mois',
    labelYearSelect: 'Sélectionner une année'
});

// https://materializecss.com/pickers.html
datepickerSettings = {
    format: 'yyyy-mm-dd',
    showClearBtn: true,
    i18n: {
        done: 'OK', // text for done-button
        clear: 'Effacer', // text for clear-button
        cancel: 'Annuler', // Text for cancel-button
    },
    autoClose: true // Close upon selecting a date
}
timepickerSettings = {
    defaultTime: 'now', // Set default time: 'now', '1:30AM', '16:30'
    twelveHour: false, // Use AM/PM or 24-hour format
    showClearBtn: true,
    i18n: {
        done: 'OK', // text for done-button
        clear: 'Effacer', // text for clear-button
        cancel: 'Annuler', // Text for cancel-button
    },
    autoClose: true, // Close upon selecting a time
}

jQuery(function() {
    // Date only datepicker
    $('input.datepicker').datepicker(datepickerSettings);
    $('input.timepicker').timepicker(timepickerSettings);
    // Splitted DateTime datepicker
    $('div.datepicker > input[type=date]').datepicker(datepickerSettings);
    $('div.datepicker > input[type=time]').timepicker(timepickerSettings);
});
