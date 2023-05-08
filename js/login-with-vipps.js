/*
This file is part of the plugin Login with Vipps
Copyright (c) 2019 WP-Hosting AS

MIT License

Copyright (c) 2019 WP-Hosting AS

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

// This can be used in buttons to login for the specified application
var vipps_loggingin = false;
function login_with_vipps(application, more_data) {
    if (vipps_loggingin) return;
    vipps_loggingin = true;
    var ajaxUrl = vippsLoginConfig.ajax_url;

    var data =  (typeof more_data == 'object' && more_data !== null) ? more_data : {};
    data['action'] = 'vipps_login_get_link';
    data['application'] = application;

    // Allow customizers to add more data to what is sent to the login process without it having been explicitly passed
    if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
         data = wp.hooks.applyFilters('loginWithVippsExtraData', data);
    }

    // for WPML. IOK 2021-08-31
    if (vippsLoginConfig['lang']) {
        data['lang'] = vippsLoginConfig['lang'];
    }


    if (!application) application='wordpress';
    jQuery.ajax(ajaxUrl, {
       data: data,
       method: 'POST',
       dataType: 'json',
       error: function (jqXHR,textStatus,errorThrown) {
           vipps_loggingin = false;
           alert("Error " + textStatus);
       },
       success: function(data, textStatus, jqXHR) {
         if (data && data['url']) {
           window.location.href = data['url'];
         } else {
           vipps_loggingin = false;
         }
       }

    });
}
// This is for blocks, which can't use onclick or really any stored javascript IOK 2020-12-16
jQuery(document).ready(function () {
  jQuery('.vipps-button.continue-with-vipps.continue-with-vipps-action').click(function (e) {
    login_with_vipps(jQuery(this).data('application'));
  });
});

