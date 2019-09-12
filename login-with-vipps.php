<?php 
/*
Plugin Name: Login with Vipps
Version: 0.01
Description: Passwordless login and stuff using Vipps
Author: WP-Hosting AS
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


require_once(dirname(__FILE__) . '/VippsLogin.class.php');
global $VippsLogin;
$VippsLogin = VippsLogin::instance();

register_activation_hook(__FILE__,array($VippsLogin,'activate'));
register_deactivation_hook(__FILE__,array($VippsLogin,'deactivate'));

# Use a separate uninstall file instead maybe IOK FIXME
register_uninstall_hook(__FILE__,array('VippsLogin','uninstall'));


add_action('init',array($VippsLogin,'init'));
add_action('after_setup_theme', array($VippsLogin,'after_setup_theme'));
add_action('plugins_loaded', array($VippsLogin,'plugins_loaded'));

if (is_admin()) {
 add_action('admin_init',array($VippsLogin,'admin_init'));
 add_action('admin_menu',array($VippsLogin,'admin_menu'));
} else {
 add_action('template_redirect',array($VippsLogin,'template_redirect'));
}


?>
