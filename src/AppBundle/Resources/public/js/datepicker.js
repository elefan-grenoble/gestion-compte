
jQuery.extend( jQuery.fn.pickadate.defaults, {
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
    labelMonthNext:"Mois suivant",
    labelMonthPrev:"Mois précédent",
    labelMonthSelect:"Sélectionner un mois",
    labelYearSelect:"Sélectionner une année"
});

jQuery(function(){
    // Date only datepicker
    $('input.datepicker').pickadate({
        selectMonths: true, // Creates a dropdown to control month
        selectYears: 2, // Creates a dropdown of 15 years to control year,
        close: 'Ok',
        closeOnSelect: true // Close upon selecting a date,
    });
    $('input.timepicker').pickatime({
        default: 'now', // Set default time: 'now', '1:30AM', '16:30'
        fromnow: 0,       // set default time to * milliseconds from now (using with default = 'now')
        twelvehour: false, // Use AM/PM or 24-hour format
        donetext: 'OK', // text for done-button
        cleartext: 'Effacer', // text for clear-button
        canceltext: 'Annuler', // Text for cancel-button
        autoclose: true, // automatic close timepicker
        ampmclickable: true, // make AM PM clickable
        aftershow: function(){} //Function for after opening timepicker
    });

    // Splitted DateTime datepicker
    $('div.datepicker > input[type=date]').pickadate({
        selectMonths: true, // Creates a dropdown to control month
        selectYears: 2, // Creates a dropdown of 15 years to control year,
        close: 'Ok',
        closeOnSelect: true // Close upon selecting a date,
    });
    $('div.datepicker > input[type=time]').pickatime({
        twelvehour: false, // Use AM/PM or 24-hour format
        donetext: 'OK', // text for done-button
        cleartext: 'Effacer', // text for clear-button
        canceltext: 'Fermer', // Text for cancel-button
        autoclose: true, // automatic close timepicker
        ampmclickable: false, // make AM PM clickable
    });
});

// Workaround an incompatibility between Materialize's datepicker and Chromium > 73
$('.datepicker').on('mousedown', function(event){
    event.preventDefault();
});
$('.timepicker').on('mousedown', function(event){
    event.preventDefault();
});
