<?php 
/*
   ContinueWithVipps: 

   This singleton class handles the communication with the Vipps Login
   API and the basic flow of any actions using that API.  The canonical
   example is 'login', but any action can be made signe-able by Vipps
   using this.

   To use this, you will use this class getAuthRedirect
   method together witn a (slug-link) action string and
   arbitrary sessiondata.  On return from Vipps, there will be
   called a set of actions, "continue_with_vipps_{$action}" or
   "continue_with_vipps_error_{$action}" if things go wrong. These
   hooks will recieve the callback data from Vipps (with userinfo) and
   the session, and are expected to redirect the user to their final
   destination. Error-handling will have to interact with whatever page
   you expect to end up on and what application you integrate against.

   A database session will be created to hold the users' session while
   this happens. The session is destroyed before the error handler is
   called, but for success callbacks, you need to clean up the session
   yourself until the final destination is reached.

   If you store sensitive information in the session, you will need to
   secure this with a browser cookie as well. Normally the session key
   gets passed to and fro Vipps as a GET parameter.


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

class ContinueWithVipps {
    public $options = array();
    public $settings = array();
    // Keys and URLs are fetched via the .well-known interface, but stored here and in a transient. IOK 2019-10-14
    public $oauthdata = null;
    public $oauthkeys = array();
    // We extend the database for this application with a single table for storing sessions needed
    // when negotiating with Vipps IOK 2019-10-14
    public static $dbversion = 1;
    // This is a singleton class. IOK 2019-10-14
    protected static $instance = null;

    function __construct() {
        $this->settings = get_option('vipps_login_options', array());
    }

    // This class should only get instantiated with this method. IOK 2019-10-14 
    public static function instance()  {
        if (!static::$instance) static::$instance = new ContinueWithVipps();
        return static::$instance;
    }

    // This function does nothing, but translatable strings added here will get seen by the gettext tools. Mostly this is for
    // translating return values from the Vipps API 2019-10-14
    private function translation_dummy () {
        print __('User cancelled the login', 'login-with-vipps');
    }


    // IOK 2019-10-14 This is the main entry-point for this class and for logging in and doing stuff with the Vipps oauth API. Call this with an action-name (like 'login') and sessiondata,
    // and you will get an URL back to which you can redirect the user. You should do this in a POST request so that you can start a session by setting a cookie.
    // The login-session will be stored in the database and is retrievable by the 'state' argument passed to and from Vipps. If sensitive, you may need to secure this session with a private (cookie-stored) value as well.
    public static function getAuthRedirect($action,$sessiondata=null,$scope="openid address birthDate email name phoneNumber") {
        $me = static::instance();
        $url      = $me->authorization_endpoint();
        $redir    = $me->make_callback_url();
        $clientid = $me->settings['clientid'];
        if (is_array($scope)) $scope = join(' ',$scope);

        if (!is_array($sessiondata)) $sessiondata = array();
        $sessiondata['action'] = $action;
        $session = VippsSession::create($sessiondata);
        $sessionkey = $session->sessionkey;
        $state = $action . "::" . $sessionkey;

        $args = array('client_id'=>$clientid, 'response_type'=>'code', 'scope'=>$scope, 'state'=>$state, 'redirect_uri'=>$redir);
        return $url . '?' . http_build_query($args);
    }


    // This runs on the main init hook. Not much here yet. IOK 2019-10-14
    public function init () {

    }
    // And this runs on plugins-loaded. The call to dbtables will only do things when the database definition changes. IOK 2019-10-14
    public function plugins_loaded () {
        $ok = load_plugin_textdomain('login-with-vipps', false, basename( dirname( __FILE__ ) ) . "/languages");
        $this->options =  get_option('vipps_login_options'); 
        // Just in case the tables were updated without 'activate' having been run IOK 2019-09-18
        $this->dbtables();
    }


    // The admin-init hook. We add warnings temporarily stored in the database as well as handling options and cleaning out old sessiondata (if any). 2019-10-14
    public function admin_init () {
        add_action('admin_notices',array($this,'stored_admin_notices'));
        add_action('admin_enqueue_scripts', array($this,'admin_enqueue_scripts'));
        register_setting('vipps_login_options','vipps_login_options', array($this,'validate'));
        VippsSession::clean();
    }

    public function admin_enqueue_scripts ($suffix) {
        if ($suffix == 'settings_page_vipps_login_options') {
            wp_enqueue_script('vipps-settings',plugins_url('js/vipps-settings.js',__FILE__),array('jquery'),filemtime(dirname(__FILE__) . "/js/vipps-settings.js"), 'true');
        }
    }

    public function admin_menu () {
        add_options_page(__('Login with Vipps', 'login-with-vipps'), __('Login with Vipps','login-with-vipps'), 'manage_options', 'vipps_login_options',array($this,'toolpage'));
    }
    // Helper function for creating an admin notice.
    public function add_admin_notice($notice) {
        add_action('admin_notices', function() use ($notice) { echo "<div class='notice notice-info is-dismissible'><p>$notice</p></div>"; });
    }
    // This stores admin notices temporarily in a transient while WP reloads. These messages will then be added when the next page loads. IOK 2019-10-14
    public function store_admin_notices() {
        // Ensure each logged in user gets their own messages by adding their hash to the transient. IOK 2019-10-14
        $cookie = @$_COOKIE[LOGGED_IN_COOKIE];
        if (!$cookie) return;
        $cookiehash =  hash('sha256',$cookie,false);
        ob_start();
        do_action('admin_notices');
        $notices = ob_get_clean();
        set_transient('_vipps_login_save_admin_notices_' . $cookiehash,$notices, 5*60);
    }

    // So if the 'store_admin_notices' method was called before WP shut down, this will fetch any stored notices for display when the user reloads the page. IOK 2019-10-14
    public function stored_admin_notices() {
        $cookie = @$_COOKIE[LOGGED_IN_COOKIE];
        if (!$cookie) return;
        $cookiehash =  hash('sha256',$cookie,false);
        $stored = get_transient('_vipps_login_save_admin_notices_' . $cookiehash);
        if ($stored) {
            delete_transient('_vipps_login_save_admin_notices_' . $cookiehash);
            print $stored;
        }
    }

    // This is the main options-page for this plugin. The classes VippsLogin and WooLogin adds more options to this screen just to 
    // keep the option-data local to each class. IOK 2019-10-14
    public function toolpage () {
        if (!is_admin() || !current_user_can('manage_options')) {
            die(__("Insufficient privileges",'login-with-vipps'));
        }
        $options = get_option('vipps_login_options'); 

        $callback = $this->make_callback_url();

        ?>
            <div class='wrap'>
            <h2><?php _e('Login With Vipps', 'login-with-vipps'); ?></h2>


            <?php do_action('admin_notices'); ?>

            <form action='options.php' method='post'>
            <?php settings_fields('vipps_login_options'); ?>
            <table class="form-table" style="width:100%">

            <tr>
            <td><?php _e('Client ID', 'login-with-vipps'); ?></td>
            <td width=30%><input id=configpath style="width:20em" name="vipps_login_options[clientid]" class='vippspw' value="<?php echo htmlspecialchars($options['clientid']);?>" autocomplete="off" type="password"></td>
            <td><?php _e('Your client ID, from the Vipps Portal','login-with-vipps'); ?></td>
            </tr>
            <tr>
            <td><?php _e('Client Secret', 'login-with-vipps'); ?></td>
            <td width=30%><input id=configpath style="width:20em" name="vipps_login_options[clientsecret]" class='vippspw' value="<?php echo htmlspecialchars($options['clientsecret']);?>" autocomplete="off"  type="password"></td>
            <td><?php _e('Your client secret, from the Vipps Portal','login-with-vipps'); ?></td>
            </tr>

            <tr>
            <th><?php _e('Your callback URL is:', 'login-with-vipps'); ?></th>
            <td><b><?php echo esc_html($callback); ?></b>
            <td><?php _e('You will need to register this URL into the Vipps Portal to make Login with Vipps work. If you change the URL or the permalinks of your site, you will need to do this again by finding the new URL on this page.', 'login-with-vipps'); ?></td>
            </tr>



            </table>
            <div> <input type="submit" style="float:left" class="button-primary" value="<?php _e('Save Changes') ?>" /> </div>

            </form>

            <?php  do_action('continue_with_vipps_extra_option_fields'); ?>


            </div>

            <?php
    }

    // Validating user options. Currenlty a trivial function. IOK 2019-10-19
    public function validate ($input) {
        $current =  get_option('vipps_login_options'); 

        $valid = array();
        foreach($input as $k=>$v) {
            switch ($k) {
                default: 
                    $valid[$k] = $v;
            }
        }
        return $valid;
    }

    // The activation hook will create the session database tables if they do not or if the database has been upgraded. IOK 2019-10-14
    public function activate () {
        // Options
        $default = array('clientid'=>'','clientsecret'=>'', 'dbversion'=>0);
        add_option('vipps_login_options',$default,false);
        $this->dbtables();

    }

    // The deactivation hook. It currently will only delete all active sessions. Deletion of the database will occur in the uninstall.php file instead.
    //  IOK 2019-10-14
    public static  function deactivate () {
        VippsSession::destroy_all();
    }

    // To handle the sessions (abstracted in the VippsSession class), we need some database tables. For historical reasons, this is in this class 
    // rather than the VippsSession class. IOK 2019-10-14.
    public function dbtables() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $tablename = $wpdb->prefix . 'vipps_login_sessions';
        $charset_collate = $wpdb->get_charset_collate();
        $options = get_option('vipps_login_options');
        $version = static::$dbversion;
        if ($options['dbversion'] == $version) {
            return false;
        }

        // 'state', the primary key, is created large enough to contain a SHA256 hash, but is 
        // varchar just in case. IOK 2019-10-14
        // For the syntax here, remember to consult https://codex.wordpress.org/Creating_Tables_with_Plugins
        $tablecreatestatement = "CREATE TABLE `${tablename}` (
            state varchar(44) NOT NULL,
                  expire timestamp DEFAULT CURRENT_TIMESTAMP,
                  content text DEFAULT '',
                  PRIMARY KEY  (state),
                  KEY expire (expire)
                      ) ${charset_collate};";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $result = dbDelta( $tablecreatestatement, true );
        foreach ($result as $s) {
            error_log($s);
        }
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$tablename'");
        if($exists != $tablename) {
            $this->add_admin_notice(__('Could not create session table - Login With Vipps plugin is not correctly installed', 'login-with-vipps'));
        } else {
            error_log(__("Installed database tables for Login With Vipps", 'login-with-vipps'));
            $options['dbversion']=static::$dbversion;
            update_option('vipps_login_options',$options,false);
        }
        return true;
    }

    // When returning from Vipps, we intercept the return-URL passed and call the 'continue_with_vipps_<action>' handlers, that should redirect to 
    // a final destination. We don't however create a special page or rewrite rule for this callback - we just intercept it in the template_redirect hook. IOK 2019-10-14


    // This is the handler-function that 'template_redirect' dispatches to. It handles the callback, managing the JWT tokens, getting the userinfo, deleting or updating the sessions
    // and finally dispatching via the action hooks to the end-result. Default actions implemented with wp_die() just so a result can be shown. IOK 2019-10-14
    public function continue_from_vipps () {
        // We are always going to redirect somewhere from here, so let's start by not caching this. IOK 2019-10-11
        wc_nocache_headers();
        // The 'state' value passed to and from Vipps will conttain both the action and the session key. IOK 2019-10-14
        $state = @$_REQUEST['state'];
        $action ='';
        $sessionkey='';
        if ($state) {
            list($action,$sessionkey) = explode("::", $state);
        }
        $session = VippsSession::get($sessionkey);

        $error = @$_REQUEST['error'];
        $errordesc = @$_REQUEST['error_description'];
        $error_hint = @$_REQUEST['error_hint'];

        $forwhat = $action;
        $userinfo = null;

        if ($error) {
            // When errors happen, we always destroy the current session. You may need to create a new one if you need to pass  info. IOK 2019-10-19
            if($session) $session->destroy();
            do_action('continue_with_vipps_error_' .  $forwhat, $error,$errordesc,$error_hint, $session);
            wp_die(sprintf(__("Unhandled error when using Continue with Vipps for action %s: %s", 'login-with-vipps'), esc_html($forwhat), esc_html($error)));
        }

        $code =  @$_REQUEST['code'];
        $scope = @$_REQUEST['scope'];

        // Now to get the user information. When successful, we will store this in the session, but to get there, we need to decode the idtoken, verify its signature, then get the userinfo
        // via the API and then check that the 'sub' (subscriber) values are the same. At each point we will redirect any errors to the error handler.
        // Because the session is not destroyed on success, we can return to this 'page' if neccessary, and that can happen if for instance a MFA plugin redirects back after checking its code.
        // this works because we do not call the userinfo API more than once. IOK 2019-10-14
        $accesstoken = null;
        $idtoken = null;
        $idtoken_sub = null;

        if ($session && $session['userinfo']) {
            $userinfo = $session['userinfo'];
        }  else {
            if ($code) {
                $authtoken = $this->get_auth_token($code);
                if (isset($authtoken['content']) && isset($authtoken['content']['access_token'])) {
                    $accesstoken = $authtoken['content']['access_token'];
                    $idtoken = $authtoken['content']['id_token'];
                    $keys = $this->get_oauth_keys();
                    $result = VippsJWTVerifier::verify_idtoken($idtoken,$keys);
                    if ($result['status'])  {
                        $idtoken_sub = $result['data']['sub'];
                    } else {
                        error_log("Error verifiying the oAuth2 JWT: " . $result['msg']);
                        if($session) $session->destroy();
                        if ($forwhat) { 
                            do_action('continue_with_vipps_error_' .  $forwhat, 'vipps_protocol_error',sprintf(__('Could not verify your oAuth2 token: %s', 'login-with-vipps'), esc_html($result['msg'])),'', $session);
                        }
                        wp_die(sprintf(__('Could not verify your oAuth2 token: %s', 'login-with-vipps'), esc_html($result['msg'])));
                    }
                } else {
                    if($session) $session->destroy();
                    if ($forwhat) { 
                        do_action('continue_with_vipps_error_' .  $forwhat, 'vipps_protocol_error',__('A problem occurred when trying to use Vipps:' . ' ' . $authtoken['headers'][0], 'login-with-vipps'),'', $session);
                    }
                    wp_die($authtoken['headers'][0]);
                }
                $userinfo = $this->get_openid_userinfo($accesstoken);

                if ($userinfo['response'] != 200) {
                    if($session) $session->destroy();
                    if ($forwhat) { 
                        do_action('continue_with_vipps_error_' .  $forwhat, 'vipps_protocol_error',__('A problem occurred when trying to use Vipps:' . ' ' . $userinfo['headers'][0], 'login-with-vipps'),'', $session);
                    }
                    wp_die($userinfo['response']);
                }
                if ($userinfo['content']['sub'] != $idtoken_sub) {
                    if($session) $session->destroy();
                    if ($forwhat) { 
                        do_action('continue_with_vipps_error_' .  $forwhat, 'vipps_protocol_error',__('There is a problem with verifying your ID token from Vipps. Unfortunately, you cannot continue with Vipps at this time', 'login-with-vipps'),'', $session);
                    }
                    wp_die(__('There is a problem with verifying your ID token from Vipps. Unfortunately, you cannot continue with Vipps at this time', 'login-with-vipps'));
                }

                $userinfo = @$userinfo['content'];
                if ($session) {
                    $session->set( 'userinfo', $userinfo);
                } else {
                    do_action('continue_with_vipps_error_' .  $forwhat, 'vipps_protocol_error',__('Session expired - please retry', 'login-with-vipps'),'', $session);
                    wp_die(__('Session expired - please retry', 'login-with-vipps'));
                }
            }
        } 

        // Ok so if we get here, we have a session with the userinfo in place. Now redirect to the application code! IOK 2019-10-14

        do_action('continue_with_vipps_' .  $forwhat, $userinfo, $session);
        if($session) $session->destroy();
        wp_die(sprintf(__('You successfully completed the action "%s" using Vipps - unfortunately, this website doesn\'t know how to handle that.', 'login-with-vipps'), $forwhat ));
    }


    public function make_callback_url () {
        if ( !get_option('permalink_structure')) {
            return set_url_scheme(home_url(),'https') . "/?wp-vipps-login=continue-from-vipps&callback=";
        } else {
            return set_url_scheme(home_url(),'https') . "/wp-vipps-login/continue-from-vipps/";
        }
    }

    // This allows us to handle 'special' pages by using registered query variables; rewrite rules can be added to add the actual argument to the query.
    public function template_redirect () {
        $special = $this->is_special_page() ;
        if ($special) return $this->$special();
        return false;
    }

    // This is used to recognize the Vipps 'callback' - written like this to allow for sites wihtout pretty URLs IOK 2019-09-12
    public function is_special_page() {
        $specials = array('continue-from-vipps' => 'continue_from_vipps');
        $method = null;
        if ( get_option('permalink_structure')) {
            foreach($specials as $special=>$specialmethod) {
                // IOK 2018-06-07 Change to add any prefix from home-url for better matching IOK 2018-06-07
                if (preg_match("!/wp-vipps-login/$special/!", $_SERVER['REQUEST_URI'], $matches)) {
                    $method = $specialmethod;
                    break;
                }
            }
        } else {
            if (isset($_GET['wp-vipps-login'])) {
                $method = @$specials[$_GET['wp-vipps-login']];
            }
        }
        return $method;
    }



    /* IOK 2019-10-14 The below code is for interfacing with the Vipps API and servers  */

    // This method will get an auth-token from Vipps given that we have a code received from the oAuth2 session. IOK 2019-10-14
    private function get_auth_token($code) {
        $clientid= $this->settings['clientid'];
        $secret = $this->settings['clientsecret'];
        $redir  = $this->make_callback_url();

        $url = $this->token_endpoint();

        $headers = array();
        $headers['Authorization'] = "Basic " . base64_encode("$clientid:$secret");

        $args = array('grant_type'=>'authorization_code', 'code'=>$code, 'redirect_uri'=>$redir);

        $response = $this->http_call($url,$args,'POST',$headers,'url');
        return $response;
    }
    // And this fetches the userinfo given an accesstoken. Remember that it is obligatory to check the 'sub' from this value against the JWT sub received in the 'get_auth_token' call.  IOK 2019-10-14
    private function get_openid_userinfo($accesstoken) {
        $headers = array();
        $headers['Authorization'] = "Bearer $accesstoken";
        $url = $this->userinfo_endpoint();
        $args = array();

        return $this->http_call($url,$args,'GET',$headers,'url');
    }

    // This is the base URL to talk to the Vipps api. From this we retreive the '.well-known'-value from which we get the authorize/accesstoken/userinfo endpoints. IOK 2019-10-14
    protected function base_url() {
        return "https://api.vipps.no/access-management-1.0/access/";
    }

    // This gets and temporarily stores the keys used to verify that the idtoken (containing the 'sub' value indicating the identity of the subscriber) really ss from Vipps. IOK 2019-10-14
    protected function get_oauth_keys() {
        if ($this->oauthkeys) return $this->oauthkeys;
        $keys = get_transient('_login_with_vipps_oauth_keys');
        if ($keys) {
            $this->oauthkeys= $keys;
            return $keys;
        }
        $data =$this->get_oauth_data();
        if ($data && isset($data['jwks_uri'])) {
            $keyscontent = @file_get_contents($data['jwks_uri']);
            $keysdata = array();
            if (!empty($keyscontent)) $keysdata = json_decode($keyscontent,true);
            if (!empty($keysdata) && isset($keysdata['keys'])) {
                $this->oauthkeys = $keysdata['keys']; 
                set_transient('_login_with_vipps_oauth_keys', $this->oauthkeys, 60*60*24);
            }
        }
        return $this->oauthkeys;
    }

    // This gets and temporarily stores the endpoint URLs used to communicate with the Vipps api. IOK 2019-10-14
    protected function get_oauth_data() {
        if ($this->oauthdata) return $this->oauthdata;
        $data = get_transient('_login_with_vipps_oauth_data');
        if ($data) {
            $this->oauthdata = $data;
            return $data;
        }
        $wellknownurl = $this->base_url() . ".well-known/openid-configuration";
        $wellknowncontents= @file_get_contents($wellknownurl);
        $wellknowndata=array();
        if ($wellknowncontents) {
            $wellknowndata = @json_decode($wellknowncontents,true);
        }
        // Store for one day if possible
        set_transient('_login_with_vipps_oauth_data', $wellknowndata, 60*60*24);
        $this->oauthdata = $wellknowndata;
        return $wellknowndata;
    }
    // Returns the endpoint from which we get access and idtokens from the Vipps oAuth2 api IOK 2019-10-14 
    protected function token_endpoint() {
        $fallback = $this->base_url . "oauth2/token";  // Just in case, this is acctually the address, but let's check .well-known
        $data = $this->get_oauth_data();
        if (!empty($data)) return $data['token_endpoint'];
        return $fallback; 
    }
    protected function authorization_endpoint() {
        $fallback = $this->base_url . "oauth2/auth"; 
        $data = $this->get_oauth_data();
        if (!empty($data)) return $data['authorization_endpoint'];
        return $fallback; 
    }
    protected function userinfo_endpoint() {
        $fallback = $this->base_url . "userinfo"; 
        $data = $this->get_oauth_data();
        if (!empty($data)) return $data['userinfo_endpoint'];
        return $fallback; 
    }

    // Helper function for calling the Vipps servers.
    private function http_call($url,$data,$verb='GET',$headers=[],$encoding='url'){
        $date = date("Y-m-d H:i:s");
        $data_encoded = ''; 
        if ($encoding == 'url' || $verb == 'GET') {
            $data_encoded = http_build_query($data);
        } else {
            $data_encoded = json_encode($data);
        }  
        $data_len = strlen ($data_encoded);
        $http_response_header = null;
        $sslparams = [];

        $sslparams['verify_peer'] = true;
        $sslparams['verify_peer_name'] = true;

        $headers['Connection'] = 'close';
        if ($verb=='POST' || $verb == 'PATCH' || $verb == 'PUT') {
            if ($encoding == 'url') {
                $headers['Content-type'] = 'application/x-www-form-urlencoded';
            } else {
                $headers['Content-type'] = 'application/json';
            }
        }
        $headerstring = '';
        $hh = [];
        foreach($headers as $key=>$value) {
            array_push($hh,"$key: $value");
        }
        $headerstring = join("\r\n",$hh);
        $headerstring .= "\r\n";

        $httpparams = array('method'=>$verb,'header'=>$headerstring);
        if ($verb == 'POST' || $verb == 'PATCH' || $verb == 'PUT') {
            $httpparams['content'] = $data_encoded;
        }
        if ($verb == 'GET' && $data_encoded) {
            $url .= "?$data_encoded";
        }
        $params = ['http'=>$httpparams,'ssl'=>$sslparams];

        $context = stream_context_create($params);
        $content = null;

        $contenttext = @file_get_contents($url,false,$context);
        if ($contenttext) {
            $content = json_decode($contenttext,true);
        }
        $response = 0;
        if ($http_response_header && isset($http_response_header[0])) {
            $match = [];
            $ok = preg_match('!^HTTP/... (...) !i',$http_response_header[0],$match);
            if ($ok) {
                $response = 1 * $match[1];
            }
        }
        return array('response'=>$response,'headers'=>$http_response_header,'content'=>$content);
    }
}
