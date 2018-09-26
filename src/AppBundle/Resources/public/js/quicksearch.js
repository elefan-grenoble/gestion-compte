$(document).ready(function() {
    $('.main-navigation').click(function (e) {
        if ($(e.target).is('div.nav-wrapper')){
            $('#quick_search_nav').slideToggle($('#quick_search_nav').is(':visible'));
            $('#quick_search').val('');
            $('#quick_search').focus();
            $('#quick_search').one('focusout',function () {
                $('#quick_search_nav').slideUp();
            });
        }
    });
    $('#quick_search_form').submit(function (e) {e.preventDefault()});
    $('#quick_search_close').click(function () {
        $('#quick_search_nav').slideUp();
    });
    $('#quick_search').keyup(function () {
        if ($(this).val().length > 1){
            $.ajax({
                type: "POST",
                url: "{{ path('search') }}",
                data: {
                    key: $(this).val()
                },
                dataType: "json",
                success: function(response) {
                    // console.log(response.data);
                    if (response && response.count > 0){
                        var beneficiaries = response.data;
                        var dataBeneficiaries = {};
                        var dataUsernames = {};
                        for (var i = 0; i < beneficiaries.length; i++) {
                            dataBeneficiaries[beneficiaries[i].name] = beneficiaries[i].icon;
                            dataUsernames[beneficiaries[i].id] = beneficiaries[i].username;
                        }
                        //$('#quick_search').autocomplete('destroy');
                        $('#quick_search').autocomplete({
                            data: dataBeneficiaries,
                            limit: 3,
                            onAutocomplete: function(val) {
                                $('#quick_search_nav').slideUp();
                                console.log(val);
                                const regex = /\(([0-9]*)\)/gm;
                                let m;

                                if ((m = regex.exec(val)) !== null) {
                                    var beneficiary_id = m[1];
                                    var username = dataUsernames[beneficiary_id];
                                    var res = show_user_template_url.replace("-USERNAME-", username);
                                    window.location = res;
                                }
                            },
                            minLength: 3,
                        });
                    }else{
                        console.log("no results for '"+$(this).val()+"'");
                    }
                }
            });
        }
    });

})