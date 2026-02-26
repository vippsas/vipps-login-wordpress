<?php
/**
 * This file is part of the plugin Login with Vipps
 * Copyright (c) 2019 WP-Hosting AS
 *
 *
 * MIT License
 *
 * Copyright (c) 2019 WP-Hosting AS
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */


add_action('init', function () {
    register_block_type(__DIR__ . '/dist/login-with-vipps-button');
});


// Inject block config variables to the login-with-vipps-button editor script. LP 14.11.2024
add_action('enqueue_block_editor_assets', function () {
    wp_add_inline_script(
        'login-with-vipps-login-with-vipps-button-editor-script',
        'const injectedLoginWithVippsBlockConfig = ' . json_encode(VippsLogin::instance()->login_with_vipps_block_config()),
        'before'
    );
});

// Add scripts for web components to the block editor. You would expect this to work with block.json
// or enqueue_block_editor_assets, but no, that doesn't work at all. THIS works though.  IOK 2026-02-25
// https://developer.wordpress.org/block-editor/how-to-guides/enqueueing-assets-in-the-editor/
add_action('enqueue_block_assets', function () {
    // But only for admin backend... LP 2026-02-25
    if (!is_admin()) {
        return;
    }

    wp_enqueue_script(
        "vipps-button-webcomponent",
        "https://checkout.vipps.no/checkout-button/v1/vipps-checkout-button.js",
        [],
        VIPPS_LOGIN_VERSION,
        ['in_footer' => true, 'strategy'  => 'async'],
    );
});
// web component for frontend. LP 2026-02-25
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        "vipps-button-webcomponent",
        "https://checkout.vipps.no/checkout-button/v1/vipps-checkout-button.js",
        [],
        VIPPS_LOGIN_VERSION,
        ['in_footer' => true, 'strategy'  => 'async'],
    );
});
