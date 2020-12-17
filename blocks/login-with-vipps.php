<?php
/**
 * Functions to register client-side assets (scripts and stylesheets) for the
 * Gutenberg block.
 *
 * @package login-with-vipps
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


        $localizations = array();
        $localizations['applications'] = array('wordpress', 'woocommerce');
        $localizations['logosrc'] = plugins_url('../img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
        $localizations['vippssmileurl'] = plugins_url('../img/vipps-smile-orange.png',__FILE__);

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
