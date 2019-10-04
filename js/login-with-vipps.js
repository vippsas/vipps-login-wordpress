
// This can be used in buttons to login for the specified application
function login_with_vipps(application) {
    console.log("app is " + application);
    var ajaxUrl = vippsLoginConfig.ajax_url;
    if (!application) application='wordpress';
    jQuery.ajax(ajaxUrl, {
       data: { 'action': 'vipps_login_get_link', 'application' : application},
       dataType: 'json',
       error: function (jqXHR,textStatus,errorThrown) {
           alert("Error " + textStatus);
       },
       success: function(data, textStatus, jqXHR) {
         if (data && data['url']) {
           window.location.href = data['url'];
         }
       }

    });
}
