<?php
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

// This file will uninstall the sessions table when the plugin is uninstalled.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

error_log("Uninstalling Login with Vipps");


function login_with_vipps_cleanup() {
    global $wpdb;
    $prefix = $wpdb->prefix;
    $tablename = $wpdb->prefix . 'vippsLoginSessions';
    delete_option('vipps_login_options');
    delete_option('vipps_login_settings');
    $dropquery = "DROP TABLE IF EXISTS `{$tablename}`";
    $wpdb->query($dropquery);
}

if (!is_multisite()) { 
    login_with_vipps_cleanup();
} else {
    global $wpdb;
    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

    foreach ( $blog_ids as $blog_id ) {
        switch_to_blog( $blog_id );
        login_with_vipps_cleanup();
    }
    restore_current_blog();
}





