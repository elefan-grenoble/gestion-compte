var barcode="";
function bc_focus() {
    $('input[name="swipe_code"]').focus();
    setTimeout(bc_focus,200);
}
let initSwipeCode = function (barcode_submit_url) {
    var form = $('<form style="display: block;position:fixed;bottom:0;left: 0;background: transparent; opacity: 0;" action="' + barcode_submit_url + '" method="post">' +
        '<input type="text" name="swipe_code" value="' + barcode + '" />' +
        '</form>');
    $('body').append(form);
    bc_focus();
    $('input[name="swipe_code"]').keypress(function (e) {

        var code = (e.keyCode ? e.keyCode : e.which);
        var toReplace = [224, 38, 233, 34, 39, 40, 45, 232, 95, 231];
        var replacedBy = [48, 49, 50, 51, 52, 53, 54, 55, 56, 57];
        if (toReplace.indexOf(code) >= 0) {
            code = replacedBy[toReplace.indexOf(code)];
        }
        if (code == 13 || code == 9)// Enter or tab key hit
        {
            if (barcode.length) {
                $('input[name="swipe_code"]').val(barcode);
                form.submit();
            }
            barcode = "";
        }
        else {
            if (code <= 122) { //0-9, a-z, A-Z
                barcode = barcode + String.fromCharCode(code);
            }
        }
    });
}

global.initSwipeCode = initSwipeCode