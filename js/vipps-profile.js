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


// This is used for logged-in users to connect their account to their Vipps-account
function connect_vipps_account(application) {
    var ajaxUrl = vippsLoginProfileConfig.ajax_url;
    var nonce = vippsLoginProfileConfig.vippsconfirmnonce;
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
    var ajaxUrl = vippsLoginProfileConfig.ajax_url;
    var nonce = vippsLoginProfileConfig.vippsconfirmnonce;
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
