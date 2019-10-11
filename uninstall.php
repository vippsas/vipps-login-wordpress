<?php
// This file will uninstall the sessions table when the plugin is uninstalled.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

error_log("This is uninstall");


function login_with_vipps_cleanup() {
	global $wpdb;
	$prefix = $wpdb->prefix;
	$tablename = $wpdb->prefix . 'vippsLoginSessions';
	delete_option('vipps_login_options');
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





