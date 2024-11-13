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

function login_with_vipps_button_block_hooks() {
    add_action('init', function () {
        register_block_type(__DIR__ . '/build/login-with-vipps-button');
    });


    add_action('admin_enqueue_scripts', function () {
        $vipps_login = VippsLogin::instance();
        $login_method = $vipps_login->get_login_method();

        $applications = [
            ['label' => __("Log in to WordPress", 'login-with-vipps'), 'value' => 'wordpress']
        ];
        $gotWoo = false;
        if (class_exists('VippsWooLogin') && VippsWooLogin::instance()->is_active()) {
            $gotWoo = true;
            $applications[] = ['label' => __("Log in to WooCommerce", 'login-with-vipps'), 'value' => 'woocommerce'];
        }


        $block_config = [
            'title' => sprintf(__('Log in with %1$s-button', 'login-with-vipps'), $login_method),
            'iconSrc' => $vipps_login->get_mark_svg(),
            'defaultApp' => $gotWoo ? 'woocommerce' : 'wordpress',
            'defaultTitle' => sprintf(__('Log in with %1$s!', 'login-with-vipps'), $login_method),
            'defaultTextPreLogo' => __('Log in with', 'login-with-vipps'),
            'defaultTextPostLogo' => __('!', 'login-with-vipps'),
            'loginMethod' => $login_method,
            'applications' => apply_filters('login_with_vipps_applications', $applications),
            'loginMethodLogoSrc' => $vipps_login->get_transparent_logo()
        ];

        wp_add_inline_script('login-with-vipps-login-with-vipps-button-editor-script',
            'const injectedBlockConfig = ' . json_encode($block_config),
            'before');
    });
}

login_with_vipps_button_block_hooks();
