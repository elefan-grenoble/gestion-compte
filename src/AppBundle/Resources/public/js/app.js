$(document).ready(function() {
    $('select').material_select();
    $('.button-collapse').sideNav({
        menuWidth: 300, // Default is 300
        edge: 'left', // Choose the horizontal origin
        closeOnClick: true, // Closes side-nav on <a> clicks, useful for Angular/Meteor
        draggable: true, // Choose whether you can drag to open on touch screens,
        onOpen: function(el) { /* Do Stuff */ }, // A function to be called when sideNav is opened
        onClose: function(el) { /* Do Stuff */ }, // A function to be called when sideNav is closed
    });
    $('.modal').modal({
        ready: function (modal) {
            $(modal).find('.simplemde-container').trigger('modalOpen'); //tell markdown editor to refresh
            },
    });
    $('.tooltipped').tooltip();
    $(".dropdown-button").dropdown();
});