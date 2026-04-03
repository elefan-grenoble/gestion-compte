
const SimpleMDE = require('simplemde/dist/simplemde.min.js');
global.SimpleMDE = SimpleMDE;

require('simplemde/dist/simplemde.min.css');

// require jQuery normally
const $ = require('jquery');
// create global $ and jQuery variables
global.$ = global.jQuery = $;

require('materialize-css/dist/js/materialize');
require('materialize-css/dist/css/materialize.css');
require('materialize-css/')


require('./barcode.js');
require('./datepicker.js');
global.Cookies = require('./js.cookie.js');

require("../less/card.less");
require("../less/custom.less");
require("../less/post-it.less");
require("../less/shift.less");

require("../less/update.less");


global.install_events = function () {
    // initialize Materialize behavior - https://materializeweb.com/
    $('select').formSelect();

    $('.modal').each( function (i, modal) {
        if (M.Modal.getInstance(modal) !== undefined)
            return;

        $(modal).modal({
            onOpenStart: function (modal) {
                $(modal).find('.simplemde-container').trigger('modalOpen'); // tell markdown editor to refresh
            },
        });
    });

    $('.collapsible').collapsible();
    $('.collapsible.collapsible-expandable').collapsible({
        accordion: false,
        onOpenStart: function (li) {
            $(li).trigger("opened");
        }
    });
    $('.tooltipped').tooltip();
    $(".dropdown-trigger").dropdown();
    $(".materialboxed").materialbox();
}


$(document).ready(function() {
    $('.sidenav').sidenav({
        menuWidth: 300, // Default is 300
        edge: 'left', // Choose the horizontal origin
        closeOnClick: true, // Closes side-nav on <a> clicks, useful for Angular/Meteor
        draggable: true, // Choose whether you can drag to open on touch screens
    });

    install_events();

    // Ouvrir le premier jour du planning ; une fois chargé, on ouvrira le second etc.
    if (typeof load_next !== "undefined")
        load_next();
});

global.myCookieInit = function(defaultData){
    var data_raw = Cookies.get("frontend");
    var data = undefined;
    if (data_raw)
        data = JSON.parse(data_raw);
    if (!data)
        data = {};
    if (!data.user_show)
        data.user_show = {};
    if (typeof defaultData != "undefined"){
        for (var key in defaultData) {
            if (!defaultData.hasOwnProperty(key)) continue;
            if (!data[key])
                data[key] = defaultData[key];
        }
    }
    return data;
}

function initCollapsible(id){
    $(id+' .collapsible-header').on('click', function () {
        var data = myCookieInit();
        data.user_show[id.substr(1)+"_open"] = !$(this).hasClass("active");
        Cookies.set("frontend", data);
    });
}
