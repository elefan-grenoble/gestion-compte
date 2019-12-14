$(document).ready(function() {
    $(document).on('click',function(event) {
        $target = $(event.target);
        if(!$target.closest('#quick_search_nav').length &&
            $('#quick_search_nav').is(":visible") &&
            !$target.closest('div.nav-wrapper').length) {
                $('#quick_search_nav').slideUp();
        }
    });
    $('.main-navigation').click(function (e) {
        if ($(e.target).is('div.nav-wrapper')){
            $('#quick_search_nav').slideToggle($('#quick_search_nav').is(':visible'));
            $('#quick_search').val('');
            $('#quick_search').focus();
        }
    });
    $('#quick_search_form').submit(function (e) {e.preventDefault()});
    $('#quick_search_close').click(function () {
        $('#quick_search_nav').slideUp();
    });

    $('#quick_search').keydown(function () {
        var val = $(this).val();
        if ($(this).val().length > 1){
            $.ajax({
                type: "POST",
                url: search_url,
                data: {
                    key: val,
                },
                dataType: "json"
            }).done(function (data, textStatus, jqXHR) {
                //console.log(data);
                $('#quick_search_form .autocomplete-content').html('').hide();
                if (data && data.count > 0){
                    var results = data.data;
                    var regex = /\(([0-9]*)\)/gm;
                    for (var i = 0; i < results.length; i++) {
                        var link = results[i].url;
                        var content = highlight($('#quick_search').val(),results[i].name);
                        var $row = $('<li><a href="'+link+'"></a></li>');
                        $row.find('a').html('<span>'+content+'</span>');
                        $row.appendTo('#quick_search_form .autocomplete-content');
                    }
                    $('#quick_search_form .autocomplete-content').show();
                }else{
                    console.log("no results for '"+val+"'");
                }
            });
        }else{
            $('#quick_search_form .autocomplete-content').html('').hide();
        }
    });

})

function highlight(text,inputText) {
    var regex = new RegExp(text,'gi');
    return inputText.replace(regex,"<span class='highlight'>$&</span>");
}