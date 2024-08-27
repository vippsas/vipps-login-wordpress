<?php 
/*
   Plugin Name: Login with Vipps
   Version: 1.3.2
   Stable tag: 1.3.2
   Description: No need to remember passwords ever again. Vipps, and you are logged in.
   Author: WP-Hosting AS
   Plugin URI: https://wordpress.org/plugins/login-with-vipps/
   Description: Use Vipps for passwordless login and more. Integrates perfectly with WooCommerce.
   Author: WP Hosting
   Author URI: https://www.wp-hosting.no/
   Requires at least: 4.9.6
   Tested up to: 6.6.1
   Requires PHP: 7.2
   Text-domain: login-with-vipps
   Domain Path: /languages
   License: MIT  
   License URI: https://choosealicense.com/licenses/mit/

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



if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define('VIPPS_LOGIN_VERSION', '1.3.2');

// Utility classes used to verify JWT tokens and manage login sessions. IOK 2019-10-14
require_once(dirname(__FILE__) . '/VippsJWTVerifier.class.php');

// Handle the issue with return type 'mixed' IOK 2023-01-09
if (PHP_MAJOR_VERSION >= 8) {
   require_once(dirname(__FILE__) . '/VippsSession.class.php');
} else {
   require_once(dirname(__FILE__) . '/legacy/VippsSession.class.php');
}

// This is the main class, a singleton. We'll store it in a global variable for hook-writing-convenience. IOK 2019-10-14
global $ContinueWithVipps;
require_once(dirname(__FILE__) . '/ContinueWithVipps.class.php');
$ContinueWithVipps = ContinueWithVipps::instance();
register_activation_hook(__FILE__,array($ContinueWithVipps,'activate'));
register_deactivation_hook(__FILE__,array('ContinueWithVipps','deactivate'));
add_action('init',array($ContinueWithVipps,'init'));
add_action('plugins_loaded', array($ContinueWithVipps,'plugins_loaded'));
if (is_admin()) {
    add_action('admin_init',array($ContinueWithVipps,'admin_init'));
    add_action('admin_menu',array($ContinueWithVipps,'admin_menu'));
} else {
    // IOK 2019-12-06 The below is required only because certain plugins in this
    // hook assumes they own every return with 'state' and 'code' args.
    add_action('parse_request',array($ContinueWithVipps,'parse_request'),1);
    add_action('template_redirect',array($ContinueWithVipps,'template_redirect'),1);
}

// This class implements all login logic. IOK 2019-10-14
global $VippsLogin;
require_once(dirname(__FILE__) . '/VippsLogin.class.php');
$VippsLogin=VippsLogin::instance();
register_activation_hook(__FILE__,array($VippsLogin,'activate'));
register_deactivation_hook(__FILE__,array('VippsLogin','deactivate'));
register_activation_hook(__FILE__,array($VippsLogin,'activate'));
register_deactivation_hook(__FILE__,array($VippsLogin,'deactivate'));
add_action('init',array($VippsLogin,'init'));
if (is_admin()) {
    add_action('admin_init',array($VippsLogin,'admin_init'));
} else {
    add_action('template_redirect',array($VippsLogin,'template_redirect'));
}


// And if WooCommerce is installed, integrate with that with another class. IOK 2019-10-14
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if(is_plugin_active( 'woocommerce/woocommerce.php')) { 
    require_once(dirname(__FILE__) . '/VippsWooLogin.class.php');
    global $VippsWooLogin;
    $VippsWooLogin=VippsWooLogin::instance();
    register_activation_hook(__FILE__,array($VippsWooLogin,'activate'));
    register_deactivation_hook(__FILE__,array('VippsWooLogin','deactivate'));
    add_action('init',array($VippsWooLogin,'init'));
    add_action('plugins_loaded',array($VippsWooLogin,'plugins_loaded'));
    if (is_admin()) {
        add_action('admin_init',array($VippsWooLogin,'admin_init'));
    }
}

// Gutenberg block for the block editor, if installed. IOK 2020-12-15
require_once(dirname(__FILE__) . '/blocks/login-with-vipps.php');




?>
