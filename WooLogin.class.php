<?php 
/* 
   WooLogin: 
   This class checks that WooCommerce is available, and if so, will add features and buttons for logging in and registering as customers.
   It will also add a Vipps tab to the customer profile page, and interact with the shopping flow of Woocommerce. It will also interact properly with Vipps payment gateways, if available.  IOK 2019-10-14

   This works by just adding a new 'application' to the actions already handled by VippsLogin, hooking into the actions that allow customizing errorhandling and success-redirects. It also ensures address information is updated (and kept synchronized). IOK 2019-10-14



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
class WooLogin{
    protected static $instance = null;
    protected $loginbuttonshown = 0;    // Ensure we show the login action just once on certain pages.
    protected $rewriteruleversion = 1;  // Can't avoid rewrite rule modifications for Woo

    function __construct() {
    }

    // This is a singleton page, access the single instance just using this method. IOK 2019-10-14
    public static function instance()  {
        if (!static::$instance) static::$instance = new WooLogin();
        return static::$instance;
    }

    // The main init hook. We do nothing withouT Woo being active, but if it is, we hook into loads of things
    // that happen when logging in using Vipps, and with the Woo order flow. IOK 2019-10-14
    public function init () {
        if (!class_exists( 'WooCommerce' )) return;

        $woologin= $this->is_active();

        $this->add_rewrite_rules();

        if ($woologin) {
            $this->add_stored_woocommerce_notices();
            // Adding the login / register buttons to the 'myaccount' page IOK 2019-10-14
            add_action('woocommerce_before_customer_login_form' , array($this, 'login_with_vipps_banner'));
            add_action('woocommerce_login_form_start' , array($this, 'login_with_vipps_banner'));
            add_action('woocommerce_register_form_start' , array($this, 'register_with_vipps_banner'));

            // Adding 'Vipps' to the customer profile page IOK 2019-10-14
            add_action('woocommerce_account_dashboard', array($this,'account_dashboard'));
            add_action('woocommerce_account_content', array($this,'account_content'));
            add_filter ('woocommerce_account_menu_items', array($this,'account_menu_items' ));
            add_action('woocommerce_account_vipps_endpoint', array($this,'account_vipps_content'));
            add_filter('add_query_vars', array($this, 'add_vipps_endpoint_query_var'));
            add_filter('the_title', array($this, 'account_vipps_title'), 10,2); // the  woocommerce_endpoint_vipps_filter does not work.

            // 'disconnect' action IOK 2019-10-14
            if (is_user_logged_in()) {
                add_filter('wp_enqueue_scripts', array($this, 'wp_enqueue_account_scripts'));
            }
            add_action('admin_post_disconnect_vipps', array($this,'disconnect_vipps_post_handler'));

            // Login stuff
            add_filter('continue_with_vipps_before_woocommerce_login_redirect', array($this, 'add_login_redirect'), 10, 2);
            add_filter('continue_with_vipps_woocommerce_users_can_register', array($this, 'users_can_register'), 10, 3);
            add_filter('continue_with_vipps_woocommerce_create_userdata', array($this, 'create_userdata'), 10, 3);
            add_filter('continue_with_vipps_woocommerce_create_username', array($this, 'create_username'), 10, 3);
            add_filter('continue_with_vipps_after_create_woocommerce_user', array($this, 'after_create_user'), 10, 2);
            add_filter('continue_with_vipps_woocommerce_allow_login', array($this, 'allow_login'), 10, 4);
            add_filter('continue_with_vipps_before_woocommerce_user_login', array($this, 'before_login'), 10, 3);
            add_filter("continue_with_vipps_error_woocommerce_login_create_session", array($this,'login_error_create_session'), 10, 2);
            add_filter("continue_with_vipps_error_woocommerce_login_redirect", array($this,'error_redirect'), 10, 3);
            add_action("continue_with_vipps_error_woocommerce_login", array($this, 'add_woocommerce_error'), 10, 4);

            // Account confirmation
            add_action("continue_with_vipps_woocommerce_confirm_before_redirect", array($this,'before_confirm_redirect'), 10, 3); 
            add_filter("continue_with_vipps_woocommerce_confirm_redirect", array($this,'confirm_redirect'), 10, 3);
            add_action('continue_with_vipps_error_woocommerce_confirm', array($this, 'add_woocommerce_error'), 10, 4);
            add_filter("continue_with_vipps_error_woocommerce_confirm_redirect", array($this,'error_redirect'), 10, 3); 

            // And for synching addresses
            add_action('continue_with_vipps_woocommerce_synch', array($this, 'synch_addresses'), 10, 3);
            add_filter("continue_with_vipps_woocommerce_synch_redirect", array($this,'confirm_redirect'), 10, 3);
            add_action('continue_with_vipps_error_woocommerce_synch', array($this, 'add_woocommerce_error'), 10, 4);
            add_filter("continue_with_vipps_error_woocommerce_synch_redirect", array($this,'error_redirect'), 10, 3); 
            add_action('woocommerce_after_edit_account_address_form', array($this, 'synch_address_button'));

            // This runs only if the user modifies their own address in Woo. If they do, we break the Vipps connection until next synchronizatoin. IOK 2019-10-14
            add_action("woocommerce_customer_save_address", array($this,'customer_save_address'), 80);


            $this->add_shortcodes();
        }
    }

    // The hooks added here add the 'continue_with_vipps' buttons to the order flow - into the cart, the checkout page, and so forth.
    public function plugins_loaded () {
        add_action('woocommerce_proceed_to_checkout', array($this,'cart_continue_with_vipps'));
        add_action('woocommerce_widget_shopping_cart_buttons', array($this, 'cart_widget_continue_with_vipps'), 30);
        add_action('woocommerce_before_checkout_form', array($this, 'before_checkout_form_login_button'), 5);
    }


    public function admin_init () {
        if (!class_exists( 'WooCommerce' )) return;
        // Extra ettings that will end up on the simple "Login with Vipps" options screen IOK 2019-10-14
        register_setting('vipps_login_woo_options','vipps_login_woo_options', array($this,'validate'));
        add_action('continue_with_vipps_extra_option_fields', array($this,'extra_option_fields'));
    }

    // Return any Vipps payment gateway if installed. IOK 2019-10-14
    public function payment_gateway() {
        $gw = null;
        WC()->payment_gateways(); // Ensure gateways are loaded.
        if (class_exists('WC_Gateway_Vipps')) {
            $gw = WC_Gateway_Vipps::instance(); 
        } 
        // There are more than one Vipps gateway, so allow integration with these as well as the WP-Hosting one. IOK 2019-10-10
        $gw = apply_filters('continue_with_vipps_payment_gateway', $gw);
        return $gw;
    }

    // This is true iff the Vipps gateway is installed and active. Returns the gateway if it is on, so one
    // can check stuff like express checkout and so forth. IOK 2019-10-10
    public function is_gateway_active() {
        $gw = $this->payment_gateway();

        if ($gw && $gw->enabled != 'yes') return false;
        if ($gw) return $gw; // Otherwise it is null
        return false;
    }

    // We are going to add some extra configuration options here. IOK 2019-10-14
    public function extra_option_fields () {
        $options = get_option('vipps_login_woo_options');
        $woologin= $options['woo-login'];
        $woocreate = $options['woo-create-users'];
        $woocart = $options['woo-cart-login'];
        $woocheckout = $options['woo-checkout-login'];
        ?>

            <form action='options.php' method='post'>
            <?php settings_fields('vipps_login_woo_options'); ?>
            <table class="form-table" style="width:100%">
            <tr><th colspan=3><h3><?php _e('WooCommerce options', 'login-with-vipps'); ?></th></tr>
            <tr>
            <td><?php _e('Enable Login with Vipps for WooCommerce', 'login-with-vipps'); ?></td>
            <td width=30%> <input type='hidden' name='vipps_login_woo_options[woo-login]' value=0>
            <input type='checkbox' name='vipps_login_woo_options[woo-login]' value=1 <?php if ( $woologin ) echo ' CHECKED '; ?> >
            </td>
            <td>
            <?php _e('Enable Login with Vipps on your customer\'s pages in WooCommerce', 'login-with-vipps'); ?>
            </td>
            </tr>
            <tr>
            <td><?php _e('Allow users to register as customers in WooCommerce using Login with Vipps', 'login-with-vipps'); ?></td>
            <td width=30%> <input type='hidden' name='vipps_login_woo_options[woo-create-users]' value=0>
            <input type='checkbox' name='vipps_login_woo_options[woo-create-users]' value=1 <?php if ( $woocreate) echo ' CHECKED '; ?> >
            </td>
            <td>
            <?php _e('Enable new users to be created as customers if using Login with Vipps with WooCommerce.', 'login-with-vipps'); ?>
            </td>
            </tr>
            <tr>
            <td><?php _e('Show "Continue with Vipps" in Cart page and widgets', 'login-with-vipps'); ?></td>
            <td width=30%> <input type='hidden' name='vipps_login_woo_options[woo-cart-login]' value=0>
            <input type='checkbox' name='vipps_login_woo_options[woo-cart-login]' value=1 <?php if ( $woocart) echo ' CHECKED '; ?> >
            </td>
            <td>
            <?php _e('If you are using Vipps Express Checkout, that will be shown instead.', 'login-with-vipps'); ?>
            </td>
            </tr>
            <tr>
            <td><?php _e('Show "Continue with Vipps" on the Checkout page', 'login-with-vipps'); ?></td>
            <td width=30%> <input type='hidden' name='vipps_login_woo_options[woo-checkout-login]' value=0>
            <input type='checkbox' name='vipps_login_woo_options[woo-checkout-login]' value=1 <?php if ( $woocheckout) echo ' CHECKED '; ?> >
            </td>
            <td>
            <?php _e('This will replace Vipps Express Checkout on the checkout page.', 'login-with-vipps'); ?>
            </td>
            </tr>
            </table>
            <div><input type="submit" style="float:left" class="button-primary" value="<?php _e('Save Changes') ?>" /> </div>
            </form>
            <?php
    }

    // Validate the extra options added by this plugin. IOK 2019-10-14
    public function validate ($input) {
        $current =  get_option('vipps_login_woo_options');
        if (empty($input)) return $current;
        $valid = array();
        foreach($input as $k=>$v) {
            switch ($k) {
                default:
                    $valid[$k] = $v;
            }
        }
        return $valid;
    }

    public function activate () {
        $allowcreatedefault = apply_filters( 'woocommerce_checkout_registration_enabled', 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout' ) );
        $allowcreatedefault = $allowcreatedefault ||  ('yes' === get_option( 'woocommerce_enable_myaccount_registration' )) ;

        $default = array('rewriteruleversion'=>0, 'woo-login'=>true, 'woo-create-users'=>$allowcreatedefault, 'woo-cart-login'=>true,'woo-checkout-login'=>true);
        add_option('vipps_login_woo_options',$default,true);
        $this->add_rewrite_rules();
        $this->maybe_flush_rewrite_rules();
    }
    public static function deactivate () {
        // Nothing to do here. IOK 2019-10-14
    }

    // This is neccessary for the 'Vipps' tab on the myaccount page to work. IOK 2019-10-14
    public function add_rewrite_rules() {
        // This is for the myaccount/vipps endpoint
        add_rewrite_endpoint( 'vipps', EP_ROOT | EP_PAGES );
    }
    public function maybe_flush_rewrite_rules() {
        $options = get_option('vipps_login_woo_options');
        $rewrite = intval($options['rewriteruleversion']);
        if ($this->rewriteruleversion > $rewrite) {
            $this->add_rewrite_rules();
            $options['rewriteruleversion'] = $this->rewriteruleversion;
            update_option('vipps_login_woo_options', $options, true);
        }
    }
    public function add_vipps_endpoint_query_var ($vars) {
        $vars[]='vipps';
        return $vars;
    }

    // We can turn this on and off  for woocommerce in particular. IOK 2019-10-14
    public function is_active() {
        if (!VippsLogin::is_active()) return false;
        $options = get_option('vipps_login_woo_options');
        return intval($options['woo-login']);
    }


    // Action handlers; basically these just write out a button. IOK 2019-10-14
    public function cart_continue_with_vipps () {
        return $this->continue_with_vipps_button_for_carts('cart');
    }
    public function cart_widget_continue_with_vipps () {
        return $this->continue_with_vipps_button_for_carts('widget');
    }
    public function continue_with_vipps_button_for_carts($type='widget'){
        if (is_user_logged_in()) return false;
        if (!$this->is_active()) return false;
        if (WC()->cart->get_cart_contents_count() == 0) return false;

        $gw = $this->is_gateway_active();

        $options =  get_option('vipps_login_woo_options');
        $show_continue_with_vipps = intval($options['woo-checkout-login']);
        $express_checkout = $gw && $gw->show_express_checkout();

        $show_continue_with_vipps = apply_filters('continue_with_vipps_woo_show_in_cart', ($show_continue_with_vipps && !$express_checkout));
        if (!$show_continue_with_vipps) return;
        $this->cart_continue_with_vipps_button_html($type);
    }

    public function cart_continue_with_vipps_button_html($type) {
        if (!$this->is_active()) return false;
        ?>
            <div class='continue-with-vipps-wrapper center-block <?php echo $type; ?>'>
            <?php VippsLogin::instance()->login_button_html(__('Continue with', 'login-with-vipps'), 'woocommerce'); ?>
            </div>
            <?php
    }

    // This will display a banner  on the top of the checkout page. It will replace the express checkout button if that is used in the gateway. IOK 2019-10-14
    public function before_checkout_form_login_button () {
        if (is_user_logged_in()) return false;
        if (!$this->is_active()) return false;

        $options =  get_option('vipps_login_woo_options');
        $show_continue_with_vipps = intval($options['woo-checkout-login']);
        $show_continue_with_vipps = apply_filters('continue_with_vipps_woo_show_in_checkout', $show_continue_with_vipps);
        if (!$show_continue_with_vipps) return;

        // Replace expresscheckout here if using the standard Vipps payment gateway . Logging in with  Vipps is more compatible, and 
        // at this point, equally quick. IOK 2019-10-14
        add_action('woo_vipps_show_express_checkout', function ($show) { return false; });
        $this->continue_with_vipps_banner();
    }

    public function continue_with_vipps_banner() {
        if (!$this->is_active()) return false;

        // This is actually the filter used for customers, so we feed it dummy values - this is decided by an option.
        $can_register = $this->users_can_register(true, array(), array());

        if ($can_register) {
            $text = __('Log in or register an account with %s to continue your checkout.', 'login-with-vipps');
        } else {
            $text = __('Are you registered as a customer? Log in with %s to continue your checkout.', 'login-with-vipps');
        }
        $logo = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
        $linktext = __('Click to continue', 'login-with-vipps');

        $message = sprintf($text, "<img class='inline vipps-logo negative' border=0 src='$logo' alt='Vipps'/>") . "  -  <a href='javascript:login_with_vipps(\"woocommerce\");'>" . $linktext . "</a>";
        $message = apply_filters('continue_with_vipps_checkout_banner', $message);
        ?>
            <div class="woocommerce-info vipps-info"><?php echo $message;?></div>
            <?php
    }
    public function login_with_vipps_banner() {
        if (!$this->is_active()) return false;
        if ($this->loginbuttonshown) return false;
        $this->loginbuttonshown=1;

        // This is actually the filter used for customers, so we feed it dummy values - this is decided by an option.
        $can_register = $this->users_can_register(true, array(), array());
        $text  = '';
        if ($can_register) {
            $text = __('Log in or register an account using %s.', 'login-with-vipps');
        } else {
            $text = __('Are you registered as a customer? Log in with %s.', 'login-with-vipps');
        }
        $logo = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
        $linktext = __('Click here to continue', 'login-with-vipps');

        $message = sprintf($text, "<img class='inline vipps-logo negative' border=0 src='$logo' alt='Vipps'/>") . "  -  <a href='javascript:login_with_vipps(\"woocommerce\");'>" . $linktext . "</a>";
        $message = apply_filters('continue_with_vipps_login_banner', $message);
        ?>
            <div class="woocommerce-info vipps-info"><?php echo $message;?></div>
            <?php
    }
    public function register_with_vipps_banner() {
        if (!$this->is_active()) return false;
        if ($this->loginbuttonshown) return false;
        $this->loginbuttonshown=1;
        // This is actually the filter used for customers, so we feed it dummy values - this is decided by an option.
        $can_register = $this->users_can_register(true, array(), array());
        if (!$can_register) return;

        $logo = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
        $linktext = __('Click here to continue', 'login-with-vipps');
        $text = __('Create an account using ', 'login-with-vipps');

        $message = sprintf($text, "<img class='inline vipps-logo negative' border=0 src='$logo' alt='Vipps'/>") . "  -  <a href='javascript:login_with_vipps(\"woocommerce\");'>" . $linktext . "</a>";
        $message = apply_filters('continue_with_vipps_register_banner', $message);
        ?>
            <div class="woocommerce-info vipps-info"><?php echo $message;?></div>
            <?php
    }


    // We can't always add notices to the woo session, because we don't always have Woo loaded. So we'll use a transient to carry over .
    // We are always logged in here, so just use the cookie contents as a quickie session, using a short transient. IOK 2019-10-14
    // Any notices added will be shown on next page load, so this is used for both error handling and success messages. IOK 2019-10-14
    public function add_stored_woocommerce_notices() {
        $cookie = @$_COOKIE[LOGGED_IN_COOKIE];
        if (!$cookie) return;
        $cookiehash =  hash('sha256',$cookie,false);
        $notices = get_transient('_vipps_woocommerce_stored_notices_' . $cookiehash);
        if (empty($notices)) return;
        delete_transient('_vipps_woocommerce_stored_notices_' . $cookiehash);
        if ( ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }
        foreach($notices as $notice) {
            wc_add_notice($notice['notice'], $notice['type']);
        }
    }

    // Scripts added to the users' 'my account' page. Same as used on the profile page. IOK 2019-10-14
    public function wp_enqueue_account_scripts () {
        if (is_account_page()) {
            wp_enqueue_script('vipps-login-profile',plugins_url('js/vipps-profile.js',__FILE__),array('jquery'),filemtime(dirname(__FILE__) . "/js/vipps-profile.js"), 'true');
            wp_localize_script('vipps-login-profile', 'vippsLoginProfileConfig', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'vippsconfirmnonce'=>wp_create_nonce('vippsconfirmnonce') ) );
        }
    }

    public function add_shortcodes() {
        add_shortcode('woo-continue-with-vipps', array($this,'woo_continue_with_vipps_shortcode'));
    }

    public function woo_continue_with_vipps_shortcode($atts, $content, $tag) {
        if (!is_array($atts)) $atts = array();
        if (!isset($atts['application'])) $atts['application'] = 'woocommerce';
        return VippsLogin::instance()->continue_with_vipps_shortcode($atts,$content,$tag);
    }

    // This is run first on the users' main dashboard, right after menus. We hook into it to ensure the 'Vipps' tab is shown. IOK 2019-10-14
    public function account_dashboard() {
        // This only flushes rewrite rules when necessary. Adds a menu item for Vipps on the customers' My Account page. IOK 2019-10-14
        $this->maybe_flush_rewrite_rules();
    }

    // This is the main content of a users my-account page. This is the place for welcome messages, or nagging. IOK 2019-10-14
    public function account_content() {
        $userid = get_current_user_id();
        if (!$userid) return;
        $justconnected = get_user_meta($userid,'_vipps_just_connected',true);
        if ($justconnected) {
            delete_user_meta($userid, '_vipps_just_connected');
            $vippsphone = get_user_meta($userid,'_vipps_phone',true);
            $notice = sprintf(__('You are now connected to the Vipps profile <b>%s</b>!', 'login-with-vipps'), $vippsphone);
            ?>
                <div class='vipps-notice vipps-info vipps-success'><?php echo $notice ?></div>
                <?php
        }
    }
    // Add the 'Vipps' tab to the menu on the my account page. IOK 2019-10-14
    public function account_menu_items($items) {
        $items['vipps'] = __('Vipps', 'login-with-vipps');
        return $items;
    }
    // And add content to the 'Vipps' tab. . IOK 2019-10-14
    public function account_vipps_content() {
        add_filter('the_title', function ($title) { return __('Vipps!', 'login-with-vipps'); });
        $userid = get_current_user_id();
        if (!$userid) print "No user!";
        $user = new WC_Customer($userid);

        $options = get_option('vipps_login_woo_options');
        $allow_login = $options['woo-login'];
        $allow_login = apply_filters('continue_with_vipps_woocommerce_allow_login', $allow_login, $user, array(), array());
        $vippsphone = trim(get_user_meta($user->get_id(),'_vipps_phone',true));
        $vippsid = trim(get_user_meta($user->get_id(),'_vipps_id',true));

        ?>
            <?php    if ($vippsphone && $vippsid): ?>
            <h3><?php printf(__('You are connected to the Vipps profile with the phone number <b>%s</b>', 'login-with-vipps'), esc_html($vippsphone)); ?></h3>
            <p>
            <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
            <?php wp_nonce_field('disconnect_vipps', 'disconnect_vipps_nonce'); ?>
            <input type="hidden" name="action" value="disconnect_vipps">
            <input type="hidden" name="data" value="foobarid">
            <button style="margin-bottom:5px" type="submit" class='button vippsorange vipps-button vipps-disconnect'><?php _e('Unlink account', 'login-with-vipps'); ?></button>
            <?php $this->synch_address_button(); ?>
            </form>
            </p>
            <?php else: ?>
            <p><button type="button" onclick="connect_vipps_account('woocommerce');return false"; class="button vippsorange vipps-connect" value="1" name="vipps-connect"><?php _e('Press here to connect with your app','login-with-vipps'); ?></button></p>
            <?php endif; ?>
            <p> <?php _e('The easiest way to sign in. Anyone with Vipps can use Vipps to sign in. No need to remember passwords ever again. Vipps, and you are logged in.','login-with-vipps'); ?> </p>
            <?php
    }

    public function  synch_address_button () {
        $logo = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
        ?>
            <button type="button" onclick="vipps_synch_address('woocommerce');return false"; class="button vippsorange vipps-synch" value="1" name="vipps-synch">
            <?php printf(__('Get addresses','login-with-vipps'),  "<img class='inline vipps-logo negative' border=0 src='$logo' alt='Vipps'/>"); ?>
            </button> 
            <?php
    }

    // Disconnect. Done in a normal POST, but check nonce first. IOK 2019-10-14
    public function disconnect_vipps_post_handler () {
        check_admin_referer('disconnect_vipps', 'disconnect_vipps_nonce');
        $userid = get_current_user_id();
        if (!$userid) wp_die(__('You must be logged in to disconnect', 'login-with-vipps'));
        $phone = get_user_meta($userid, '_vipps_phone',true);

        delete_user_meta($userid,'_vipps_phone');
        delete_user_meta($userid,'_vipps_id');

        // Woocommerce hasn't loaded yet, so we'll just add the notices in a transient - we can't use the session
        // If they were critical, the users' metadata would have worked. IOK 2019-10-08
        // We are always logged in here, so just use the cookie contents as a quickie session.
        $cookie = @$_COOKIE[LOGGED_IN_COOKIE];
        if ($cookie) {
            $cookiehash =  hash('sha256',$cookie,false);
            $notices = get_transient('_vipps_woocommerce_stored_notices_' . $cookiehash);
            $notice = sprintf(__('Connection to Vipps profile %s <b>removed</b>.', 'login-with-vipps'), $phone);
            $notices[]=array('notice'=>$notice, 'type'=>'success');
            set_transient('_vipps_woocommerce_stored_notices_' . $cookiehash, $notices, 60);
        }        

        wp_safe_redirect(wp_get_referer());
        exit();
    }

    // For some reason we can't do this with woocommerce_endpoint_vipps_title. Set the title of this page to be what we want instead of 'my account'. IOK 2019-10-14
    public function account_vipps_title($title, $id) {
        if (in_the_loop() && !is_admin() && is_main_query() && is_account_page() ) {
            global $wp_query;
            $is_endpoint = isset($wp_query->query_vars['vipps']);
            if ($is_endpoint) {
                $title = __('Vipps!', 'login-with-vipps'); 
                remove_filter('the_title', array($this, 'account_vipps_title'), 10);
                return $title;
            }
        }
        return $title;
    }
    // Error handling doesn't require an extra session for Woocommerce. 2019-10-08
    public function login_error_create_session($createSession, $sessiondata) {
        return false;
    }
    // Woocommerce errors work sitewide, but we must use the 'wc_add_notice' code. We also need to ensure that
    // we have a session active (if nothing is in the cart yet). IOK 2019-10-14
    public function add_woocommerce_error ($error, $errordesc, $errorhint, $session) {
        // We can add woocommerce already here, as Woocommerce handles the session itself  IOK 2019-10-08
        // NB: This require that the woocommerce session is active.
        if ( ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }
        wc_add_notice(__($errordesc,'login-with-vipps'),'error');
        wc()->session->save_data();
    }

    // Handle errors for the 'woocommerce' login application on the users home. We pretty much always want to redirect to 
    // the exact same page we came from. IOK 2019-10-14
    public function error_redirect ($redir, $error, $sessiondata) {
        // If this happend on the checkout page then redirect there I guess
        $link = wc_get_page_permalink( 'myaccount' );
        if (isset($sessiondata['referer']) && $sessiondata['referer']) { 
            // If possible, report errors on same page we are
            $link = $sessiondata['referer'];
        }
        if ($link) return $link;
        return $redir;
    }

    // If a user saves their own address on the profile screen, we break the link with Vipps. This can be re-linked by
    // pressing the "Synchronize" button. For new users, this is true; and when true, we synch addresses at each login. IOK 2019-10-14
    public function customer_save_address () {
        $userid = get_current_user_id();
        if (!$userid) return;
        delete_user_meta($userid,'_vipps_synchronize_addresses', 1);
    }

    // User has just confirmed their account, so update the address if we can. IOK 2019-10-14
    public function before_confirm_redirect( $userid, $userinfo, $session) {
        $customer = new WC_Customer($userid);
        $this->maybe_update_address_info($customer,$userinfo);
        return true;
    }

    // Note that we want to synchronize addresses from now on . Also, actually do it. IOK 2019-10-14
    public function synch_addresses($userid,$userinfo, $session) {
        update_user_meta($user->ID,'_vipps_synchronize_addresses', 1);
        delete_user_meta($user->ID,'_vipps_just_synched', 1);
        // Woocommerce may not have loaded yet, so we'll just add the notices in a transient - we can't use the session
        // If they were critical, the users' metadata would have worked. IOK 2019-10-08
        // We are always logged in here, so just use the cookie contents as a quickie session.
        $cookie = @$_COOKIE[LOGGED_IN_COOKIE];
        if ($cookie) {
            $phone = $userinfo['phone'];
            $cookiehash =  hash('sha256',$cookie,false);
            $notices = get_transient('_vipps_woocommerce_stored_notices_' . $cookiehash);
            $notice = sprintf(__('Your addresses are now synchronized with the Vipps-account %s.', 'login-with-vipps'), $phone);
            $notices[]=array('notice'=>$notice, 'type'=>'success');
            set_transient('_vipps_woocommerce_stored_notices_' . $cookiehash, $notices, 60);
        }        

        $customer = new WC_Customer($userid);
        $this->maybe_update_address_info($customer,$userinfo);
        return true;
    }

    // When confirming, return to the same page we came from. IOK 2019-10-14
    public function confirm_redirect ($redir, $user , $sessiondata) {
        $link = wc_get_page_permalink( 'myaccount' );
        if (isset($sessiondata['referer']) && $sessiondata['referer']) { 
            // If possible, report errors on same page we are
            $link = $sessiondata['referer'];
        } else {
        }
        if ($link) return $link;
        return $redir;
    }

    // For logins, we want to *either* go to 'my account', or if something is in the cart,
    // to go directly to the checkout page. IOK 2019-10-14
    public function add_login_redirect($user, $session) {
        add_filter('login_redirect', array($this, 'login_redirect'), 99, 3);
    }
    public function login_redirect ($redir, $requested_redir, $user) {
        if (sizeof( WC()->cart->get_cart() ) > 0 ) {
            return wc_get_checkout_url();
        } else {
            $link = wc_get_page_permalink( 'myaccount' );
            if ($link) return $link;
            return $redir;
        }
    }

    // Standard Woo has several options for this depending on whether it is done on 'my account' or in the checkout page.
    // We take the easy way out and just use our own option, settable on the option screen. IOK 2019-10-14
    public function users_can_register($can_register,$userinfo,$session) {
        $options = get_option('vipps_login_woo_options');
        if ($options['woo-create-users']) return true;
        return false;
    }
    // This is run when creating users. We want our to be 'customer's. IOK 2019-10-14
    public function create_userdata($userdata,$userinfo,$session) {
        $userdata['role'] = 'customer';
        return $userdata;
    }
    // We'll use woocommerces own username functionality here. Run when a user is created. IOK 2019-10-14
    public function create_username($username, $userinfo, $sessio) {
        if (function_exists('wc_create_new_customer_username')) {
            return wc_create_new_customer_username($email, array('first_name'=>$userinfo['given_name'],  'last_name' =>  $userinfo['family_name']));
        } else {
            return $username;
        }
    }
    // This is run when a completely new user has been created. We want to note that we want to synchronize addresses (and to do that. IOK 2019-10-19)
    public function after_create_user($user, $session) {
        $userinfo = @$session['userinfo'];
        if (!$userinfo) return false;
        update_user_meta($user->ID,'_vipps_synchronize_addresses', 1);
        $this->maybe_update_address_info($user,$userinfo);
    } 

    // IOK 2019-10-14 currently, we just say 'yes' here, but we may want to disallow login for e.g. admins in this context.
    // will only affect Woocommerce logins.
    public function allow_login($allow, $user, $userinfo, $session) {
        $options = get_option('vipps_login_woo_options');
        $allow= $options['woo-login'];
        return $allow;
    }

    // Run when a user is freshly logged in (using Vipps, in Woocommerce). If the user hasn't chosen a payment method yet, choose Vipps! (if available.) IOK 2019-10-14
    public function before_login($user, $session) {
        $userinfo = @$session['userinfo'];
        if (!$userinfo) return false;
        /* Logging in with Vipps, so set that as payment method if possible and if the customer hasn't already chosen one IOK 2019-10-10 */
        $gw = $this->is_gateway_active();
        if ($gw) {
            $set = WC()->session->get('chosen_payment_method') ? false : true;
            $set = apply_filters('continue_with_vipps_set_default_payment_method', $set);
            if ($set) {
                WC()->session->set('chosen_payment_method', $gw->id);
            }
        }
        $customer = new WC_Customer($user->ID);
        if ($customer && !is_wp_error($customer)) {
            $billing = $customer->get_billing();
            $shipping = $customer->get_shipping();
            $all_empty = true;
            foreach($billing as $key=>$value) {
                if (!empty($value)) { $all_empty = false; break; }
            }
            foreach($shipping as $key=>$value) {
                if (!empty($value)) { $all_empty = false; break; }
            }
            // No address at all: Synch.
            if ($all_empty) {
                update_user_meta($user->ID,'_vipps_synchronize_addresses', 1);
            }
        } else { 
            error("no customer");
        }

        $this->maybe_update_address_info($user,$userinfo);
    }

    // IOK 2019-10-04 normally we want to update the users' address every time we log in, because this allows Vipps to be the repository of the users' address.
    // However, if the user has changed his or her address in woo itself, we will let it stay as it is. We handle this by a single use meta. IOK 2019-10-14
    public function maybe_update_address_info($user, $userinfo) {
        if (!get_user_meta($user->ID,'_vipps_synchronize_addresses',true)) return false;
        $customer = new WC_Customer($user->ID);
        $address = $userinfo['address'][0];
        foreach($userinfo['address'] as $add) {
            if ($add['address_type'] == 'home') {
                $address = $add; break;
            }
        }
        if (empty($address)) return;
        $firstname = $userinfo['given_name'];
        $lastname =  $userinfo['family_name'];
        $phone =  $userinfo['phone_number'];

        $country = $address['country'];
        if (!$country)  $country='NO';
        $street_address = $address['street_address'];
        $postal_code = $address['postal_code'];
        $region = $address['region'];

        $customer->set_billing_email($email);
        $customer->set_billing_phone($phone);
        $customer->set_billing_first_name($firstname);
        $customer->set_billing_last_name($lastname);
        $customer->set_billing_address_1($street_address);
        //        $customer->set_billing_address_2($addressline2);
        $customer->set_billing_city($region);
        $customer->set_billing_state('');
        $customer->set_billing_postcode($postal_code);
        $customer->set_billing_country($country);

        $customer->set_shipping_first_name($firstname);
        $customer->set_shipping_last_name($lastname);
        $customer->set_shipping_address_1($street_address);
        //        $customer->set_shipping_address_2($addressline2);
        $customer->set_shipping_city($region);
        $customer->set_shipping_state('');
        $customer->set_shipping_postcode($postal_code);
        $customer->set_shipping_country($country);
        $customer->save();
    }


}
