

let defaults = {
    months: [ 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre' ],
    monthsShort: [ 'Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aou', 'Sep', 'Oct', 'Nov', 'Dec' ],
    weekdays: [ 'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi' ],
    weekdaysShort: [ 'Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam' ],
    today: 'Aujourd\'hui',
    cancel: 'Annuler',
    clear: 'Effacer',
    close: 'Fermer',
    done: 'Ok',
    firstDay: 1,
    format: 'yyyy-mm-dd',
    formatSubmit: 'yyyy-mm-dd',
    labelMonthNext:"Mois suivant",
    labelMonthPrev:"Mois précédent",
    labelMonthSelect:"Sélectionner un mois",
    labelYearSelect:"Sélectionner une année"
};

$(document).ready(function() {
    // Date only datepicker
    $('input.datepicker').datepicker({
        selectMonths: true, // Creates a dropdown to control month
        selectYears: 2, // Creates a dropdown of 15 years to control year,
        autoClose: true, // Close upon selecting a date,
        i18n: defaults
    });
    $('input.timepicker').timepicker({
        default: 'now', // Set default time: 'now', '1:30AM', '16:30'
        fromNow: 0,       // set default time to * milliseconds from now (using with default = 'now')
        twelveHour: false, // Use AM/PM or 24-hour format
        autoClose: true, // automatic close timepicker
    });

    // Splitted DateTime datepicker
    $('div.datepicker > input[type=date]').datepicker({
        selectMonths: true, // Creates a dropdown to control month
        selectYears: 2, // Creates a dropdown of 15 years to control year,
        autoClose: true, // Close upon selecting a date,
        i18n: defaults
    });
    $('div.datepicker > input[type=time]').timepicker({
        twelveHour: false, // Use AM/PM or 24-hour format
        autoClose: true, // automatic close timepicker
    });
});

// Workaround an incompatibility between Materialize's datepicker and Chromium > 73
$('.datepicker').on('mousedown', function(event){
    event.preventDefault();
});
$('.timepicker').on('mousedown', function(event){
    event.preventDefault();
});
