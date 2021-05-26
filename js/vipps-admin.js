
 jQuery(document).ready(function () {
    jQuery('.notice-vipps-login .notice-dismiss').click(function () {
        let nonce = LoginVippsConfig['vippssecnonce'];
        let key = jQuery(this).closest('.notice').data('key');
        if (! key) return;
        let data = { 'action': 'login_vipps_dismiss_notice', 'vipps_sec':nonce, 'key': key };
        jQuery.ajax(ajaxurl, { "method": "POST", "data":data, "cache":false, "dataType": "json", "timeout":0 });
    });
 });
