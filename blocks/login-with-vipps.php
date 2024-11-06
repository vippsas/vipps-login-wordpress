<?php
/**
 * Functions to register client-side assets (scripts and stylesheets) for the
 * Gutenberg block.
 *
 * @package login-with-vipps

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

/**
 * Registers all block assets so that they can be enqueued through Gutenberg in
 * the corresponding context.
 *
 * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/tutorials/block-tutorial/applying-styles-with-stylesheets/
 */
function login_with_vipps_block_init() {
	// Skip block registration if Gutenberg is not enabled/merged.
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	$dir = dirname( __FILE__ );


        $applications = array(array('label'=> __("Log in to WordPress", 'login-with-vipps'), 'value'=>'wordpress'));
     
        $gotWoo = false;
        if (class_exists('VippsWooLogin') && VippsWooLogin::instance()->is_active()) {
          $gotWoo = true;
          $applications[] = array('label' => __("Log in to WooCommerce", 'login-with-vipps'), 'value'=>'woocommerce');
        }



        $login_method = VippsLogin::instance()->get_login_method();
        $localizations = array();
        $localizations['BlockTitle'] = sprintf(__('Log in with %1$s-button', 'login-with-vipps'), $login_method); 

        $localizations['applications'] = apply_filters('login_with_vipps_applications', $applications);
        $localizations['defaultapp'] = ($gotWoo ? 'woocommerce' : 'wordpress');

        $localizations['Application'] = __('Application', 'login-with-vipps');
        $localizations['ApplicationsText'] = sprintf(__('The continue with %1$s-button can perform different actions depending on what is defined in your system. Per default it will log you in to WordPress or WooCommerce if installed, but plugins and themes can define more', 'login-with-vipps'), $login_method);
        $localizations['Title'] = __('Title', 'login-with-vipps');
        $localizations['TitleText'] = __('This will be used as the title/popup of the button', 'login-with-vipps');
 
        $localizations['DefaultTextPrelogo'] = __('Log in with', 'login-with-vipps'); 
        $localizations['DefaultTextPostlogo'] = __('!', 'login-with-vipps'); 
        $localizations['DefaultTitle'] = sprintf(__('Log in with %1$s!', 'login-with-vipps'), $login_method); 


        $localizations['loginmethodlogosrc'] = VippsLogin::instance()->get_transparent_logo();
        $localizations['vmplogosrc'] = plugins_url('../img/vmp-logo.png',__FILE__);
        $localizations['loginmethod'] = VippsLogin::instance()->get_login_method();


	$index_js = 'login-with-vipps-button/index.js';
	wp_register_script(
		'login-with-vipps-button-block-editor',
		plugins_url( $index_js, __FILE__ ),
		array(
			'wp-blocks',
			'wp-block-editor',
			'wp-components',
			'wp-compose',
			'wp-i18n',
			'wp-element',
		),
		filemtime( "$dir/$index_js" )
	);
        wp_localize_script('login-with-vipps-button-block-editor', 'LoginWithVippsBlockConfig', $localizations);

	$editor_css = 'login-with-vipps-button/editor.css';
	wp_register_style(
		'login-with-vipps-button-block-editor',
		plugins_url( $editor_css, __FILE__ ),
		array(),
		filemtime( "$dir/$editor_css" )
	);

	$style_css = 'login-with-vipps-button/style.css';
	wp_register_style(
		'login-with-vipps-button-block',
		plugins_url( $style_css, __FILE__ ),
		array(),
		filemtime( "$dir/$style_css" )
	);

	register_block_type( 'login-with-vipps/login-with-vipps-button', array(
		'editor_script' => 'login-with-vipps-button-block-editor',
		'editor_style'  => 'login-with-vipps-button-block-editor',
		'style'         => 'login-with-vipps-button-block',
	) );
}
add_action( 'init', 'login_with_vipps_block_init' );
