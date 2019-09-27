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

require_once(dirname(__FILE__) . '/VippsSession.class.php');

global $ContinueWithVipps;
require_once(dirname(__FILE__) . '/ContinueWithVipps.class.php');
$ContinueWithVipps = ContinueWithVipps::instance();

register_activation_hook(__FILE__,array($ContinueWithVipps,'activate'));
register_deactivation_hook(__FILE__,array($ContinueWithVipps,'deactivate'));

add_action('init',array($ContinueWithVipps,'init'));
add_action('plugins_loaded', array($ContinueWithVipps,'plugins_loaded'));
if (is_admin()) {
 add_action('admin_init',array($ContinueWithVipps,'admin_init'));
 add_action('admin_menu',array($ContinueWithVipps,'admin_menu'));
} else {
 add_action('template_redirect',array($ContinueWithVipps,'template_redirect'));
}

global $VippsLogin;
require_once(dirname(__FILE__) . '/VippsLogin.class.php');
$VippsLogin=VippsLogin::instance();
register_activation_hook(__FILE__,array($VippsLogin,'activate'));
register_deactivation_hook(__FILE__,array($VippsLogin,'deactivate'));
add_action('init',array($VippsLogin,'init'));
if (is_admin()) {
 add_action('admin_init',array($VippsLogin,'admin_init'));
}


?>
