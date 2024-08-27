<?php 
/*
   VippsLogin
   This class uses the ContinueWithVipps class to implement *logins* and *confirmations* of user accounts so that a user can link their Vipps and Wordpress accounts and log in using just the app.
   It is written extensible, so that further 'applications' - like WooCommerce - can use this class to implement specific login rules (for errorhandling, user modification, and end-user pages / profiles).

   It implements the login actions using Ajax for simplicity, and stores in the session a cookie value stored also in the browser. Any session that does not have the corresponding browser cookie is invalid.

   Previously, Vipps email-addresses weren't verified, so we required that a user wanting to use Vipps should confirm their email address and the connection. This is now optional,  and is done using the standard Wordpress 'user request' API if the user hasn't logged in yet.  If they are, they can connect directly from their profile page with another 'action' using ContinueWithVipps.

   Furthermore, and really mostly a Woo application, there is an 'synch' action that lets an existing, connected user syncrhronize their user data with Vipps if it has become separate. If this is done,
   the address will be synchronized at each login until the user changes the address specifically in the Worpdress instance.


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
class VippsLogin {
    protected static $instance = null;
    protected static $isactive = null;


    public static function CompanyName() { 
        return __("Vipps MobilePay", 'woo-vipps');
    }

    function __construct() {
    }
    // This is a singleton class; access the single instance with this method. IOK 2019-10-14
    public static function instance()  {
        if (!static::$instance) static::$instance = new VippsLogin();
        return static::$instance;
    }

    // We use this to annotate the users' login session with a a current Vipps session. Not in use now, but can be used to require Vipps logins for instance. IOK 2019-10-14
    protected $currentSid = null;

    static public function is_active() {
        if (static::$isactive !== null) return static::$isactive;
        $settings  = ContinueWithVipps::instance()->settings;
        if (!$settings || empty($settings['clientid']) || empty($settings['clientsecret'])) {
            static::$isactive = 0;
            return 0;
        }
        $options = get_option('vipps_login_settings');
        $usevipps = @$options['use_vipps_login'];
        static::$isactive   = $usevipps;
        return $usevipps;
    }

    public function log ($what,$type='info') {
        ContinueWithVipps::instance()->log($what,$type);
    }

    // The main init hook. We are going to hook into authentication, logout, error handling, the hooks defined by ContinueWithVipps and to the UserRequest handlers
    // for connecting accounts. IOK 2019-10-14
    public function init () {


        if (!static::is_active()) return;
        // Hook into standard auth and logout, but do so after the secure signon bits and so forth.
        add_filter('authenticate', array($this,'authenticate'),50,3); 
        // Can be used to manage sessions later it is hoped.
        add_action('wp_logout', array($this,'wp_logout'),10,3); 
        // Any errors from 'continue_with_vipps_error_login' will be handled here on the wp-login.php screen. IOK 2019-10-14
        add_filter('wp_login_errors', array($this, 'wp_login_errors'), 10, 2);

        // Profile updates for customers. 2019-10-14. Used to connect/disconnect from Vipps. IOK 2019-10-14
        // disconnect now handled separately, this handles other settings like if App usage is required for the user. IOK 2024-08-27
        add_action('personal_options_update',array($this,'profile_update'));
        add_action('edit_user_profile_update',array($this,'profile_update'));
        add_action('user_profile_update_errors', array($this,'user_profile_update_errors'), 10,3);
        add_action('admin_post_profile_disconnect_vipps', array($this,'disconnect_vipps_post_handler'));

        // Action that handles the 'waiting' page - originally, this was the page that will be shown to the user while they confirm their email account.
        // It can however be used for other actions to be done before actually logging a user in, so the filters are kept.
        // On confirmation and reload, the user will be logged in .  This also integrates with 'template_redirect' because of this. IOK 2019-10-14
        add_action('continue_with_vipps_page_login', array($this, 'continue_with_vipps_page_login'), 10, 1);
        add_action('continue_with_vipps_before_page_login', array($this, 'continue_with_vipps_before_page_login') , 10, 1);

        // Ajax code loaded here. IOK 2019-10-14
        add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));

        // Login form button on wp-login.php main screen. IOK 2019-10-14
        add_action('login_form', array($this, 'login_form_continue_with_vipps'));
        add_action('register_form', array($this, 'register_form_continue_with_vipps'));
        add_action( 'login_enqueue_scripts', array($this,'login_enqueue_scripts' ));

        // We provide 'login with vipps / continue with vipps' button shortcodes'. IOK 2019-10-14
        $this->add_shortcodes();

        // Ajax code to get the redirect url to start the login/confirmation process. IOK 2019-10-14
        add_action('wp_ajax_vipps_login_get_link', array($this,'ajax_vipps_login_get_link'));
        add_action('wp_ajax_nopriv_vipps_login_get_link', array($this,'ajax_vipps_login_get_link'));
        add_action('wp_ajax_vipps_confirm_get_link', array($this,'ajax_vipps_confirm_get_link'));
        add_action('wp_ajax_vipps_synch_get_link', array($this,'ajax_vipps_synch_get_link'));

        // Main return handler. This will do all the work neccessary to login a user, register a user, ask for confirmation etc before redirecting to
        // either the users' profile page or to the waiting page for confirmations. IOK 2019-10-14
        add_action('continue_with_vipps_login', array($this, 'continue_with_vipps_login'), 10, 2);
        add_action('continue_with_vipps_error_login', array($this, 'continue_with_vipps_error_login'), 10, 4);

        // Main return handler for 'confirm your account'. Same as the above, except that for this the user is already logged in.
        add_action('continue_with_vipps_confirm', array($this, 'continue_with_vipps_confirm'), 10, 2);
        add_action('continue_with_vipps_error_confirm', array($this, 'continue_with_vipps_error_confirm'), 10, 4);
        add_action('continue_with_vipps_error_wordpress_confirm', array($this, 'continue_with_vipps_error_wordpress_confirm'), 10, 4);

        // And for synching addresses. Again, the user would be logged in.
        add_action('continue_with_vipps_synch', array($this, 'continue_with_vipps_synch'), 10, 2);
        add_action('continue_with_vipps_error_synch', array($this, 'continue_with_vipps_error_synch'), 10, 4);
        add_action('continue_with_vipps_error_wordpress_synch', array($this, 'continue_with_vipps_error_wordpress_synch'), 10, 4);

        // This is for confirming - with Vipps - an already existing user.
        add_action('continue_with_vipps_confirm_login', array($this, 'continue_with_vipps_confirm_login'), 10, 2);
        add_action('continue_with_vipps_error_confirm_login', array($this, 'continue_with_vipps_error_confirm_login'), 10, 4);
    }

    // Scripts used to make the 'login' button work; they use Ajax. IOK 2019-10-14
    public function wp_enqueue_scripts() {
        if (!static::is_active()) return;
        wp_enqueue_script('login-with-vipps',plugins_url('js/login-with-vipps.js',__FILE__),array('jquery'),filemtime(dirname(__FILE__) . "/js/login-with-vipps.js"), 'true');

        $loginconfig = array( 'ajax_url' => admin_url( 'admin-ajax.php' ));

        $loginconfig['lang'] = $this->get_customer_language();

        wp_localize_script('login-with-vipps', 'vippsLoginConfig', $loginconfig);
        wp_enqueue_style('login-with-vipps',plugins_url('css/login-with-vipps.css',__FILE__),array(),filemtime(dirname(__FILE__) . "/css/login-with-vipps.css"), 'all');
        $logo = plugins_url("img/vmp-logo.png", __FILE__);
        wp_add_inline_style('login-with-vipps', ".woocommerce-MyAccount-navigation ul li.woocommerce-MyAccount-navigation-link--vipps a::before { background-image: url('{$logo}'); }");
    }


    public function login_enqueue_scripts() {
        if (!static::is_active()) return;
        $options = get_option('vipps_login_settings');
        if (!$options['login_page']) return;
        wp_enqueue_script('jquery');
        wp_enqueue_script('login-with-vipps',plugins_url('js/login-with-vipps.js',__FILE__),array('jquery'),filemtime(dirname(__FILE__) . "/js/login-with-vipps.js"), 'true');
        wp_localize_script('login-with-vipps', 'vippsLoginConfig', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
        wp_enqueue_style('login-with-vipps',plugins_url('css/login-with-vipps.css',__FILE__),array(),filemtime(dirname(__FILE__) . "/css/login-with-vipps.css"), 'all');
    }


    // This will return to the user an URL that when followed will start the login process. IOK 2019-10-14
    // Default application is 'wordpress', others would be wp-members,woocommerce, bbpress, your own. IOK 2019-10-14
    public function ajax_vipps_login_get_link () {
        //NB We are not using a nonce here - the user has not yet logged in, and the page may be cached. To continue logging in, 
        // the users' browser must retrieve the url from this json value. IOK 2019-10-03
        $application = 'wordpress';
        if (isset($_REQUEST['application'])) {
            $application = sanitize_title($_REQUEST['application']);
        }
        // We need the 'originating page' even when we are doing a POST to ourselves, so use 'raw reverer'. This means that this
        // must use 'safe redirect' as well.
        $referer = wp_get_raw_referer();
        $data = array('referer' => $referer);

        // Allow applications to extend the values added to the session IOK 2021-11-09
        $data = apply_filters('login_with_vipps_login_link_data', $data, $application);

        $url = $this->get_vipps_login_link($application, $data);
        wp_send_json(array('ok'=>1,'url'=>$url,'message'=>'ok'));
        wp_die();
    }

    // This method will create the URL that when redirected to, will start the login process for the given application. It will 
    // also create a browser cookie (secure, no javascript access) and store this in the session for validation. IOK 2019-10-14
    public function get_vipps_login_link($application='wordpress', $data=array()) {
        // We are going to store a random cookie in the browser so we can verify that this
        // calls' session belongs to a single browser. IOK 2019-10-09
        // Incidentally, this will invalidate any current session as well.
        // Also, this *must* be called with POST to ensure you actually get the cookies, at least if you have
        // caching proxies somewhere in your chain.
        $cookie = $this->setBrowserCookie();
        $data['cookie'] = $cookie;
        $data['application'] = $application;
        $action = apply_filters('login_with_vipps_login_action', 'login', $application, $data);
        $url = ContinueWithVipps::getAuthRedirect($action ,$data);
        return $url;
    }

    // This is like for login, except it is for confirming that user login should be connected to Vipps. User is logged in, so we
    // are using a nonce here. IOK 2019-10-14
    public function ajax_vipps_confirm_get_link () {
        check_ajax_referer('vippsconfirmnonce','vippsconfirmnonce',true);
        $application = 'wordpress';
        if (isset($_REQUEST['application'])) {
            $application = sanitize_title($_REQUEST['application']);
        }
        $referer = wp_get_raw_referer();
        $url = $this->get_vipps_confirm_link($application, array('referer'=>$referer));
        wp_send_json(array('ok'=>1,'url'=>$url,'message'=>'ok'));
        wp_die();
    }
    public function get_vipps_confirm_link($application='wordpress', $sessiondata=array()) {
        if (!is_user_logged_in()) return;
        $cookie = $this->setBrowserCookie();
        $sessiondata['cookie'] = $cookie;
        $sessiondata['application'] = $application;
        $sessiondata['userid'] = get_current_user_id();
        $url = ContinueWithVipps::getAuthRedirect('confirm',$sessiondata);
        return $url;
    }

    // And this is for synchronizing already connected accounts. Again, we use a nonce, it will only work for logged-in users. IOK 2019-10-14
    public function ajax_vipps_synch_get_link () {
        check_ajax_referer('vippsconfirmnonce','vippsconfirmnonce',true);

        $application = 'wordpress';
        if (isset($_REQUEST['application'])) {
            $application = sanitize_title($_REQUEST['application']);
        }
        $referer = wp_get_raw_referer();
        $url = $this->get_vipps_synch_link($application, array('referer'=>$referer));
        wp_send_json(array('ok'=>1,'url'=>$url,'message'=>'ok'));
        wp_die();
    }
    //  And this for users that want to synch their address with Vipps
    public function get_vipps_synch_link($application='wordpress', $sessiondata=array()) {
        if (!is_user_logged_in()) return;
        $cookie = $this->setBrowserCookie();
        $sessiondata['cookie'] = $cookie;
        $sessiondata['application'] = $application;
        $sessiondata['userid'] = get_current_user_id();
        $url = ContinueWithVipps::getAuthRedirect('synch',$sessiondata);
        return $url;
    }

    // We need to do this because on earlier versions of WordPress, COOKEPATH can be wrong for multisites. IOK 2021-12-17
    public function getCookiePath() {
        $cookiepath = defined("COOKIEPATH") ? COOKIEPATH : "";
        if (!$cookiepath) {
            $cookiepath = trailingslashit(parse_url(get_option( 'home' ), PHP_URL_PATH));
        }
        return apply_filters('login_with_vipps_cookie_path', $cookiepath);
    }

    // This ensures that a session is valid for the browser we are interacting with, using this cookie as a one-time password. 2019-10-14
    public function setBrowserCookie() {
        $cookie = base64_encode(hash('sha256',random_bytes(256), true));
        $path = $this->getCookiePath();
        $_COOKIE['wordpress_vipps_session_key'] = $cookie;
        setcookie('wordpress_vipps_session_key', $cookie, time() + (2*3600), $path, COOKIE_DOMAIN,true,true);
        return $cookie;
    }
    public function deleteBrowserCookie() {
        unset($_COOKIE['wordpress_vipps_session_key']);
        $path = $this->getCookiePath();
        setcookie('wordpress_vipps_session_key', '', time() - (2*3600), $path, COOKIE_DOMAIN,true,true);
    }
    public function checkBrowserCookie($against) {
        if (!isset($_COOKIE['wordpress_vipps_session_key'])) return false;
        if (empty($against)) return false;
        return ($_COOKIE['wordpress_vipps_session_key'] == $against);
    }

    public function admin_init () {
        // This is for creating a page for admins to manage user confirmations. It's not needed here, so this line is just information. IOK 2019-10-14
        // add_management_page( 'Show user confirmations', 'Show user confirmations!', 'install_plugins', 'vipps_connect_login', array( $this, 'show_confirmations' ), '' );

        add_action('admin_enqueue_scripts', array($this,'admin_enqueue_scripts'), 10, 1);

        // This adds fields indicating if the user is connected to Vipps or not, and allows connecting/disconnecting . IOK 2019-10-14
        if (current_user_can('manage_options')) {
            $uid = isset($_REQUEST['user_id']) ? intval($_REQUEST['user_id']) : 0;
            if ($uid>0 && current_user_can('edit_user', $uid)) {
                add_action( 'edit_user_profile_update', array($this,'profile_update'));
                add_action( 'edit_user_profile', array($this,'show_extra_profile_fields'));
            }
        }
        add_action('show_user_profile', array($this,'show_extra_profile_fields'));

        // Error- and successhandling on the profile page: Add some feedback in standard WP idioms. IOK 2019-10-14
        global $pagenow;
        if ($pagenow == 'profile.php') {
            $userid = get_current_user_id();
            $justconnected = get_user_meta($userid,'_vipps_just_connected',true);
            $justsynched = get_user_meta($userid,'_vipps_just_synched',true);

            list($vippsphone, $vippsid) = $this->get_vipps_account($userid);

            if ($justconnected) {
                delete_user_meta($userid, '_vipps_just_connected');
                $notice = sprintf(__('You are now connected to the %1$s profile <b>%2$s</b>.', 'login-with-vipps'), VippsLogin::CompanyName(), $vippsphone);
                add_action('admin_notices', function() use ($notice) { echo "<div class='notice notice-success notice-vipps is-dismissible'><p>$notice</p></div>"; });
            }
            if ($justsynched) {
                delete_user_meta($userid, '_vipps_just_synched');
                $notice = sprintf(__('You are now synchronized with the %1$s profile <b>%2$s</b>.', 'login-with-vipps'), VippsLogin::CompanyName(), $vippsphone);
                add_action('admin_notices', function() use ($notice) { echo "<div class='notice notice-success notice-vipps is-dismissible'><p>$notice</p></div>"; });
            }
        } 
    }

    // Ajax methods for connecting accounts externalized here. IOK 2019-10-14
    public function admin_enqueue_scripts ($suffix) {
        if ($suffix == 'profile.php') {
            wp_enqueue_script('vipps-login-profile',plugins_url('js/vipps-profile.js',__FILE__),array('jquery'),filemtime(dirname(__FILE__) . "/js/vipps-profile.js"), 'true');
            wp_localize_script('vipps-login-profile', 'vippsLoginProfileConfig', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'vippsconfirmnonce'=>wp_create_nonce('vippsconfirmnonce') ) );
        }
    }

    public function get_login_method() {
        $settings = get_option('vipps_login_settings', array());
        if(!isset($settings['login_method'])) {
            return $this->detect_default_login_method();
        }
        return $settings['login_method'];
    }

    // Detect default payment method based on store location, user locale, currency NT 2023-11-30
    // Modified for Login IOK 2024-04-22
    public function detect_default_login_method() {
        // IOK 2023-12-01 use the main locale instead of the user locale
        $locale = get_locale();
        $settings = get_option('vipps_login_settings', array());
        if ($settings['migrated'] ?? false) {
            // All pre-existing installs are known to use Vipps
            return 'Vipps';
        }
        if ( class_exists( 'WooCommerce' ) ) {
            // Countries object not yet available at this point IOK 2023-12-01
            // $store_location = WC()->countries->get_base_country();
            $store_location=  wc_get_base_location();
            $store_country = $store_location['country'] ?? '';
            $currency = get_woocommerce_currency();

            // If store location, locale, or currency is Norwegian, use Vipps
            if ($store_country== "NO" || preg_match("/.*_NO/", $locale) || $currency == "NOK") {
                return 'Vipps';
            }
        }

        if (preg_match("/.*_NO/", $locale)) {
           return 'Vipps';
        }

        return 'MobilePay';
    }

    // Try to get the current language in the format Vipps wants, one of 'en' and 'no'
    // Follows the same implementation as the main VippsMobilePay plugin
    public function get_customer_language() {
        $user = wp_get_current_user();
        $user_locale = get_user_meta($user->ID, 'locale', true);
        $language = substr($user_locale, 0, 2); 
        if (function_exists('pll_current_language')) {
           $language = pll_current_language('slug');
        } elseif (has_filter('wpml_current_language')){
            $language=apply_filters('wpml_current_language',null);
        } 
        if (! $language) $language = substr(get_bloginfo('language'),0,2);
        if ($language == 'nb' || $language == 'nn') $language = 'no';
        if ($language == 'da') $language = 'dk';
        if (! in_array($language, ['en', 'no', 'dk', 'fi'])) $language = 'en';
        return $language;
     }
     
     
     public function init_form_login_options2() {
        $options = get_option('vipps_login_settings');

        $continuepageoptions = array(
           ''=>__('Create a new page', 'login-with-vipps'),
        );
        $continuepageid = $options['continuepageid'] ?? 0;

        $continuepage = $this->ensure_continue_with_vipps_page();
        if (is_wp_error($continuepage)) {
            $notice = $continuepage->get_error_message();
            add_action('admin_notices', function() use ($notice) { echo "<div class='notice notice-error is-dismissible'><p>$notice</p></div>"; });
        } else {
            $continuepageid = $continuepage->ID;
        }
        foreach(get_pages() as $page) {
            $continuepageoptions[$page->ID] = $page->post_title;
        }

        $roles = array(
            '_all_' => __('Everybody', 'login-with-vipps'),
        );
        foreach(wp_roles()->roles as $role=>$roledata) {
            $roles[$role] = $roledata['name'];
        }

        $fields = array(
            'use_vipps_login' => array(
                'type' => 'checkbox',
                'title' => sprintf(__('Enable Login with %1$s', 'login-with-vipps'), $this->get_login_method()),
                'description' => sprintf(__('Turn Login with %1$s on and off', 'login-with-vipps'), $this->get_login_method()),
                'default' => false,
            ),
            'login_page' => array(
                'type' => 'checkbox',
                'title' => sprintf(__('Add %1$s to login page', 'login-with-vipps'), $this->get_login_method()),
                'description' => sprintf(__('Log in with %1$s on the Wordpress login page', 'login-with-vipps'), $this->get_login_method()),
                'default' => false,
            ),
            'required_roles' => array(
                'type' => 'multicheck',
                'title' => sprintf(__('Require users in these roles to log in with %1$s', 'login-with-vipps'), $this->get_login_method()),
                'description' => sprintf(__('Users in these roles *must* use login with %1$s. You can also require this for a given user on the profile page', 'login-with-vipps'), $this->get_login_method()),
                'options' => $roles,
                'default' => array(),
            ),
            'continuepageid' => array(
                'type' => 'select',
                'title' => sprintf(__('Continue with %1$s page', 'login-with-vipps'), $this->get_login_method()),
                'description' => __('Sometimes, the user may need to confirm their email or answer follow up questions to complete sign in. This page, which you may leave blank, will be used for this purpose. A blank page will have been installed for you when activating the plugin, this is the default page which will be used. Do *not* use any system pages or anything that is being used for other things.', 'login-with-vipps'),
                'options' => $continuepageoptions,
                'default' => 0,
            )
        );

        return array(
            'title' => sprintf(__('Login with %1$s', 'login-with-vipps'), $this->get_login_method()),
            'fields' => $fields,
        );
    }

    // Upon activation, this plugin will create a new page that is used for one thing only: Waiting for the user to confirm that the
    // account they are trying to log into is actually owned by them, or that they control the email address. This code ensures that this page exists. IOK 2019-10-14
    public function activate () {
        $continuepage = $this->ensure_continue_with_vipps_page();
        $continueid = 0;
        if (!is_wp_error($continuepage)) {
            $continueid = $continuepage->ID;
        }
        $default = array('continuepageid'=>$continueid, 'use_vipps_login'=>true,'login_page'=>true,'require_confirmation'=>false);
        add_option('vipps_login_settings',$default,false);
    }

    public static function deactivate () {
        // We don't delete anything however, just in case.
    }

    // Returns the page object of the 'continue with vipps' page, creating it if neccessary. 2019-10-14
    public function ensure_continue_with_vipps_page() {
        $options = get_option('vipps_login_settings');
        $continuepageid = $options['continuepageid'] ?? false;
        if ($continuepageid) {
            $page = get_post($continuepageid);
            if ($page) return $page;
            if (!$page) {
                $options['continuepageid'] = 0;
                update_option('vipps_login_settings', $options);
            }
        }

        // This is the typical case, when the user installs and activates the plugin. We use the users' id as the pages author. 2019-10-14 
        $author = null;
        if (current_user_can('manage_options')) $author = wp_get_current_user();

        // Otherwise, use a random admin as author. 2019-10-14
        if (!$author) {
            $alladmins = get_users(array('role'=>'administrator'));
            if ($alladmins) { 
                $alladmins = array_reverse($alladmins);
                $author = $alladmins[0];
            }
        }
        $authorid = 0;
        if ($author) $authorid = $author->ID;

        $defaultname = sprintf(__('Continue with %1$s page', 'login-with-vipps'), VippsLogin::instance()->get_login_method());

        $pagedata = array('post_title'=>$defaultname, 'post_status'=> 'publish', 'post_author'=>$authorid, 'post_type'=>'page');
        $newid = wp_insert_post($pagedata);
        if (is_wp_error($newid)) {
            return new WP_Error(sprintf(__('Could not find or create the "Continue with %1$s" page.', 'login-with-vipps'), VippsLogin::instance()->get_login_method()) . ": " .  $newid->get_error_message());
        }

        $options['continuepageid'] = $newid;
        update_option('vipps_login_settings', $options);
        return get_post($newid);
    }

    // On the profile page, show extra buttons to connect and disconnect a user with a Vipps account . IOK 2019-10-14
    function show_extra_profile_fields( $user ) {
        $allow_login = true;
        $allow_login = apply_filters('continue_with_vipps_allow_login', $allow_login, $user, array(), array());
        list($vippsphone, $vippsid) = $this->get_vipps_account($user);
        $its_you = (get_current_user_id() == $user->ID);
        $is_admin = current_user_can('manage_options');

        // Use an admin-post URL to disconnect users if we have the capability IOK 2024-08-27
        $disconnect_url = 
             add_query_arg( ['action'=>'profile_disconnect_vipps', 'userid'=>$user->ID],
             wp_nonce_url(admin_url("/admin-post.php"), 'disconnect_vipps', 'disconnect_vipps_nonce'));

        ?>
            <h2 class='vipps-profile-section-header'><?php printf(__('Log in with %1$s', 'login-with-vipps'), VippsLogin::CompanyName()); ?> </h2>
            <?php if ($allow_login): ?>
            <table class="form-table">
            <tr>
            <th><?php printf(__('Use %1$s to login to your account', 'login-with-vipps'), VippsLogin::CompanyName()); ?></th>
            <td>
            <?php if ($vippsphone && $vippsid): ?>
            <?php if ($its_you): ?>
            <p> <?php printf(__('You are connected to the %1$s profile with the phone number <b>%2$s</b>', 'login-with-vipps'), VippsLogin::CompanyName(), esc_html($vippsphone)); ?></p>
            <?php else: ?>
            <p> <?php printf(__('The user is connected to the %1$s profile with the phone number <b>%2$s</b>', 'login-with-vipps'), VippsLogin::CompanyName(), esc_html($vippsphone)); ?></p>
            <?php endif; ?> 
            <p><a href="<?php echo $disconnect_url; ?>" class="button vipps-disconnect" ><?php _e('Unlink account','login-with-vipps'); ?></a></p>
            <span class="description"><?php printf(__('As long as your profile is connected to %1$s, you can log in with %1$s.','login-with-vipps'), VippsLogin::CompanyName()); ?></span>
            <?php else: ?>
            <?php if ($its_you): ?>
            <p> <?php printf(__('You are not connected to any %1$s profile', 'login-with-vipps'), VippsLogin::CompanyName()); ?></p>
            <p><button type="button" onclick="connect_vipps_account('wordpress');return false"; class="button vipps-connect" value="1" name="vipps-connect"><?php _e('Press here to connect with your app','login-with-vipps'); ?></button></p>
            <?php else: ?>
            <p> <?php printf(__('The user is not connected to a %1$s profile.', 'login-with-vipps'), VippsLogin::CompanyName()); ?></p>
            <?php endif; ?> 
            <span class="description"><?php printf(__('You can connect to your %1$s profile if you use the same email address in the %1$s app and on this site.', 'login-with-vipps'), VippsLogin::CompanyName()); ?></span>
            <?php endif; ?>
            </td>
            </tr>
            <?php if ($is_admin): ?>
            <tr>
            <th><?php printf(__('Require this user to confirm their login with %1$s if logging in normally', 'login-with-vipps'), $this->get_login_method()); ?></th>
            <td>
               <input type="hidden" name="_require_vipps_confirm" value=0>

               <input type="radio" name="_require_vipps_confirm" id="_require_vipps_confirm_yes" 
                      <?php if (get_user_meta($user->ID, "_require_vipps_confirm", true)=='yes') echo "checked=checked" ?>
                      value="yes"><label for="_require_vipps_confirm_yes"><?php _e("Yes, require confirmation", 'login-with-vipps'); ?></label><br>
               <input type="radio" name="_require_vipps_confirm" id="_require_vipps_confirm_no" 
                      <?php if (get_user_meta($user->ID, "_require_vipps_confirm", true)=='no') echo "checked=checked" ?>
                      value="no"><label for="_require_vipps_confirm_no"> <?php _e("No, allow login without the app", 'login-with-vipps'); ?></label><br>
               <input type="radio" name="_require_vipps_confirm" id="_require_vipps_confirm_default" 
                      <?php if (!get_user_meta($user->ID, "_require_vipps_confirm", true)) echo "checked=checked" ?>
                      value=""><label for="_require_vipps_confirm_default"> <?php _e("Only if member of restricted groups", 'login-with-vipps'); ?></label><br>
               <span class="description"><?php printf(__('If you check this, this user will not be allowed to log in without confirming this operation with their %1$s app - email addresses must match.','login-with-vipps'), VippsLogin::CompanyName()); ?></span>

       

            </td>
            </tr>
            <?php endif; ?>

            </table>
            <?php else: ?>
            <table class="form-table">
            <tr>
            <th><?php printf(__('Login with %1$s is disabled', 'login-with-vipps'), $this->get_login_method()); ?></th>
            <td>
            <span class="description"><?php printf(__('It is unfortunately not possible for your account to use %1$s to log in to this system due to the site administrators policy.'), $this->get_login_method()); ?></span>
            </td>
            </tr>
            </table>
            <?php endif; ?>

            <?php
    }

    // This runs when the users saves the profile page, which here includes disconnecting from Vipps. IOK 2019-10-14
    // Disconnect moved to disconnect_vipps_post_handler, called from admin-post. IOK 2024-09-27
    function profile_update( $userid ) {
        if (!current_user_can('edit_user',$userid)) return false;

        // Allow admin (only) to set the "require Vipps confirmation field
        if (current_user_can('manage_options') && isset($_POST['_require_vipps_confirm'])) {
           update_user_meta($userid, '_require_vipps_confirm', sanitize_key($_POST['_require_vipps_confirm']));
        }
    }

    // If neccessary, add errors et to the profile update.
    public function user_profile_update_errors($errors,$update,$user) {
        // Not actually neccessary, yet.
    }

    // Disconnect handler. Done using admin-post, but check nonce first. IOK 2019-10-14
    public function disconnect_vipps_post_handler () {
        check_admin_referer('disconnect_vipps', 'disconnect_vipps_nonce');
        $userid = intval($_REQUEST['userid']);
        if (!$userid) wp_die(__('Cannot disconnect', 'login-with-vipps'));
        if (!current_user_can('edit_user',$userid)) wp_die(__('Cannot disconnect', 'login-with-vipps'));

        list($vippsphone, $vippsid) =  VippsLogin::instance()->get_vipps_account($userid);
        VippsLogin::instance()->unmap_phone_to_user(get_user_by('id', $userid));
        VippsLogin::instance()->log(sprintf(__('Unmapping user %2$d from %1$s', 'login-with-vipps'), VippsLogin::instance()->get_login_method(), $userid));

        $notice = sprintf(__('Connection to %1$s profile %2$s <b>removed</b>.', 'login-with-vipps'), VippsLogin::CompanyName(), $phone);
        $continue = ContinueWithVipps::instance();
        $continue->add_admin_notice($notice);
        $continue->store_admin_notices();

        wp_safe_redirect(wp_get_referer());
        exit();
    }

    // At this point, this method does not do anything. However, it is intended to 
    // allow this plugin to *require* that Vipps be used for connected users. This will then
    // allow this plugin to deny bruteforce attacks against these accounts. IOK 2019-10-14
    public function authenticate ($user, $username, $password) {
        if (!$user) return $user;
        if (! ($user instanceof WP_User)) return $user;

        // This is the Vipps login session ID, if it is missing, we're not logging in via Vipps 2020-12-21
        if (!$this->currentSid) $this->currentSid = array($user->ID, null);
        if ($this->currentSid) {
            list ($userid, $sid) = $this->currentSid; 
            if (!$sid) {
                $options = get_option('vipps_login_settings');
                $option_roles= isset($options['required_roles']) ? $options['required_roles'] : [];
                if (!is_array($option_roles)) $option_roles=array();

                $needs_verification = false;
                $needs_verification_setting = get_user_meta($userid,'_require_vipps_confirm',true);

                // This user has been marked as requiring verification by Vipps before login 
                if (!$needs_verification && $needs_verification_setting == 'yes') $needs_verification=true;

                // Unless an exception has been made for this user, role rules apply
                if ($needs_verification_setting!= 'no' && !$needs_verification) {
                    // Everybody should be verified
                    if (!$needs_verification && isset($option_roles['_all_']) && $option_roles['_all_']) {
                        $needs_verification = true;
                    }
                    // Some roles need verification, and this user is in one of them
                    if (!$needs_verification && !empty($option_roles)) {
                        $required_roles=array_keys($option_roles);
                        if (!empty(array_intersect($user->roles, $required_roles))) {
                            $needs_verification=true;
                        }
                    }
                }

                // A final filter
                $needs_verification = apply_filters('login_with_vipps_user_needs_verification', $needs_verification, $user);


                if ($needs_verification) {
                    $referer = wp_get_raw_referer();
                    $url = ContinueWithVipps::getAuthRedirect('confirm_login',array('uid'=>$userid,'origin'=>$referer));
                    wp_redirect($url);
                    exit();
                }
            }
        }
        return $user;
    }

    // Ideally, we would interact with Vipps here, but these parts of the API is not yet implemented. IOK 2019-10-14
    public function wp_logout () {
        $user = wp_get_current_user();
    }

    // For convenience, some shortcodes that output the same buttons added to the login page. IOK 2019-10-14
    public function add_shortcodes() {
        add_shortcode('login-with-vipps', array($this, 'log_in_with_vipps_shortcode'));
        add_shortcode('log-in-with-vipps', array($this, 'log_in_with_vipps_shortcode'));
        add_shortcode('continue-with-vipps', array($this, 'continue_with_vipps_shortcode'));
    }
    public function log_in_with_vipps_shortcode($atts, $content, $tag) {
        if (!is_array($atts)) $atts = array();
        if (!isset($atts['text'])) $atts['text'] = __('Log in with', 'login-with-vipps');
        return $this->continue_with_vipps_shortcode($atts,$content,$tag);
    }
    public function continue_with_vipps_shortcode($atts,$content,$tag) {
        $args = shortcode_atts(array('application'=>'wordpress', 'text'=>__('Continue with', 'login-with-vipps')), $atts);
        $text = esc_html($args['text']);
        $application = $args['application'];
        ob_start();
        ?> 
            <span class='continue-with-vipps-wrapper inline'>
            <?php $this->login_button_html($text, $application); ?>
            </span>
            <?php
        return ob_get_clean();
    }

    // The login-button on the front page. Moved up in front of the main form using javascript. IOK 2019-10-14
    public function login_form_continue_with_vipps () {
        $options = get_option('vipps_login_settings');
        if (!$options['login_page']) return;
        $this->wp_login_button(__('Log in with', 'login-with-vipps'));
        $this->move_continue_button_over_login_form();
    }
    public function register_form_continue_with_vipps () {
        $options = get_option('vipps_login_settings');
        if (!$options['login_page']) return;
        $this->wp_login_button(__('Continue with', 'login-with-vipps'));
        $this->move_continue_button_over_login_form();
    }
    public function wp_login_button($text, $application='wordpress') {
        ?>
            <div id='continue-with-vipps-wrapper' class='continue-with-vipps-wrapper'>
            <?php $this->login_button_html($text, $application); ?>
            </div>
            <?php 
    }

    // The HTML for this button. IOK 2019-10-14
    public function login_button_html($text, $application) {
        $login_method = $this->get_login_method();
        $logo = $this->get_transparent_logo();
        $bg = $this->get_background_class();
        ob_start();
        ?>
            <a href='javascript:login_with_vipps("<?php echo $application; ?>");' class="button vipps-orange vipps-button continue-with-vipps <?php echo $bg;?>" title="<?php echo sprintf("%s %s", $text, $login_method); ?>"><?php echo $text;?> <img
            alt="<?php printf(__('Log in without password using %1$s', 'login-with-vipps'), $login_method); ?>" src="<?php echo $logo; ?>">!</a>
            <?php
        echo apply_filters('continue_with_vipps_login_button_html', ob_get_clean(), $application, $text);
    }


    // The logo for the login button, depending on the login method.
    public function get_transparent_logo() {
        $method = $this->get_login_method();
        if ($method == 'MobilePay') {
            return plugins_url('img/mobilepay_logo_negativ_rgb_transparent.png',__FILE__);
        }
        if ($method == 'Vipps') {
            return plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
        }
    }

    // The background for the login button, depending on the login method.
    public function get_background_class() {
        $method = $this->get_login_method();
        if ($method == 'MobilePay') {
            return 'mobilepay-background';
        }
        if ($method == 'Vipps') {
            return 'vipps-background';
        }
    }

    // The login form does not have any action that runs right before the form, where we want to be. So we cheat, rewriting the page using javascript. IOK 2019-10-14
    public function move_continue_button_over_login_form() {
        ?>
            <script>
            window.onload = function () {
                var me =  document.getElementById('continue-with-vipps-wrapper');
                var theform = document.getElementById('loginform'); 
                if (!theform) theform = document.getElementById('registerform'); 
                if (theform) {
                    var tops = theform.parentNode;
                    tops.insertBefore(me, theform); 
                }
                me.style.display = 'block';
            }
        </script>
            <?php
            return true;
    }

    // The hook into template_redirect is to handle the Vipps Confirmation waiting page. If the user confirms, and this page is reloaded, we want to log in. Also
    // we will allow applications to modify this behaviour. IOK 2019-10-14
    public function template_redirect () {
        $continuepage = $this->ensure_continue_with_vipps_page();
        if ($continuepage && !is_wp_error($continuepage) && is_page($continuepage->ID)) {

            $state = sanitize_text_field(@$_REQUEST['state']);
            $sessionkey = '';
            $action = '';
            if ($state) list($action,$sessionkey) = explode("::",$state);
            do_action('continue_with_vipps_before_page_' . $action, $sessionkey);

            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Dec 1990 16:00:00 GMT');
            add_filter('the_content', function ($content) {
                    return $this->continue_with_vipps_waiting_page($content);
                    });
        }
    }

    // If for some reason we need to go to the waiting page (e.g user is not confirmed). IOK 2019-10-1e
    public function redirect_to_waiting_page($forwhat,$session) {
        $state = $forwhat . "::" . $session->sessionkey;
        $continuepage = $this->ensure_continue_with_vipps_page();
        if (is_wp_error($continuepage)) {
            wp_die(sprintf(__('Cannot redirect to %1$s waiting page as it doesn\'t exist. If you just tried to log in, check your email.', 'login-with-vipps'), VippsLogin::CompanyName()));
        }
        $waiturl = get_permalink($continuepage);
        $redir = add_query_arg(array('state'=>urlencode($state)), $waiturl);
        wp_safe_redirect($redir, 302, 'Vipps');
        exit();
    }

    // This is actually run as the 'the_content' filter for the waiting page, replacing the contents of whatever page was used with this. IOK 2019-10-14
    public  function continue_with_vipps_waiting_page($content) {
        $state = sanitize_text_field(@$_REQUEST['state']);
        $sessionkey = '';
        $action = '';
        if ($state) list($action,$sessionkey) = explode("::",$state);
        $session = VippsSession::get($sessionkey);

        if (!$session  || !$this->checkBrowserCookie($session['cookie'])) {
            $message = sprintf(__('This page is used to handle your requests when continuing from %1$s and further action is required to complete your task. But in this case, it doesn\'t seem to be an open session', 'login-with-vipps'), VippsLogin::CompanyName());  
            $message = apply_filters('continue_with_vipps_waiting_page_expired_session_' . $action, $message,  $session);
            if ($session) $session->destroy();
            return $message;
        }
        ob_start();
        do_action('continue_with_vipps_page_' . $action, $session);
        return ob_get_clean();
    }

    // This happens right before the waiting page, before output is started. If you need to use the "Before login" waiting page, you can check if your requirements (ie, terms acceptance, email confimation etc)
    // are now ok, and if so, you could go ahead and use the still active session to log your user in etc. Remember to destroy the session and preferably delete the browser cookie too.
    public function continue_with_vipps_before_page_login($sessionkey) {
        if (!$sessionkey) return;
        $session = VippsSession::get($sessionkey);
        if (!$session  || !$this->checkBrowserCookie($session['cookie'])) return;
        // Check your session here if you are ready to login the/any user etc.
        do_action('vipps_login_before_waiting_page_actions', $session);
        return false;
    }

    // This is a "the_content" filter added to the waiting page for login sessions.  Typically, you would add something to the session (still alive here)
    // and dispatch on that. An example is provided below; also we add a simple filter to extend functionality. You would here ask your users to confirm something or select something before actually logging them in.
    public function continue_with_vipps_page_login($session)  {
        $handled = false;
        if ($session['subaction'] == 'confirm_your_account') {
            // No longer used, but kept as documentation as to how one could use a 'waiting page' like this. You would a) not delete the session b) set some flag in it,
            //  redirect to the "continue with vipps" waiting page, and do your output here. IOK 2022-04-01
            print "<p>";
            print "</p>";
            $handled = true;
        } else {
            do_action('vipps_login_waiting_page_actions', $session);
            $handled = apply_filters('vipps_login_waiting_page_handled', $handled,  $session);
        }
        if (!$handled) { 
            $msg = __("Welcome! If you see this page, an really unexpected error has occur. Unfortunately, we can't do better than to send you to the <a href='%s'>login page</a>", 'login-with-vipps');
            printf($msg, wp_login_url()); 
        }
    }

    // This is used in a hook when redirecing to wp-login.php. It will format and output any errors that occured during login. IOK 2019-10-14
    // In this situation we use a fresh VippsSession to carry this information.
    public function wp_login_errors ($errors, $redirect_to) {
        $session = array();
        $state = sanitize_text_field(@$_REQUEST['vippsstate']);
        $errorcode = sanitize_text_field(@$_REQUEST['vippserror']);
        if ($state) {
            $session = VippsSession::get($state);
        }
        if (!$session) return $errors;
        if (isset($session['error'])) {
            $desc = __($session['error'],'login-with-vipps');
            if (isset($session['errordesc'])) {
                $desc = __($session['errordesc'],'login-with-vipps');
            }
            $errors->add($session['error'], $desc);
        }
        if ($session) $session->destroy();
        return $errors;
    }


    // This is an action handler for all errors for the action 'login'. We format the errors, create a new session, store them there, then redirect to the login URL.
    // Before any of this, we parametrize everything with the passed 'application' - since this is handled completely different when logging in via Woocommerce for instance. IOK 2019-10-14
    // The last parameter, 'sessiondata' is not a live session. It is an Array, or any object that can be accessed as such. If you need to 
    // create a new session here, you should not try to pass it as the new sessions contens - create a new array instead. IOK 2019-10-08
    public function continue_with_vipps_error_login($error,$errordesc,$error_hint='',$sessiondata=array()) {
        $redir = wp_login_url();
        $app = sanitize_title(($sessiondata && isset($sessiondata['application'])) ? $sessiondata['application'] : 'wordpress');
        $referer = ($sessiondata && isset($sessiondata['referer'])) ? $sessiondata['referer'] : '';

        // Override error page redirect for your application. No access to the possible new session. IOK 2019-10-08
        $redir = apply_filters('continue_with_vipps_error_login_redirect', $redir, $error, $sessiondata);
        $redir = apply_filters("continue_with_vipps_error_{$app}_login_redirect", $redir, $error, $sessiondata);

        // Only create a session if your application needs it, to avoid getting messy URLs. IOK 2019-10-14
        $createSession = true;
        $createSession = apply_filters("continue_with_vipps_error_{$app}_login_create_session", $createSession, $sessiondata);

        $continue = ContinueWithVipps::instance();
        $session = $createSession ? VippsSession::create(array('application'=>$app, 'error'=>$error,'errordesc'=>$errordesc,'error_hint'=>$error_hint,'action'=>'login','referer'=>$referer)) : null;

        // Final chance for tweaking error messages
        $errordesc = apply_filters('continue_with_vipps_login_errordescription', $errordesc, $error, $sessiondata, $app); 

        // This would be for an application to extend the session if needed IOK 2019-10-08 
        do_action("continue_with_vipps_error_{$app}_login", $error, $errordesc, $error_hint, $session);


        if ($createSession) $redir = add_query_arg(array('vippsstate'=>urlencode($session->sessionkey), 'vippserror'=>urlencode($error)), $redir);
        wp_safe_redirect($redir);
        exit();
    }


    // This function will login your user when appropriate (ie, after 'authenticate' has run and everything is good).
    // NB: 'authenticate' may well redirect to another page, and then back here. Therefore the session will be active right until we
    // can redirect to the final page ('login_redirect').  IOK 2019-10-14
    // This page is also parametrized on the passed application so that for instance Woo can update addressses and so forth. IOK 2019-10-14
    protected function actually_login_user($user,$sid=null,$session=null) {
        // Note our session ID. This also will indicate that we have logged in via Vipps. IOK 2020-12-21
        $this->currentSid = array($user->ID, $sid);

        // Then, ensure that we interact properly with MFA stuff and so forth so other plugins can invalidate the login
        $user = apply_filters('authenticate', $user, '', '');
        if (is_wp_error($user)) {
            $error = $user;
            $this->continue_with_vipps_error_login($error->get_error_code(),$error->get_error_message(),'', $session);
            exit();
        }


        $app = sanitize_title(($session && isset($session['application'])) ? $session['application'] : 'wordpress');
        do_action('continue_with_vipps_before_user_login', $user, $session);
        do_action("continue_with_vipps_before_{$app}_user_login", $user, $session);

        add_filter('attach_session_information', function ($data,$user_id) use ($sid) {
                $data['vippssession'] = $sid;
                return $data;
                }, 10, 2);

        $remember = apply_filters('login_with_vipps_remember_user', false, $user, $data);
        wp_set_auth_cookie($user->ID, $remember);
        wp_set_current_user($user->ID,$user->user_login); // 'secure'
        do_action('wp_login', $user->user_login, $user);
        $profile = get_edit_user_link($user->ID);

        do_action('continue_with_vipps_before_login_redirect', $user, $session);
        do_action("continue_with_vipps_before_{$app}_login_redirect", $user, $session);

        $redir = apply_filters('login_redirect', $profile,$profile, $user);
        if($session) $session->destroy();
        $this->deleteBrowserCookie();
        wp_safe_redirect($redir, 302, 'Vipps');
        exit();
    }

    // Standard method of finding "the" user mapped to this phone number. The table in principle supports 
    // 1-n mappings, but we don't support that out of the box, as that would require user interaction on login.
    // The 'sub' is unique per merchant, so we require that too.
    public function get_user_by_vipps_creds($phone,$sub) {
        global $wpdb;
         $tablename2  = $wpdb->prefix . 'vipps_login_users';
         $q = $wpdb->prepare("SELECT * FROM `{$tablename2}` WHERE vippsphone=%s AND vippsid=%s ORDER BY id DESC LIMIT 1", $phone, $sub);
         $result = $wpdb->get_row($q, ARRAY_A);
         if (!$result) return null;
         $user = get_user_by('id', $result['userid']);
         if (!is_wp_error($user)) return $user;
         return null;
    }

    // Get the account (currently the only one) mapped to a Vipps account by user id
    public function get_vipps_account($user=null) {
        $userid = 0;
        if (is_int($user)) {
            $userid = $user;
        } else if (! $user) {
            $userid = get_current_user_id();
        } else {
            $userid = $user->ID;
        }
        if (!$userid) {
            return array(null, null);
        }
        global $wpdb;
        $tablename2  = $wpdb->prefix . 'vipps_login_users';
        $q = $wpdb->prepare("SELECT vippsphone, vippsid  FROM `{$tablename2}` WHERE userid=%d ORDER BY id DESC LIMIT 1", $userid);
        $result = $wpdb->get_row($q, ARRAY_A);
        if (!empty($result)) {
            return array($result['vippsphone'], $result['vippsid']);
        }
        // Try to see if this user *is* mapped, but in the old way. To be deleted later. IOK 2022-04-04
        $phone = get_user_meta($userid, '_vipps_phone',true);
        $sub = get_user_meta($userid, '_vipps_id',true);
        if ($phone && $sub) {
            $this->map_phone_to_user($phone, $sub, get_user_by('id', $userid));
            return array($phone, $sub);
        }
        return array(null, null);
    }

    // This maintains the mapping table from Vipps phones to users, which is used for logins once the user is "connected". Before this, you will 
    // either need to be logged in (and connect), or the users' verified email address will be used. IOK 2022-04-01
    public function map_phone_to_user($phone, $sub, $user) {
        global $wpdb;
        $tablename2  = $wpdb->prefix . 'vipps_login_users';

        // The uniqueness constraint here is for phone x userid, not just phone, so we *can* actually map many-to-many here. But standardly, we won't (see below).
        $q = $wpdb->prepare("INSERT INTO `{$tablename2}` (vippsphone, vippsid, userid) VALUES (%s, %s, %d) ON DUPLICATE KEY UPDATE vippsid=VALUES(vippsid)", $phone, $sub, $user->ID);
        $ok = $wpdb->query($q);
        if ($ok === false) return;

        // We are probably going to delete these after a while as they duplicate the info in the above table. Keep the updating for now just in case these are in use
        // by developers, but do not use them in the login process. IOK 2022-04-04
        update_user_meta($user->ID,'_vipps_phone',$phone);
        update_user_meta($user->ID,'_vipps_id',$sub);
 
        //  This is for future reference, allow filters to stop us deleting the *other* linkages so that one Vipps account
        // *could* be used to log in to several accounts.
        if (! apply_filters('login_with_vipps_allow_multiple_acount_binding', false)) {

           $others = $wpdb->prepare("SELECT userid FROM `{$tablename2}` WHERE vippsphone = %s AND userid != %d", $phone, $user->ID);
           // Handle the usermeta variables, temporarily. We'll delete these at some point in the future. IOK 2022-04-04
           if (is_array($others)) {
               foreach($others as $entry) {
                  $otherid = $entry['userid'];
                  delete_user_meta($otherid,'_vipps_phone');
                  delete_user_meta($otherid,'_vipps_id');
               }
           }
           $delete = $wpdb->prepare("DELETE FROM {$tablename2} WHERE vippsphone  = %s AND userid != %d", $phone, $user->ID);
           $wpdb->query($delete);
        }

        return;
    }
    // In reverse. Current version unmaps every connected account, because we only allow one, but we may allow other configurations in the future. IOK 2022-04-04
    public function unmap_phone_to_user($user, $phone=null, $sub=null) {
            global $wpdb;
            $tablename2  = $wpdb->prefix . 'vipps_login_users';
            $delete = null;
            if ($phone && $sub) {
                $delete = $wpdb->prepare("DELETE FROM {$tablename2} WHERE vippsphone = %s AND userid = %d", $phone, $user->ID);
            } else {
                $delete = $wpdb->prepare("DELETE FROM {$tablename2} WHERE userid = %d", $user->ID);
            }
            if ($delete) {
               $wpdb->query($delete);
            }
            delete_user_meta($user->ID,'_vipps_phone');
            delete_user_meta($user->ID,'_vipps_id');
    }

    // Main login handler action! This should redirect to either a success page (the profile page as default) or it should call the error handler.
    // It will log in a new user if a) there is no existing user with that email, and registration is allowed or b) the user is connected to the Vipps account using
    // the user_meta values. If not, then a confirmation message will be sent to the user. IOK 2019.
    public function continue_with_vipps_login($userinfo,$session) {
        if (!$userinfo) {
            $this->deleteBrowserCookie();
            if ($session) $session->destroy();
            $loginurl = wp_login_url() ;
            wp_safe_redirect($loginurl);
            exit();
        }
        $email = $userinfo['email'];
        $name = $userinfo['name'];
        $username = sanitize_user($email);
        $lastname = $userinfo['family_name'];
        $firstname =  $userinfo['given_name'];
        $phone =  $userinfo['phone_number'];
        $sub =  $userinfo['sub'];

        // $sid=  $userinfo['sid'];
        $sid = 'no_longer_relevant'; // IOK SID no longer avaiable from $userinfo.

        // First, see if we have this user already mapped:
        $mapped = false;
        $user = $this->get_user_by_vipps_creds($phone, $sub);
        if ($user) {
            $mapped = true;
        } else  {
            // Else, use  the verified email address to retrieve the user
            $mapped = false;
            if (intval($userinfo['email_verified'])) {
                $user = get_user_by('email',$email); 
            }
        } 
        // Allow a filter to find the user for special applications (phone number as username etc etc)
        $user = apply_filters( 'login_with_vipps_authenticate_user', $user, $userinfo, $session); 

        // Login is parametrized by 'application' stored in the session. Will be 'wordpress', 'woocommerce' etc. IOK 2019-10-14
        $app = sanitize_title(($session && isset($session['application'])) ? $session['application'] : 'wordpress');

        // MFA plugins may actually redirect here again, in which case we will now be logged in, and we can just redirect. Destroy session first of course. IOK 2019-10-14
        if ($user && (is_user_logged_in() == $user)) {
            $profile = get_edit_user_link($user->ID);
            do_action('continue_with_vipps_before_login_redirect', $user, $session);
            do_action("continue_with_vipps_before_{$app}_login_redirect", $user, $session);
            $redir = apply_filters('login_redirect', $profile,$profile, $user);
            if($session) $session->destroy();
            wp_safe_redirect($redir, 302, 'Vipps');
            exit();
        }

        // If not we must now check that the browser is actually allowed to do this thing - if the user has no cookie, it can't log in. IOK 2019-10-14
        if (!isset($session['cookie']) || !$this->checkBrowserCookie($session['cookie'])){
            // The user doesn't have a valid cookie for this session in their browser.
            // Leave the browser cookie for debugging. IOK 2019-10-14
            if ($session) $session->destroy();
            $this->continue_with_vipps_error_login('invalid_session', __("Your login session is missing. Ensure that you are accessing this site on a secure link (using HTTPS). Also, ensure that you are not blocking cookies – you will need those to log in.", 'login-with-vipps'), '', $session);
        }


        // Check if we allow user registrations
        $can_register = apply_filters('option_users_can_register', get_option('users_can_register'));
        $can_register = apply_filters('continue_with_vipps_users_can_register', $can_register, $userinfo, $session);
        $can_register = apply_filters("continue_with_vipps_{$app}_users_can_register", $can_register, $userinfo, $session);

        if (!$user && !$can_register) {
            if($session) $session->destroy();
            $this->deleteBrowserCookie();
 
            $msg = __('Could not find any user with your registered email. Cannot log in.', 'login-with-vipps');
            $msg = apply_filters('login_with_vipps_invalid_user_message', $msg, $userinfo, $session); 

            $this->continue_with_vipps_error_login('unknown_user', $msg, '', $session);
            exit();
        }

        // Here we don't have a user, but we are allowed to register, so let's do that
        if (!$user) {
            $pass = wp_generate_password( 32, true);

            // Fix username here so it's unique, then allow applications to change it
            $newusername = apply_filters('continue_with_vipps_create_username', $username, $userinfo,$session);
            $newusername = apply_filters("continue_with_vipps_{$app}_create_username", $username, $userinfo,$session);
            $user_id = wp_create_user( $newusername, $pass, $email);
            if (is_wp_error($user_id)) {
                if($session) $session->destroy();
                $this->deleteBrowserCookie();
                $this->continue_with_vipps_error_login('unknown_user', sprintf(__('Could not create a new user - an error occured: %s', 'login-with-vipps'), esc_html($user_id->get_error_message())), '', $session);
                exit();
            }

            $userdata = array('ID'=>$user_id, 'user_nicename'=>$name, 'display_name'=>"$firstname $lastname",
                    'nickname'=>$firstname, 'first_name'=>$firstname, 'last_name'=>$lastname,
                    'user_registered'=>date('Y-m-d H:i:s'));

            // Allow applications to modify this, or they can use the hook below IOK 2019-10-14
            $userdata = apply_filters('continue_with_vipps_create_userdata', $userdata, $userinfo,$session);
            $userdata = apply_filters("continue_with_vipps_{$app}_create_userdata", $userdata, $userinfo,$session);

            wp_update_user($userdata);

            $this->map_phone_to_user($phone, $sub, $user); # This will make logging in use the *phone* for the Vipps account instead of the account from now on.
            update_user_meta($user_id, '_vipps_just_connected', 1);
            // This is currently mostly for Woo, but in general: User has no address, so please update addresses when logging in. IOK 2019-10-25
            update_user_meta($user_id,'_vipps_synchronize_addresses', 1);
            $this->log(sprintf(__('%1$s user with phone %2$s just connected to account %3$d during account creation', 'login-with-vipps'), VippsLogin::CompanyName(), $phone, $user_id));

            $user = get_user_by('id', $user_id);

            // Unfortunately we need this to do the WooCommerce calls correctly that send emails after an account has been created.
            $session->set('created_pw', $pass); 
            do_action('continue_with_vipps_after_create_user', $user, $session);
            do_action("continue_with_vipps_after_create_{$app}_user", $user, $session);
            $session->set('created_pw', null);

            $this->actually_login_user($user,$sid,$session);
            exit();
        } 

        // Allow applications to allow or deny logins for a given user (e.g. to  disallow admin accounts or simila IOK 2019-10-14r)
        $allow_login = true;
        $allow_login = apply_filters('continue_with_vipps_allow_login', $allow_login, $user, $userinfo, $session);
        $allow_login= apply_filters("continue_with_vipps_{$app}_allow_login", $allow_login, $user,$userinfo,$session);

        if (!$allow_login) {
            if($session) $session->destroy();
            $this->deleteBrowserCookie();
            $this->continue_with_vipps_error_login('login_disallowed', sprintf(__('It is unfortunately not allowed for your account to log-in using %1$s', 'login-with-vipps'), VippsLogin::CompanyName()), '', $session);
            exit();
        }

        // Now, if we were previously mapped, we can log in. Call the "map" function anyway in case we need to do some cleanup (we do, at first).
        if ($mapped) {
            $this->map_phone_to_user($phone, $sub, $user); 
            $this->actually_login_user($user,$sid,$session);
            exit();
        }

        // We have a user not connected to the Vipps account, but with the same email. However, Vipps accounts are now verified, so unless we explicitly want confirmation,
        // we'll just connect the accounts and log right in. IOK 2020-05-27
        // IOK 2021-05-27 No longer in effect as the "user request" API is being limited at WP, and no longer required
        // as Vipps does confirm email addresses.
        // We'll leave a filter in the short term to allow any users depending on this to find another solution.
        $require_confirmation = apply_filters('login_with_vipps_require_email_confirmation', false);
        if (!$require_confirmation) {
            $this->map_phone_to_user($phone, $sub, $user); 
            update_user_meta($user->ID,'_vipps_just_connected', 1);
            $this->log(sprintf(__('%1$s user with phone %2$s just connected to account %3$d during login', 'login-with-vipps'), VippsLogin::CompanyName(), $phone, $user->ID));
            $this->actually_login_user($user,$sid,$session);
            exit();
        }

        // This branch used to contain a system for having the user confirm their email address as sent by Vipps, which is no longer relevant as Vipps does this.
        // It is removed because the user request api is being limited by WP so we can't assume it will continue to work without using internal APIs. so we'll just replace this with a filter (for admins that want to handle this 
        // in other ways) and an error message.
        if($session) $session->destroy();
        $this->deleteBrowserCookie();
        $handled = apply_filters('login_with_vipps_handle_email_confirmation', false, $user, $session);
        if (!$handled) {
            $this->continue_with_vipps_error_login('login_disallowed', sprintf(__('You need to confirm your email account before Login with %1$s can be used. Login to your account normally and connect with %1$s on your user page, or contact the site admin', 'login-with-vipps'), $this->get_login_method()), '', $session);
        }
        exit();
    }

    // Errorhandling for confirming account connection. This will happen on the profile page, so it is a bit easier. IOK 2019-10-14
    public function continue_with_vipps_error_confirm($error,$errordesc,$error_hint='',$sessiondata=array()) {
        $userid = get_current_user_id();
        if (!$userid) wp_die(__('You must be logged in to confirm your account', 'login-with-vipps'));
        $redir = get_edit_user_link($userid);
        $app = sanitize_title(($sessiondata && isset($sessiondata['application'])) ? $sessiondata['application'] : 'wordpress');
        $referer = ($sessiondata && isset($sessiondata['referer'])) ? $sessiondata['referer'] : '';

        // Override error page redirect for your application. No access to the possible new session. IOK 2019-10-08
        $redir = apply_filters('continue_with_vipps_error_confirm_redirect', $redir, $error, $sessiondata);
        $redir = apply_filters("continue_with_vipps_error_{$app}_confirm_redirect", $redir, $error, $sessiondata);

        do_action("continue_with_vipps_error_{$app}_confirm", $error, $errordesc, $error_hint, $sessiondata);

        wp_safe_redirect($redir);
        exit();
    }

    // This stores the error-messages temporarily in a transient keyed with the users' cookie. They can then be displayed on the profile page after reloads. IOK 2019-10-14
    public function continue_with_vipps_error_wordpress_confirm ($error, $errordesc, $errorhint, $sessiondata) {
        // Cannot  use the 'store_admin_notices' of ContinueWithVipps here, because it breaks Woocommerce which will not have been loaded yet - so we inline it. IOK 2019-10-14 
        $cookie = sanitize_text_field(@$_COOKIE[LOGGED_IN_COOKIE]);
        if (!$cookie) return;
        $cookiehash =  hash('sha256',$cookie,false);
        $notices = "<div class='notice notice-error is-dismissible'><p>" . sprintf(__('Could not connect to your %1$s profile: ', 'login-with-vipps'), VippsLogin::CompanyName()) . esc_html($errordesc) . "<p></div>";
        set_transient('_vipps_login_save_admin_notices_' . $cookiehash,$notices, 5*60);
    }

    // Main handler for confirming your accounts connection with Vipps. IOK 2019-10-14.
    public function continue_with_vipps_confirm($userinfo,$session) {
        if (!is_user_logged_in()) {
            $this->deleteBrowserCookie();
            if ($session) $session->destroy();
            wp_die(__('This can only be called when logged in', 'login-with-vipps'));
        }
        $app = sanitize_title(($session && isset($session['application'])) ? $session['application'] : 'wordpress');

        $userid = get_current_user_id();
        $profile = get_edit_user_link($userid);
        $redir = apply_filters('continue_with_vipps_confirm_redirect', $profile, $userid, $session);
        $redir = apply_filters("continue_with_vipps_{$app}_confirm_redirect", $redir , $userid, $session);

        if (!$userinfo) {
            $this->deleteBrowserCookie();
            if ($session) $session->destroy();
            wp_safe_redirect($redir);
            exit();
        }

        $email = $userinfo['email'];
        $phone =  sanitize_text_field($userinfo['phone_number']);
        $sub =  sanitize_text_field($userinfo['sub']);
        // $sid=  $userinfo['sid'];
        $sid = 'no_longer_relevant'; // IOK 2021-03-23 SID no longer avaiable from $userinfo.
        $user = get_user_by('id', $userid);

        // By default we will only allow 'new' connections where the verified Vipps email address is the same
        // as the account email. This is to be completely sure this can't be gamed. We may relieve this constraint
        // in time, and we allow a filter to change the constraint for developers with specific needs. IOK 2022-04-04
        $ok = $user->user_email == $email; 
        $ok = apply_filters('login_with_vipps_allow_connection', $ok, $user, $userinfo);
        if (!$ok) {
            if($session) $session->destroy();
            $this->deleteBrowserCookie();
            $sorrymessage = apply_filters('login_with_vipps_cannot_connect_message', sprintf(__('Unfortunately, you cannot connect to this %1$s-profile: The email addresses are not the same.', 'login-with-vipps'), VippsLogin::CompanyName()), $user, $userinfo);
            $this->continue_with_vipps_error_confirm('wrong_user', $sorrymessage, '', $session);
            exit();
        }
        // Actually connect user to phone/sub here
        $this->map_phone_to_user($phone, $sub, $user);
        update_user_meta($userid, '_vipps_just_connected', 1);
        $this->log(sprintf(__('%1$s user with phone %2$s just connected to account %1$d', 'login-with-vipps'), VippsLogin::CompanyName(), $phone, $userid));

        do_action("continue_with_vipps_{$app}_confirm_before_redirect", $userid, $userinfo, $session);

        $this->deleteBrowserCookie();
        if ($session) $session->destroy();
        wp_safe_redirect($redir);
        exit();

    }

    // This handler runs when normal logins need to be confirmed with Vipps. It is *only* called from "authenticate" after the user is verified, so we know that the userid in the
    // session is good. Therefore we will just log right in.  If there are other MFA applications active, they may cause an interesting loop at this point, so probably don't do that.
    //  IOK 2019-10-14.
    public function continue_with_vipps_confirm_login ($userinfo,$session) {

        $userid = $session['uid'];
        $user = new WP_User($userid);
        $referer = $session['referer'];
        if (!$userinfo) {
            $this->deleteBrowserCookie();
            if ($session) $session->destroy();
            $msg = __('Not a valid user', 'login-with-vipps');
            wp_die($msg);
            exit();
        }
        if (!$user || is_wp_error($user)) {
            $this->deleteBrowserCookie();
            if ($session) $session->destroy();
            $msg = __('Not a valid user', 'login-with-vipps');
            if (is_wp_error($user)) $msg .= "<br>" . $user->get_error_message();
            wp_die($msg);
        }

        $email = $userinfo['email'];
        // $sid=  $userinfo['sid'];
        $sid = 'no_longer_relevant'; // IOK 2021-03-23 SID no longer avaiable from $userinfo.
        $phone =  $userinfo['phone_number'];
        $sub =  $userinfo['sub'];

        // First, see if we have this user already mapped
        $mapped = false;
        $verifieduser = $this->get_user_by_vipps_creds($phone, $sub);
        if ($verifieduser) {
            $mapped = true;
        } else  {
            // Else, use  the verified email address to retrieve the user
            $mapped = false;
            if (intval($userinfo['email_verified'])) {
                $verifieduser = get_user_by('email',$email); 
            }
        }
        // Allow a filter to find the user for special applications (phone number as username etc etc)
        $verifieduser = apply_filters( 'login_with_vipps_authenticate_user', $verifieduser, $userinfo, $session);

        if ($verifieduser && $verifieduser->ID != $userid) {
            if($session) $session->destroy();
            $this->deleteBrowserCookie();
            $sorrytext  = sprintf(__('Login not confirmed: This user is not the user mapped to the %1$s account, and does not have the same email account', 'login-with-vipps'), VippsLogin::CompanyName());
            $sorrytext = apply_filters('login_with_vipps_confirm_login_wrong_usertext', $sorrytext, wp_get_current_user(), $userinfo, $session);
            return $this->continue_with_vipps_error_confirm_login('wrong_user', $sorrytext);
        }
        $this->deleteBrowserCookie();
        if ($session) $session->destroy();

        $this->map_phone_to_user($phone, $sub, $user);  // Map the user to the Vipps account just in case.
        $this->actually_login_user($user,$sid,$session);
    }

    // The error handling punts a bit: Just print the referer and exit.
    public function continue_with_vipps_error_confirm_login ($error,$errordesc,$error_hint='',$sessiondata=array()) {
       $origin = $sessiondata['referer'];
       if (!$origin) $origin = wp_login_url();
       $message .= "<h2>" . sprint(__('Cannot log in: It is required for this account to verify login with the %1$s app', 'login-with-vipps'), VippsLogin::CompanyName()) . "</h2>";
       $message .= "<p>" . "<b>" . esc_html($error) . "<b>: " .  esc_html($errordesc) . "</p>";
       $message .= "<p>" . sprintf(__("<a href='%s'>Return to your previous page</a> to try again</a>", 'login-with-vipps'), esc_attr($origin)) . "</p>";
       wp_die($message);
    }

    // Very much like for confirmation, this handles errors for the action that marks the account to be synched with Vipps as to addressinfo etc. IOK 2019-10-14
    public function continue_with_vipps_error_synch($error,$errordesc,$error_hint='',$sessiondata=array()) {
        $userid = get_current_user_id();
        if (!$userid) wp_die(__('You must be logged in to synchronise your account', 'login-with-vipps'));
        $redir = get_edit_user_link($userid);
        $app = sanitize_title(($sessiondata && isset($sessiondata['application'])) ? $sessiondata['application'] : 'wordpress');
        $referer = ($sessiondata && isset($sessiondata['referer'])) ? $sessiondata['referer'] : '';

        // Override error page redirect for your application. No access to the possible new session. IOK 2019-10-08
        $redir = apply_filters('continue_with_vipps_error_synch_redirect', $redir, $error, $sessiondata);
        $redir = apply_filters("continue_with_vipps_error_{$app}_synch_redirect", $redir, $error, $sessiondata);
        do_action("continue_with_vipps_error_{$app}_synch", $error, $errordesc, $error_hint, $sessiondata);
        wp_safe_redirect($redir);
        exit();
    }
    // Again, errors to the wordpress proflepage. IOK 2019-10-14
    public function continue_with_vipps_error_wordpress_synch ($error, $errordesc, $errorhint, $sessiondata) {
        // Cannot  use the 'store_admin_notices' of ContinueWithVipps here, because it breaks Woocommerce which will not have been loaded yet 
        $cookie = sanitize_text_field(@$_COOKIE[LOGGED_IN_COOKIE]);
        if (!$cookie) return;
        $cookiehash =  hash('sha256',$cookie,false);
        $notices = "<div class='notice notice-error is-dismissible'><p>" . sprintf(__('Could not synchronize your %1$s profile: ', 'login-with-vipps'), VippsLogin::CompanyName()) . esc_html($errordesc) . "<p></div>";
        set_transient('_vipps_login_save_admin_notices_' . $cookiehash,$notices, 5*60);
    }

    // This handler marks the users so that we know that at every login, we want to synchronize address info etc from Vipps to Wordrpess. Mostly used for Woocommerce, so see the VippsWooLogin class. IOK 2019-10-14
    public function continue_with_vipps_synch($userinfo,$session) {
        if (!is_user_logged_in()) {
            $this->deleteBrowserCookie();
            if ($session) $session->destroy();
            wp_die(__('This can only be called when logged in', 'login-with-vipps'));
        }
        if (!$userinfo) {
            $this->deleteBrowserCookie();
            if ($session) $session->destroy();
            wp_safe_redirect($redir);
            exit();
        }
        $userid = get_current_user_id();
        update_user_meta($userid,'_vipps_synchronize_addresses', 1);
        update_user_meta($userid,'_vipps_just_synched', 1);

        $app = sanitize_title(($session && isset($session['application'])) ? $session['application'] : 'wordpress');
        do_action("continue_with_vipps_{$app}_synch",$userid,$userinfo,$session);


        $profile = get_edit_user_link($userid);
        $redir = $profile;
        if (isset($sessiondata['referer']) && $sessiondata['referer']) {
            $link = $sessiondata['referer'];
        }
        $userid = get_current_user_id();
        $redir = apply_filters('continue_with_vipps_synch_redirect', $profile, $userid, $session);
        $redir = apply_filters("continue_with_vipps_{$app}_synch_redirect", $redir , $userid, $session);
        $this->deleteBrowserCookie();
        if ($session) $session->destroy();
        wp_safe_redirect($redir);
        exit();
    }


}
