// This is used for logged-in users to connect their account to their Vipps-account
function connect_vipps_account(application) {
    var ajaxUrl = vippsLoginAdminConfig.ajax_url;
    var nonce = vippsLoginAdminConfig.vippsconfirmnonce;
    if (!application) application='wordpress';
    jQuery.ajax(ajaxUrl, {
       data: {  'action': 'vipps_confirm_get_link', 'vippsconfirmnonce':nonce, 'application' : application},
       method: 'POST',
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
    return false;
}
// This is used for logged-in users to synchronize their address with Vipps. Mostly useful for appliciatons  like Woo
function vipps_synch_address(application) {
    var ajaxUrl = vippsLoginAdminConfig.ajax_url;
    var nonce = vippsLoginAdminConfig.vippsconfirmnonce;
    if (!application) application='wordpress';
    jQuery.ajax(ajaxUrl, {
       data: {  'action': 'vipps_synch_get_link', 'vippsconfirmnonce':nonce, 'application' : application},
       method: 'POST',
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
    return false;
}
