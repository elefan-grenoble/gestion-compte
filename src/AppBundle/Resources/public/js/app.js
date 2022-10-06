$(document).ready(function() {
    // initialize Materialize behavior - https://materializecss.com/
    $('select').formSelect();
    // $('datepicker').datepicker();  // see datepicker.js
    $('.sidenav').sidenav({
        menuWidth: 300, // Default is 300
        edge: 'left', // Choose the horizontal origin
        closeOnClick: true, // Closes side-nav on <a> clicks, useful for Angular/Meteor
        draggable: true, // Choose whether you can drag to open on touch screens
    });
    $('.modal').modal({
        ready: function (modal) {
            $(modal).find('.simplemde-container').trigger('modalOpen'); // tell markdown editor to refresh
        },
    });
    $('.collapsible').collapsible();
    $('.collapsible.collapsible-expandable').collapsible({ accordion: false });
    $('.tooltipped').tooltip();
    $(".dropdown-trigger").dropdown();
    // $('input.autocomplete').autocomplete();  // see specific files for initialization & configuration
});

function myCookieInit(defaultData){
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