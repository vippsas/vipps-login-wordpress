<?php 
/*
   VippsLogin
     This class uses the ContinueWithVipps class to implement *logins* and *confirmations* of user accounts so that a user can link their Vipps and Wordpress accounts and log in using just the app.
     It is written extensible, so that further 'applications' - like WooCommerce - can use this class to implement specific login rules (for errorhandling, user modification, and end-user pages / profiles).

     It implements the login actions using Ajax for simplicity, and stores in the session a cookie value stored also in the browser. Any session that does not have the corresponding browser cookie is invalid.

     Because Vipps email-addresses aren't verified, we require that a user wanting to use this  to confirm their email address and the connection. This is done using the standard Wordpress 'user request' API if the user hasn't logged in yet.  If they are, they can connect directly from their profile page with another 'action' using ContinueWithVipps.

     Furthermore, and really mostly a Woo application, there is an 'synch' action that lets an existing, connected user syncrhronize their user data with Vipps if it has become separate. If this is done,
     the address will be synchronized at each login until the user changes the address specifically in the Worpdress instance.


*/
class VippsLogin {
    protected static $instance = null;

    function __construct() {
    }
    // This is a singleton class; access the single instance with this method. IOK 2019-10-14
    public static function instance()  {
        if (!static::$instance) static::$instance = new VippsLogin();
        return static::$instance;
    }

    // We use this to annotate the users' login session with a a current Vipps session. Not in use now, but can be used to require Vipps logins for instance. IOK 2019-10-14
    protected $currentSid = null;

    // The main init hook. We are going to hook into authentication, logout, error handling, the hooks defined by ContinueWithVipps and to the UserRequest handlers
    // for connecting accounts. IOK 2019-10-14
    public function init () {
        // Hook into standard auth and logout, but do so after the secure signon bits and so forth.
        add_filter('authenticate', array($this,'authenticate'),50,3); 
        // Can be used to manage sessions later it is hoped.
        add_action('wp_logout', array($this,'wp_logout'),10,3); 
        // Any errors from 'continue_with_vipps_error_login' will be handled here on the wp-login.php screen. IOK 2019-10-14
        add_filter('wp_login_errors', array($this, 'wp_login_errors'), 10, 2);

        // Profile updates for customers. 2019-10-14. Used currently just to connect/disconnect from Vipps. IOK 2019-10-14
        add_action('personal_options_update',array($this,'profile_update'));
        add_action('edit_user_profile_update',array($this,'profile_update'));
        add_action('user_profile_update_errors', array($this,'user_profile_update_errors'), 10,3);

        // Action that handles the 'waiting' page - the page that will be shown to the user while they confirm their email account.
        // On confirmation and reload, the user will be logged in .  This also integrates with 'template_redirect' because of this. IOK 2019-10-14
        add_action('continue_with_vipps_page_login', array($this, 'continue_with_vipps_page_login'), 10, 1);
        add_action('continue_with_vipps_before_page_login', array($this, 'continue_with_vipps_before_page_login') , 10, 1);

        // Ajax code loaded here. IOK 2019-10-14
        add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));

        // Login form button on wp-login.php main screen. IOK 2019-10-14
        add_action('login_form', array($this, 'login_form_continue_with_vipps'));
        add_action('register_form', array($this, 'register_form_continue_with_vipps'));
        add_action( 'login_enqueue_scripts', array($this,'login_enqueue_scripts' ));


        // We provdde 'login with vipps / continue with vipps' button shortcodes'. IOK 2019-10-14
        $this->add_shortcodes();

        // Ajax code to get the redirect url to start the login/confirmation process. IOK 2019-10-14
        add_action('wp_ajax_vipps_login_get_link', array($this,'ajax_vipps_login_get_link'));
        add_action('wp_ajax_nopriv_vipps_login_get_link', array($this,'ajax_vipps_login_get_link'));
        add_action('wp_ajax_vipps_confirm_get_link', array($this,'ajax_vipps_confirm_get_link'));
        add_action('wp_ajax_vipps_synch_get_link', array($this,'ajax_vipps_synch_get_link'));

        /* The following actions are for using the confirm-handler to allow users to confirm their account using email with the
           standard WP mechanism for user requests IOK 2019-10-11*/
        // This handler handles actual confirmations. It should be run after the default action at priority 10, but before the
        // maintenance action at 12.
        add_action('user_request_action_confirmed', array($this, 'confirm_vipps_connect_and_login'), 11); 

        // The user email. IOK 2019-10-14
        add_filter('user_request_action_description', array($this, 'confirm_vipps_connect_and_login_description'), 10, 2);
        add_filter('user_request_action_email_content', array($this, 'confirm_vipps_connect_and_login_email_content'), 10, 2);
        add_filter('user_request_action_email_subject', array($this, 'confirm_vipps_connect_and_login_email_subject'), 10, 3);

        // The admin email (not used) IOK 2019-10-14
        add_filter( 'user_confirmed_action_email_content', array($this, 'user_confirmed_vipps_connection_email_content'), 10, 2);
        add_filter( 'user_request_confirmed_email_subject', array($this, 'user_confirmed_vipps_connection_email_subject'), 10, 3);

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

    }
    // Scripts used to make the 'login' button work; they use Ajax. IOK 2019-10-14
    public function wp_enqueue_scripts() {
        wp_enqueue_script('login-with-vipps',plugins_url('js/login-with-vipps.js',__FILE__),array('jquery'),filemtime(dirname(__FILE__) . "/js/login-with-vipps.js"), 'true');
        wp_localize_script('login-with-vipps', 'vippsLoginConfig', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
        wp_enqueue_style('login-with-vipps',plugins_url('css/login-with-vipps.css',__FILE__),array(),filemtime(dirname(__FILE__) . "/css/login-with-vipps.css"), 'all');
    }

    public function login_enqueue_scripts() {
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

        $url = $this->get_vipps_login_link($application, array('referer'=>$referer));
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
        $url = ContinueWithVipps::getAuthRedirect('login',$data);
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

    // This ensures that a session is valid for the browser we are interacting with, using this cookie as a one-time password. 2019-10-14
    public function setBrowserCookie() {
        $cookie = base64_encode(hash('sha256',random_bytes(256), true));
        setcookie('VippsSessionKey', $cookie, time() + (2*3600), COOKIEPATH, COOKIE_DOMAIN,true,true);
        return $cookie;
    }
    public function deleteBrowserCookie() {
        unset($_COOKIE['VippsSessoinKey']);
        setcookie('VippsSessionKey', '', time() - (2*3600), COOKIEPATH, COOKIE_DOMAIN,true,true);
    }
    public function checkBrowserCookie($against) {
        if (!isset($_COOKIE['VippsSessionKey'])) return false;
        if (empty($against)) return false;
        return ($_COOKIE['VippsSessionKey'] == $against);
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

        // We also add some more options. These are put on the main "Log in with Vipps" options screen. IOK 2019-10-14
        register_setting('vipps_login_options2','vipps_login_options2', array($this,'validate'));
        add_action('continue_with_vipps_extra_option_fields', array($this,'extra_option_fields'));

        // Error- and successhandling on the profile page: Add some feedback in standard WP idioms. IOK 2019-10-14
        global $pagenow;
        if ($pagenow == 'profile.php') {
            $userid = get_current_user_id();
            $justconnected = get_usermeta($userid,'_vipps_just_connected');
            $justsynched = get_usermeta($userid,'_vipps_just_synched');
            if ($justconnected) {
                delete_user_meta($userid, '_vipps_just_connected');
                $vippsphone = get_usermeta($userid,'_vipps_phone');
                $notice = sprintf(__('You are now connected to the Vipps account <b>%s</b>!', 'login-with-vipps'), $vippsphone);
                add_action('admin_notices', function() use ($notice) { echo "<div class='notice notice-success notice-vipps is-dismissible'><p>$notice</p></div>"; });
            }
            if ($justsynched) {
                delete_user_meta($userid, '_vipps_just_synched');
                $vippsphone = get_usermeta($userid,'_vipps_phone');
                $notice = sprintf(__('You are now synchronized with the Vipps account <b>%s</b>!', 'login-with-vipps'), $vippsphone);
                add_action('admin_notices', function() use ($notice) { echo "<div class='notice notice-success notice-vipps is-dismissible'><p>$notice</p></div>"; });
            }
        } 
    }

    // Ajax methods for connecting accounts externalized here. IOK 2019-10-14
    public function admin_enqueue_scripts ($suffix) {
        if ($suffix == 'profile.php') {
            wp_enqueue_script('vipps-login-admin',plugins_url('js/vipps-admin.js',__FILE__),array('jquery'),filemtime(dirname(__FILE__) . "/js/vipps-admin.js"), 'true');
            wp_localize_script('vipps-login-admin', 'vippsLoginAdminConfig', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'vippsconfirmnonce'=>wp_create_nonce('vippsconfirmnonce') ) );
        }
    }

    // Extra options to be added to the admin-page for Login With Vipps. IOK 2019-10-14
    public function extra_option_fields () {
        $options = get_option('vipps_login_options2');
        $continuepageid = $options['continuepageid'];

        $continuepage = $this->ensure_continue_with_vipps_page();
        if (is_wp_error($continuepage)) {
            $notice = $continuepage->get_error_message();
            add_action('admin_notices', function() use ($notice) { echo "<div class='notice notice-error is-dismissible'><p>$notice</p></div>"; });
        } else {
            $continuepageid = $continuepage->ID;
        }


        ?>
            <?php settings_fields('vipps_login_options2'); ?>
            <tr>
            <td><?php _e('Continue-with-Vipps page', 'login-with-vipps'); ?></td>
            <td width=30%>
            <?php wp_dropdown_pages(array('name'=>'vipps_login_options2[continuepageid]','selected'=>$continuepageid,'show_option_none'=>__('Create a new page', 'login-with-vipps'))); ?>
            </td>
            <td><?php _e('Sometimes when using Vipps Login, the user may need to answer questions, confirm their email or other actions. This page, which you may leave blank, will be used for this purpose','login-with-vipps'); ?></td>
            </tr>
            <?php
    }

    // And validation for the extra options. IOK 2019-10-14
    public function validate ($input) {
        $current =  get_option('vipps_login_options2');
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

    // Upon activation, this plugin will create a new page that is used for one thing only: Waiting for the user to confirm that the
    // account they are trying to log into is actually owned by them, or that they control the email address. This code ensures that this page exists. IOK 2019-10-14
    public function activate () {
        $continuepage = $this->ensure_continue_with_vipps_page();
        $continueid = 0;
        if (!is_wp_error($continuepage)) {
            $continueid = $continuepage->ID;
        }
        $default = array('continuepageid'=>$continueid);
        add_option('vipps_login_options2',$default,false);
    }
   
    public static function deactivate () {
       // We don't delete anything however, just in case.
    }

    // Returns the page object of the 'continue with vipps' page, creating it if neccessary. 2019-10-14
    public function ensure_continue_with_vipps_page() {
        $options = get_option('vipps_login_options2');
        $continuepageid = $options['continuepageid'];
        if ($continuepageid) {
            $page = get_post($continuepageid);
            if ($page) return $page;
            if (!$page) {
                $options['continuepageid'] = 0;
                update_option('vipps_login_options2', $options);
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

        $defaultname = __('Continue with Vipps', 'login-with-vipps');

        $pagedata = array('post_title'=>$defaultname, 'post_status'=> 'publish', 'post_author'=>$authorid, 'post_type'=>'page');
        $newid = wp_insert_post($pagedata);
        if (is_wp_error($newid)) {
            return new WP_Error(__("Could not find or create the 'continue with Vipps' page", 'login-with-vipps') . ": " .  $newid->get_error_message());
        }

        $options['continuepageid'] = $newid;
        update_option('vipps_login_options2', $options);
        return get_post($newid);
    }

    // On the profile page, show extra buttons to connect and disconnect a user with a Vipps account . IOK 2019-10-14
    function show_extra_profile_fields( $user ) {
        $allow_login = true;
        $allow_login = apply_filters('continue_with_vipps_allow_login', $allow_login, $user, array(), array());
        $vippsphone = trim(get_usermeta($user->ID,'_vipps_phone'));
        $vippsid = trim(get_usermeta($user->id,'_vipps_id'));
        $its_you = (get_current_user_id() == $user->ID);
        ?>
            <h2 class='vipps-profile-section-header'><?php _e('Log in with Vipps', 'login-with-vipps'); ?> </h2>
            <?php if ($allow_login): ?>
            <table class="form-table">
            <tr>
            <th><?php _e('Use Vipps to login to your account', 'login-with-vipps'); ?></th>
            <td>
            <?php if ($vippsphone && $vippsid): ?>
            <?php if ($its_you): ?>
            <p> <?php printf(__('You are connected to the Vipps account with the phone number <b>%s</b>', 'login_vipps'), esc_html($vippsphone)); ?></p>
            <?php else: ?>
            <p> <?php printf(__('The user is connected to the Vipps account with the phone number <b>%s</b>', 'login_vipps'), esc_html($vippsphone)); ?></p>
            <?php endif; ?> 
            <p><button class="button vipps-disconnect" value="1" name="vipps-disconnect"><?php _e('Press here to disconnect','login-with-vipps'); ?></button></p>
            <span class="description"><?php _e("As long as your account is connected to a Vipps account, you can log in just by using Log in With Vipps using the connected app.",'login-with-vipps'); ?></span>
            <?php else: ?>
            <?php if ($its_you): ?>
            <p> <?php _e('You are not connected to any Vipps accounts', 'login-with-vipps'); ?></p>
            <p><button type="button" onclick="connect_vipps_account('wordpress');return false"; class="button vipps-connect" value="1" name="vipps-connect"><?php _e('Press here to connect with your app','login-with-vipps'); ?></button></p>
            <?php else: ?>
            <p> <?php _e('The user is not connected to any Vipps accounts', 'login-with-vipps'); ?></p>
            <?php endif; ?> 
            <span class="description"><?php _e("You can connect to your Vipps account if you use the same email address both in the app and on this site. After connecting, you can log in just using Vipps", 'login-with-vipps'); ?></span>
            <?php endif; ?>
            </td>
            </tr>
            </table>
            <?php else: ?>
            <table class="form-table">
            <tr>
            <th><?php _e('Vipps login disabled', 'login-with-vipps'); ?></th>
            <td>
            <span class="description"><?php _e("It is unfortunately not possible for your account to use Vipps to log in to this system due to the site administrators policy."); ?></span>
            </td>
            </tr>
            </table>
            <?php endif; ?>

            <?php
    }

    // This runs when the users saves the profile page, which here includes disconnecting from Vipps. IOK 2019-10-14
    function profile_update( $userid ) {
        if (!current_user_can('edit_user',$userid)) return false;
        if (isset($_POST['vipps-disconnect']) && $_POST['vipps-disconnect']) {
            $phone = get_usermeta($userid, '_vipps_phone');
            delete_user_meta($userid,'_vipps_phone');
            delete_user_meta($userid,'_vipps_id');
            $notice = sprintf(__('Connection to Vipps account %s <b>removed</b>.', 'login-with-vipps'), $phone);
            $continue = ContinueWithVipps::instance();
            $continue->add_admin_notice($notice);
            $continue->store_admin_notices();
            return true;
        }
    }
    // If neccessary, add errors et to the profile update.
    public function user_profile_update_errors($errors,$update,$user) {
       // Not actually neccessary, yet.
    }

    // At this point, this method does not do anything. However, it is intended to 
    // allow this plugin to *require* that Vipps be used for connected users. This will then
    // allow this plugin to deny bruteforce attacks against these accounts. IOK 2019-10-14
    public function authenticate ($user, $username, $password) {
        if (!$user) return $user;
        if (! ($user instanceof WP_User)) return $user;

        if (!$this->currentSid) $this->currentSid = array($user->ID, null);
        if ($this->currentSid) {
            list ($userid, $sid) = $this->currentSid; 
# If user requries vipps and we have no sid, then return a WP_Error
        }
        return $user;
    }

    // Ideally, we would interact with Vipps here, but these parts of the API is not yet implemented. IOK 2019-10-14
    public function wp_logout () {
        $user = wp_get_current_user();
    }

    // For convenience, some shortcodes that output the same buttons added to the login page. IOK 2019-10-14
    public function add_shortcodes() {
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
        $this->wp_login_button(__('Log in with', 'login-with-vipps'));
        $this->move_continue_button_over_login_form();
    }
    public function register_form_continue_with_vipps () {
        $this->wp_login_button(__('Create an account using ', 'login-with-vipps'));
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
        $logo = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
        ?>
            <a href='javascript:login_with_vipps("<?php echo $application; ?>");' class="button vipps-orange vipps-button continue-with-vipps" title="<?php echo $text; ?>"><?php echo $text;?> <img
            alt="<?php _e('Log in without password using Vipps', 'login-with-vipps'); ?>" src="<?php echo $logo; ?>">!</a>
            <?php
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

            $state = @$_REQUEST['state'];
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

    // For some reason we need to go to the waiting page (user is not confirmed). IOK 2019-10-1e
    public function redirect_to_waiting_page($forwhat,$session) {
        $state = $forwhat . "::" . $session->sessionkey;
        $continuepage = $this->ensure_continue_with_vipps_page();
        if (is_wp_error($continuepage)) {
            wp_die(__("Cannot redirect to Login With Vipps waiting page - it doesn't exist! If you just tried to log in, check your email.", 'login-with-vipps'));
        }
        $waiturl = get_permalink($continuepage);
        $redir = add_query_arg(array('state'=>urlencode($state)), $waiturl);
        wp_safe_redirect($redir, 302, 'Vipps');
        exit();
    }

    // This is actually run as the 'the_content' filter for the waiting page, replacing the contents of whatever page was used with this. IOK 2019-10-14
    public  function continue_with_vipps_waiting_page($content) {
        $state = @$_REQUEST['state'];
        $sessionkey = '';
        $action = '';
        if ($state) list($action,$sessionkey) = explode("::",$state);
        $session = VippsSession::get($sessionkey);

        if (!$session  || !$this->checkBrowserCookie($session['cookie'])) {
            $message = __('This page is used to handle your requests when continuing from Vipps and further action is required to complete your task. But in this case, it doesn\'t seem to be an open session', 'login-with-vipps');  
            $message = apply_filters('continue_with_vipps_waiting_page_expired_session_' . $action, $message,  $session);
            if ($session) $session->destroy();
            return $message;
        }
        ob_start();
        do_action('continue_with_vipps_page_' . $action, $session);
        return ob_get_clean();
    }

    // This happens right before the waiting page, before output is started. Used to check if the user is now confirmed, in which case login can proceed. IOK 2019-10-14 
    public function continue_with_vipps_before_page_login($sessionkey) {
        if (!$sessionkey) return;
        $session = VippsSession::get($sessionkey);


        if (!$session  || !$this->checkBrowserCookie($session['cookie'])) return;
        if (@$session['subaction'] != 'confirm_your_account') return;
        $userid = @$session['user'];
        if (!$userid) return; 
        $userinfo = @$session['userinfo'];
        if (!$userinfo) return;

        $vippsphone = get_usermeta($userid,'_vipps_phone');
        $vippsid = get_usermeta($userid,'_vipps_id');

        if ($vippsphone == $userinfo['phone_number'] && $vippsid == $userinfo['sub']) { 
            $user = get_user_by('id', $userid);
            $this->actually_login_user($user,$userinfo['sid'],$session);
            exit();
        }
        return false;
    }

    public function continue_with_vipps_page_login($session)  {
        if ($session['subaction'] == 'confirm_your_account') {
            print "<p>";
            _e("Welcome! As this is your first log-in with Vipps, for safety reasons we require that you must confirm that your account as identified by your registered e-mail belongs to you.", 'login-with-vipps');
            print "<p>";
            print "<p>";
            _e("We have sent an email to your account with a confirmation link. Press this, and you will be confirmed!");          
            print "</p>";

        } else {
            $msg = __("Welcome! If you see this page, an really unexpected error has occur. Unfortunately, we can't do better than to send you to the <a href='%s'>login page</a>", 'login-with-vipps');
            printf($msg, wp_login_url()); 
        }
    }


    // IOK 2019-10-14 this handler is run when the user confirms their identity by clicking on the email link. We connect the accounts and mark the request as completed.
    public function confirm_vipps_connect_and_login ($request_id) {
        $request = wp_get_user_request_data( $request_id );
        if (!is_a( $request, 'WP_User_Request' ) || 'request-confirmed' !== $request->status) {
            return;
        }
        $action = $request->action_name;
        if ($action !== 'vipps_connect_login') return;

        $data = $request->request_data;

        $email = @$data['email'];
        $userid = @$data['userid'];
        $phone = @$data['vippsphone'];
        $sub = @$data['sub'];

        // One could here check the post author too. IOK 2019-10-11

        update_user_meta($userid,'_vipps_phone',$phone);
        update_user_meta($userid,'_vipps_id',$sub);
        update_user_meta($userid, '_vipps_just_connected', 1);

        // We don't need to alert admin, so we don't.
        update_post_meta( $request_id, '_wp_admin_notified', true );
        update_post_meta( $request_id, '_wp_user_request_completed_timestamp', time() );
        $result = wp_update_post(
                array(
                    'ID'          => $request_id,
                    'post_status' => 'request-completed',
                    )
                );
    }

    // The below overrides texts and contents of the confirmation emails. IOK 2019-10-14
    public function confirm_vipps_connect_and_login_description ($desc, $action) {
        if ($action !== 'vipps_connect_login') return $desc;
        return __('Connect your Vipps account', 'login-with-vipps');
    }
    public function confirm_vipps_connect_and_login_email_content ($email_text, $email_data) {
        if ($email_data->request->action_name !== 'vipps_connect_login') return $email_text;
        return $email_text; // Bleh
    }
    public function confirm_vipps_connect_and_login_email_subject ($subject,$sitename,$email_data) {
        if ($email_data->request->action_name !== 'vipps_connect_login') return $subject;
        return sprintf(__('Confirm that you want to connect your Vipps account on %s', 'login-with-vipps'), $sitename); 
    }
    // Admin emails, not used but still
    public function user_confirmed_vipps_connection_email_content ($email_text, $email_data) {
        if ($email_data->request->action_name !== 'vipps_connect_login') return $email_text;
        $email_text = __(
                'Hei,

                A user just connected his Vipps account with his Wordpress account on ###SITENAME###:

                User: ###USER_EMAIL###
                Request: ###DESCRIPTION###

                No further action is required.

                Regards,
                All at ###SITENAME###
###SITEURL###'
                );
        return $email_text; 
    }
    public function user_confirmed_vipps_connection_email_subject ($subject, $sitename, $email_data) {
        if ($email_data->request->action_name !== 'vipps_connect_login') return $subject;
    }


    // This is used in a hook when redirecing to wp-login.php. It will format and output any errors that occured during login. IOK 2019-10-14
    // In this situation we use a fresh VippsSession to carry this information.
    public function wp_login_errors ($errors, $redirect_to) {
        $session = array();
        $state = @$_REQUEST['vippsstate'];
        $errorcode = @$_REQUEST['vippserror'];
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

        // This would be for an application to extend the session if needed IOK 2019-10-08 
        do_action("continue_with_vipps_error_{$app}_login", $error, $errordesc, $errorhint, $session);


        if ($createSession) $redir = add_query_arg(array('vippsstate'=>urlencode($session->sessionkey), 'vippserror'=>urlencode($error)), $redir);
        wp_safe_redirect($redir);
        exit();
    }


    // This function will login your user when appropriate (ie, after 'authenticate' has run and everything is good).
    // NB: 'authenticate' may well redirect to another page, and then back here. Therefore the session will be active right until we
    // can redirect to the final page ('login_redirect').  IOK 2019-10-14
    // This page is also parametrized on the passed application so that for instance Woo can update addressses and so forth. IOK 2019-10-14
    protected function actually_login_user($user,$sid=null,$session=null) {
        // First, ensure that we interact properly with MFA stuff and so forth

        $user = apply_filters('authenticate', $user, '', '');
        if (is_wp_error($user)) {
            $error = $user;
            $this->continue_with_vipps_error_login($error->get_error_code(),$error->get_error_message(),'', $session);
            exit();
        }

        $app = sanitize_title(($session && isset($session['application'])) ? $session['application'] : 'wordpress');
        do_action('continue_with_vipps_before_user_login', $user, $session);
        do_action("continue_with_vipps_before_{$app}_user_login", $user, $session);

        $this->currentSid = array($user->ID, $sid);
        add_filter('attach_session_information', function ($data,$user_id) use ($sid) {
                $data['vippssession'] = $sid;
                return $data;
                }, 10, 2);

        wp_set_auth_cookie($user->ID, false);
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
        $sid=  $userinfo['sid'];

        $user = get_user_by('email',$email);
 
        // Login is parametrized by 'application' stored in the session. Will be 'wordpress', 'woocommerce' etc. IOK 2019-10-14
        $app = sanitize_title(($session && isset($session['application'])) ? $session['application'] : 'wordpress');

        // MFA plugins may actually redirect here again, in which case we will now be logged in, and we can just redirect. Destroy session first of course. IOK 2019-10-14
        if (is_user_logged_in() == $user) {
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
            $this->continue_with_vipps_error_login('invalid_session', __("Your session is invalid. Only one Vipps-session can be active per browser at a time. Also, ensure that you are not blocking cookies - you will need those for login!", 'login-with-vipps'), '', $session);
        }

        // Check if we allow user registrations
        $can_register = apply_filters('option_users_can_register', get_option('users_can_register'));
        $can_register = apply_filters('continue_with_vipps_users_can_register', $can_register, $userinfo, $session);
        $can_register = apply_filters("continue_with_vipps_{$app}_users_can_register", $can_register, $userinfo, $session);

        if (!$user && !$can_register) {
            if($session) $session->destroy();
            $this->deleteBrowserCookie();
            $this->continue_with_vipps_error_login('unknown_user', __('Could not find any user with your registered email - cannot log in', 'login-with-vipps'), '', $session);
            exit();
        }

        // Here we don't have a user, but we are allowed to register, so let's do that
        if (!$user) {
            $pass = wp_generate_password( 32, true);

            // Fix username here so it's unique, then allow applications to change it
            $newusername = apply_filters('continue_with_vipps_create_username', $username, $userinfo,$session);
            $newusername = apply_filters("continue_with_vipps_{$app}_create_username", $username, $userinfo,$session);
            $user_id = wp_create_user( $newusername, $random_password, $email);
            if (is_wp_error($user_id)) {
                if($session) $session->destroy();
                $this->deleteBrowserCookie();
                $this->continue_with_vipps_error_login('unknown_user', sprintf(__('Could not create a new user - an error occured: %s', 'login-with-vipps'), esc_html($user_id->get_error_message())), '', $session);
                exit();
            }

            $userdata = array('ID'=>$user_id, 'user_nicename'=>$name, 
                    'nickname'=>$firstname, 'first_name'=>$firstname, 'last_name'=>$lastname,
                    'user_registered'=>date('Y-m-d H:i:s'));

            // Allow applications to modify this, or they can use the hook below IOK 2019-10-14
            $userdata = apply_filters('continue_with_vipps_create_userdata', $userdata, $userinfo,$session);
            $userdata = apply_filters("continue_with_vipps_{$app}_create_userdata", $userdata, $userinfo,$session);

            wp_update_user($userdata);

            update_user_meta($user_id,'_vipps_phone',$phone);
            update_user_meta($user_id,'_vipps_id',$sub);
            update_user_meta($userid, '_vipps_just_connected', 1);

            do_action('continue_with_vipps_after_create_user', $user, $session);
            do_action("continue_with_vipps_after_create_{$app}_user", $user, $session);

            $user = get_user_by('id', $user_id);

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
            $this->continue_with_vipps_error_login('login_disallowed', __('It is unfortunately not allowed for your account to log-in using Vipps', 'login-with-vipps'), '', $session);
            exit();
        }

        // And now we have a user, but we must see if the accounts are connected, and if so, log in IOK 2019-10-14
        $vippsphone = get_usermeta($user->ID,'_vipps_phone');
        $vippsid = get_usermeta($user->id,'_vipps_id');
        if ($vippsphone == $phone && $vippsid == $sub) { 
            $this->actually_login_user($user,$sid,$session);
            exit();
        }
        if ($vippsphone && $vippsid && ($vippsphone != $phone && $vippsid != $sub)) {
            if($session) $session->destroy();
            $this->deleteBrowserCookie();
            $this->continue_with_vipps_error_login('login_disallowed', __('Another Vipps account is already connected to the user with your e-mail address. Unfortunately we can\'t log you in', 'login-with-vipps'), '', $session);
            exit();
        }

        // We are *not* connnected, so we must now redirect to the waiting page after sending a confirmation job IOK 2019-10-14
        // First check for existing user requests. This is still no function for this, so we inline it using $wpdb. IOK 2019-10-14
        $requestid = 0;
        $requests = get_posts(array('post_type' => 'user_request','post_name__in' =>array( 'vipps_connect_login'),'title'=> $email,'post_status'=>array('request-pending')));
        if (!empty($requests)) {
            $requestid = $requests[0]->ID;
        } else {
            $requestid = wp_create_user_request($email,'vipps_connect_login', array('application'=>$app, 'email'=>$email,'vippsphone'=>$phone, 'userid'=>$user->ID ,'sid'=>$sid, 'sub'=>$sub));
        }
        // Now we should have a possibly fresh request, but just to be sure, errorhandling: IOK 2019-10-14
        if (is_wp_error($requestid)) {
            if($session) $session->destroy();
            $this->deleteBrowserCookie();
            $this->continue_with_vipps_error_login('confirmation_request_failed', __('Unfortunately, we could not send a confirmation request to your e-mail address. You will need to log in with username and password, and connect your Vipps app on your profile page.', 'login-with-vipps'), '', $session);
            exit();
        }
        // Mark the request with the users' id. IOK 2019-10-14
        wp_update_post(array('ID'=>$requestid, 'post_author'=>$user->ID));
        wp_send_user_request($requestid); 

        // Prepare the redirect page, which will now have 'subaction' and 'application' in session. IOK 2019-10-14
        $session->set('subaction','confirm_your_account');
        $session->set('user',$user->ID);
        $this->redirect_to_waiting_page('login', $session);
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

        do_action("continue_with_vipps_error_{$app}_confirm", $error, $errordesc, $errorhint, $sessiondata);

        wp_safe_redirect($redir);
        exit();
    }

    // This stores the error-messages temporarily in a transient keyed with the users' cookie. They can then be displayed on the profile page after reloads. IOK 2019-10-14
    public function continue_with_vipps_error_wordpress_confirm ($error, $errordesc, $errorhint, $sessiondata) {
        // Cannot  use the 'store_admin_notices' of ContinueWithVipps here, because it breaks Woocommerce which will not have been loaded yet - so we inline it. IOK 2019-10-14 
        $cookie = @$_COOKIE[LOGGED_IN_COOKIE];
        if (!$cookie) return;
        $cookiehash =  hash('sha256',$cookie,false);
        $notices = "<div class='notice notice-error is-dismissible'><p>" . __("Could not connect to your Vipps account: ", 'login-with-vipps') . esc_html($errordesc) . "<p></div>";
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

        $profile = get_edit_user_link($user->ID);
        $userid = get_current_user_id();
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
        $sid=  $userinfo['sid'];

        $user = get_user_by('email',$email);
        if ($user->ID != $userid) {
            if($session) $session->destroy();
            $this->deleteBrowserCookie();
            $this->continue_with_vipps_error_confirm('wrong_user', __('Unfortunately, you cannot connect to this Vipps-account: The email addresses are not the same.', 'login-with-vipps'), '', $session);
            exit();
        }

        update_user_meta($userid, '_vipps_phone', $phone);
        update_user_meta($userid, '_vipps_id', $sub);
        update_user_meta($userid, '_vipps_just_connected', 1);

        do_action("continue_with_vipps_{$app}_confirm_before_redirect", $userid, $userinfo, $session);

        $this->deleteBrowserCookie();
        if ($session) $session->destroy();
        wp_safe_redirect($redir);
        exit();

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
        do_action("continue_with_vipps_error_{$app}_synch", $error, $errordesc, $errorhint, $sessiondata);
        wp_safe_redirect($redir);
        exit();
    }
    // Again, errors to the wordpress proflepage. IOK 2019-10-14
    public function continue_with_vipps_error_wordpress_synch ($error, $errordesc, $errorhint, $sessiondata) {
        // Cannot  use the 'store_admin_notices' of ContinueWithVipps here, because it breaks Woocommerce which will not have been loaded yet 
        $cookie = @$_COOKIE[LOGGED_IN_COOKIE];
        if (!$cookie) return;
        $cookiehash =  hash('sha256',$cookie,false);
        $notices = "<div class='notice notice-error is-dismissible'><p>" . __("Could not synchronize your Vipps account: ", 'login-with-vipps') . esc_html($errordesc) . "<p></div>";
        set_transient('_vipps_login_save_admin_notices_' . $cookiehash,$notices, 5*60);
    }

    // This handler marks the users so that we know that at every login, we want to synchronize address info etc from Vipps to Wordrpess. Mostly used for Woocommerce, so see the WooLogin class. IOK 2019-10-14
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
