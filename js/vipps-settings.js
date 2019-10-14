jQuery(document).ready(function () {
 if (typeof(pagenow) != undefined && pagenow == 'settings_page_vipps_login_options') {
   jQuery('input.vippspw').focus( function () { jQuery(this).attr('type','text') });;
   jQuery('input.vippspw').focusout( function () { jQuery(this).attr('type','password');  });
  }
});
