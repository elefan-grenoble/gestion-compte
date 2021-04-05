require('../css/app.css');

require('simplemde/dist/simplemde.min.js');
require('simplemde/dist/simplemde.min.css');

// require jQuery normally
const $ = require('jquery');
// create global $ and jQuery variables
global.$ = global.jQuery = $;

require('materialize-css/dist/js/materialize');
require('materialize-css/dist/css/materialize.css');
require('../css/materialize/iconfont/material-icons.css');

require('./barcode.js');
require('./datepicker.js');
require('./js.cookie.js');
require('./quicksearch.js');

require("../css/card.less");
require("../css/custom.less");
require("../css/post-it.less");
require("../css/shift.less");
require("../css/update.less");

// FIXME
// require("canvas-gauges/gauge.min.js")

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
