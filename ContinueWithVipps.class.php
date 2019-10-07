<?php 
// ContinueWithVipps: This singleton object does basically all the work 

class ContinueWithVipps {
  public $options = array();
  public $settings = array();
  public static $dbversion = 1;
 
  function __construct() {
     $this->settings = get_option('vipps_login_options', array());
  }

  // We are going to do the Singleton pattern here so 
  // the basic login mechanism will be available in general with the same settings etc.
  protected static $instance = null;
  public static function instance()  {
        if (!static::$instance) static::$instance = new ContinueWithVipps();
        return static::$instance;
  }

  public function admin_init () {
   add_action('admin_notices',array($this,'stored_admin_notices'));
   register_setting('vipps_login_options','vipps_login_options', array($this,'validate'));
   VippsSession::clean();
  }

  public function admin_menu () {
    add_options_page(__('Login with Vipps', 'login-vipps'), __('Login with Vipps','login-vipps'), 'manage_options', 'vipps_login_options',array($this,'toolpage'));
  }

  public function init () {
  }

  public function plugins_loaded () {
    $this->options =  get_option('vipps_login_options'); 
    // Just in case the tables were updated without 'activate' having been run IOK 2019-09-18
    $this->dbtables();
  }

  // Helper function for admin notices
  public function add_admin_notice($notice) {
    add_action('admin_notices', function() use ($notice) { echo "<div class='notice notice-info is-dismissible'><p>$notice</p></div>"; });
  }

  // Make admin-notices persistent so we can provide error messages whenever possible. IOK 2018-05-11
  public function store_admin_notices() {
        ob_start();
        do_action('admin_notices');
        $notices = ob_get_clean();
        set_transient('_vipps_login_save_admin_notices',$notices, 5*60);
  }

  // If we have admin-notices that we haven't gotten a chance to show because of
  // a redirect, this method will fetch and show them IOK 2018-05-07
  public function stored_admin_notices() {
        $stored = get_transient('_vipps_login_save_admin_notices');
        if ($stored) {
            delete_transient('_vipps_login_save_admin_notices');
            print $stored;
        }
  }


  // For a given redirect (operation, actually) and code (nonce, as returned from the Auth call), get an auth token
  private function get_auth_token($code) {
      $clientid= $this->settings['clientid'];
      $secret = $this->settings['clientsecret'];
      $redir  = $this->make_callback_url();

      # IOK FIXME better use .wellknown for this
      $url = $this->backendUrl("oauth2/token");

      $headers = array();
      $headers['Authorization'] = "Basic " . base64_encode("$clientid:$secret");

      $args = array('grant_type'=>'authorization_code', 'code'=>$code, 'redirect_uri'=>$redir);

      return $this->http_call($url,$args,'POST',$headers,'url');
  }

  private function get_openid_userinfo($accesstoken) {
      $headers = array();
      $headers['Authorization'] = "Bearer $accesstoken";
      # IOK FIXME better use .wellknown for this
      $url = $this->backendUrl("userinfo");
      $args = array();

      return $this->http_call($url,$args,'GET',$headers,'url');
  }

  public static function getAuthRedirect($action,$sessiondata=null,$scope="openid address birthDate email name phoneNumber") {
    $me = static::instance();
    $url      = $me->backendUrl('oauth2/auth');
    $redir    = $me->make_callback_url();
    $clientid = $me->settings['clientid'];
    if (is_array($scope)) $scope = join(' ',$scope);

    if (!is_array($sessiondata)) $sessiondata = array();
    $sessiondata['action'] = $action;
    $session = VippsSession::create($sessiondata);
    $sessionkey = $session->sessionkey;
    $state = $action . "::" . $sessionkey;

    if (!$clientid) return "";
    $args = array('client_id'=>$clientid, 'response_type'=>'code', 'scope'=>$scope, 'state'=>$state, 'redirect_uri'=>$redir);
    return $url . '?' . http_build_query($args);
  }

  // Return the  URL to the oAuth-backend.
  public function backendUrl ($command) {
    return "https://api.vipps.no/access-management-1.0/access/" . $command;
    return "https://apitest.vipps.no/access-management-1.0/access/" . $command;
//    return "https://api.vipps.no/access-management-1.0/access/" . $command;
  }

  // This is the actual handler for all returns from Vipps; it's extendable by using standard Wordpress actions/filters
  public function continue_from_vipps () {
        wc_nocache_headers();
        // Actually, we're totally going to redirect somewhere here. FIXME

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

        $forwhat = $action; // Should also be set in the session
        $userinfo = null;

        if ($error) {
          // Delete this - you may need to create a new session in your errorhandler.
          if($session) $session->destroy();
          do_action('continue_with_vipps_error_' .  $forwhat, $error,$errordesc,$error_hint);
          wp_die(sprintf(__("Unhandled error when using Continue with Vipps for action %s: %s", 'login-vipps'), esc_html($forwhat), esc_html($error)));
        }

        $code =  @$_REQUEST['code'];
        $scope = @$_REQUEST['scope'];
 
        $accesstoken = null;
    
        if ($session && $session['userinfo']) {
          $userinfo = $session['userinfo'];
        }  else {
         if ($code) {
          $authtoken = $this->get_auth_token($code);
          if (isset($authtoken['content']) && isset($authtoken['content']['access_token'])) {
              $accesstoken = $authtoken['content']['access_token'];
          } else {
              if($session) $session->destroy();
              if ($forwhat) { 
               do_action('continue_with_vipps_error_' .  $forwhat, 'vipps_protocol_error',__('A problem occurred when trying to use Vipps:' . ' ' . $authtoken['headers'][0], 'login-vipps'),'');
              }
              wp_die($authtoken['headers'][0]);
          }
          $userinfo = $this->get_openid_userinfo($accesstoken);

          if ($userinfo['response'] != 200) {
            # FIXME ERRORHANDLING 
            if($session) $session->destroy();
            if ($forwhat) { 
              do_action('continue_with_vipps_error_' .  $forwhat, 'vipps_protocol_error',__('A problem occurred when trying to use Vipps:' . ' ' . $userinfo['headers'][0], 'login-vipps'),'');
            }
            wp_die($userinfo['response']);
          }
          $userinfo = @$userinfo['content'];
          $session->set( 'userinfo', $userinfo);
         }
        } 
 

        // Do *not* destroy the session here: this may redirect to other pages (eg. in 'authenticate' that will need to have the session
        // be alive so that this can be called repeatedly. The session should be destroyed only when the action is done; that is, completes whatever it tries to do.
        do_action('continue_with_vipps_' .  $forwhat, $userinfo, $session);
        if($session) $session->destroy();
        wp_die(sprintf(__('You successfully completed the action "%s" using Vipps - unfortunately, this website doesn\'t know how to handle that.', 'login-vipps'), $forwhat ));
  }

  public function make_callback_url () {
      if ( !get_option('permalink_structure')) {
            return set_url_scheme(home_url(),'https') . "/?wp-vipps-login=continue-from-vipps&callback=";
          } else {
            return set_url_scheme(home_url(),'https') . "/wp-vipps-login/continue-from-vipps/";
         }
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


 // Just a very compatbile way to do REST
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

//     $sslparams['verify_peer'] = false;
//     $sslparams['verify_peer_name'] = false;

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

  // This allows us to handle 'special' pages by using registered query variables; rewrite rules can be added to add the actual argument to the query.
  public function template_redirect () {
        $special = $this->is_special_page() ;
        if ($special) return $this->$special();
        return false;
  }

  public function activate () {
          // Options
          $default = array('clientid'=>'','clientsecret'=>'', 'dbversion'=>0);
	  add_option('vipps_login_options',$default,false);
          $this->dbtables();

  }

  public function deactivate () {
  }


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

  // We need helper tables to find the products given the Smartstore product information.
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
// varchar just in case.
// https://codex.wordpress.org/Creating_Tables_with_Plugins
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
                  $this->add_admin_notice(__('Could not create session table - Login With Vipps plugin is not correctly installed', 'login-vipps'));
                } else {
                  error_log(__("Installed database tables for Login With Vipps", 'login-vipps'));
                  $options['dbversion']=static::$dbversion;
                  update_option('vipps_login_options',$options,false);
                }
                return true;
  }


  public function toolpage () {
    if (!is_admin() || !current_user_can('manage_options')) {
      die(__("Insufficient privileges",'login-vipps'));
  }
  $options = get_option('vipps_login_options'); 


?>
<div class='wrap'>
 <h2><?php _e('Login With Vipps', 'vipps-login'); ?></h2>


<?php do_action('admin_notices'); ?>

<form action='options.php' method='post'>
<?php settings_fields('vipps_login_options'); ?>
 <table class="form-table" style="width:100%">

   <tr>
       <td><?php _e('Client ID', 'login-vipps'); ?></td>
       <td width=30%><input id=configpath style="width:20em" name="vipps_login_options[clientid]" value="<?php echo htmlspecialchars($options['clientid']);?>" type="text"></td>
       <td><?php _e('Your client ID, from the Vipps Portal','vipps-login'); ?></td>
   </tr>
   <tr>
       <td><?php _e('Client Secret', 'login-vipps'); ?></td>
       <td width=30%><input id=configpath style="width:20em" name="vipps_login_options[clientsecret]" value="<?php echo htmlspecialchars($options['clientsecret']);?>" type="xpassword"></td>
       <td><?php _e('Your client secret, from the Vipps Portal','vipps-login'); ?></td>
   </tr>

<?php do_action('continue_with_vipps_extra_option_fields'); ?>

 </table>
  <div>
   <input type="submit" style="float:left" class="button-primary" value="<?php _e('Save Changes') ?>" />
  </div>
 </div>
</form>


</div>

<?php
  }


}
