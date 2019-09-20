<?php 
// VippsLogin: This singleton object does basically all the work 

class VippsLogin {
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
        if (!static::$instance) static::$instance = new VippsLogin();
        return static::$instance;
  }

  public function admin_init () {

   add_action('admin_notices',array($this,'stored_admin_notices'));

   register_setting('vipps_login_options','vipps_login_options', array($this,'validate'));
   add_action('show_user_profile', array($this,'show_extra_profile_fields'));
   add_action('show_user_profile', array($this,'show_profile_subscription'));
   add_action('wp_ajax_vipps_login_get_link', array($this,'ajax_vipps_login_get_link'));
   add_action('wp_ajax_nopriv_vipps_login_get_link', array($this,'ajax_vipps_login_get_link'));

   $this->cleanSessions();
  }

  public function admin_menu () {
    add_options_page(__('Login with Vipps', 'login-vipps'), __('Login with Vipps','login-vipps'), 'manage_options', 'vipps_login_options',array($this,'toolpage'));


     // This is for creating a page for admins to manage user confirmations. It's not needed here, so this line is just information.
     // add_management_page( 'Show user confirmations', 'Show user confirmations!', 'install_plugins', 'vipps_connect_login', array( $this, 'show_confirmations' ), '' );

    if (current_user_can('manage_options')) {
         $uid = isset($_REQUEST['user_id']) ? intval($_REQUEST['user_id']) : 0;
         if ($uid>0 && current_user_can('edit_user', $uid)) {
             add_action( 'edit_user_profile_update', array($this,'save_extra_profile_fields'));
             add_action( 'edit_user_profile', array($this,'show_extra_profile_fields'));
         }
    }
  }

  public function init () {
    add_filter('authenticate', array($this,'authenticate'),10,3); 

    // Profile updates for customers 
    add_action('personal_options_update',array($this,'profile_update'));
    add_action('edit_user_profile_update',array($this,'profile_update'));

    add_action('user_profile_update_errors', array($this,'user_profile_update_errors'), 10,3);

    add_filter('email_change_email', array($this, 'email_change_email'), 10, 3);

    add_action('login_form', array($this, 'login_form_continue_with_vipps'));
    add_action( 'login_enqueue_scripts', array($this,'login_enqueue_scripts' ));

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
        set_transient('_vipps_login__save_admin_notices',$notices, 5*60);
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

  // To be used in a POST: returns an URL that can be used to start the login process.
  public function ajax_vipps_login_get_link () {
     check_ajax_referer ('vippslogin','vlnonce',true);
     $session = $this->createSession(array('action'=>'login'));
     $url = $this->getAuthRedirect($session);
     wp_send_json(array('ok'=>1,'url'=>$url,'message'=>'ok'));
     wp_die(); 
  }

  public function getAuthRedirect($state,$scope="openid address birthDate email name phoneNumber") {
    $url      = $this->backendUrl('oauth2/auth');
    $redir    = $this->make_callback_url();
    $clientid = $this->settings['clientid'];
    if (is_array($scope)) $scope = join(' ',$scope);

    if (!$clientid) return "";
    $args = array('client_id'=>$clientid, 'response_type'=>'code', 'scope'=>$scope, 'state'=>$state, 'redirect_uri'=>$redir);
    return $url . '?' . http_build_query($args);
  }

  // Return the  URL to the oAuth-backend.
  public function backendUrl ($command) {
    return "https://apitest.vipps.no/access-management-1.0/access/" . $command;
//    return "https://api.vipps.no/access-management-1.0/access/" . $command;
  }

  // IOK FIXME REPLACE THIS WITH SOME NICE STUFF
  public function login_enqueue_scripts() {
    wp_enqueue_script('jquery');
  }
  public function login_form_continue_with_vipps () {
     $session = $this->createSession(array('action'=>'login'));
     $url = $this->getAuthRedirect($session);
     if (!$url) return;
?>
     <div style='margin:20px;' class='continue-with-vipps'>
<script>
 function login_with_vipps() {
    var nonce = '<?php echo wp_create_nonce('vippslogin'); ?>';
    var ajaxUrl = '<?php echo admin_url('/admin-ajax.php'); ?>';
    jQuery.ajax(ajaxUrl, {
       data: { 'action': 'vipps_login_get_link', 'vlnonce' : nonce },
       dataType: 'json',
       error: function (jqXHR,textStatus,errorThrown) {
           alert("Error " + textStatus);
       },
       success: function(data, textStatus, jqXHR) {
         if (data && data['url']) {
           window.location.href = data['url'];
         }
       }

    });
 }
</script>
<a href='javascript:login_with_vipps();' class='button' style='width:100%'>Login with Vipps yo!</a>
</div>
<?php
     return true;
  }

  // This is the actual handler for all returns from Vipps; it's extendable by using standard Wordpress actions/filters
  public function continue_from_vipps () {
        wc_nocache_headers();
        // Actually, we're totally going to redirect somewhere here. FIXME

        $state = @$_REQUEST['state'];
        $error = @$_REQUEST['error'];
        $errordesc = @$_REQUEST['error_description'];
        $error_hint = @$_REQUEST['error_hint'];

        $session = $this->getSession($state);

        $forwhat = @$session['action'];
        $userinfo = null;

/*
An error has occured during login with Vipps:
access_denied
User cancelled the login
*/

        if (!empty($error)) {
          $errormessage = "<h1>" . __('An error has occured during login with Vipps:', 'login-vipps') . "</h1>";
          $errormessage .= "<p> state " . sanitize_text_field($state) . "</p>";
          $errormessage .= "<p>" . sanitize_text_field($error) . "</p>";
          $errormessage .= "<p>" . sanitize_text_field($errordesc) . "</p>";
          $errormessage .= "<p>" . sanitize_text_field($error_hint) . "</p>";
          if($state) $this->deleteSession($state);
          wp_die($errormessage);
        }

        $code =  @$_REQUEST['code'];
        $state = @$_REQUEST['state'];
        $scope = @$_REQUEST['scope'];
 
        $accesstoken = null;
    
        if (isset($session['userinfo'])) {
          $userinfo = $session['userinfo'];
        }  else {
         if ($code) {
          $authtoken = $this->get_auth_token($code);
          if (isset($authtoken['content']) && isset($authtoken['content']['access_token'])) {
              $accesstoken = $authtoken['content']['access_token'];
          } else {
              if($state) $this->deleteSession($state);
              wp_die($authtoken['headers'][0]);
          }
           // Errorhandling! FIXME
          $userinfo = $this->get_openid_userinfo($accesstoken);
          $this->setSession($state, 'userinfo', $userinfo);
         }
        } 

        if ($userinfo && $userinfo['response'] ==  200 && !empty($userinfo['content'])) {
           $userinfo = $userinfo['content'];
           $email = $userinfo['email'];
           $name = $userinfo['name'];
           $username = sanitize_user($email);
           $lastname = $userinfo['family_name'];
           $firstname =  $userinfo['given_name'];
           $phone =  $userinfo['phone_number'];
         
           $address = $userinfo['address'][0];
           foreach($userinfo['address'] as $add) {
              if ($add['address_type'] == 'home') {
                 $address = $add; break;
              }
           }
           $user = get_user_by('email',$email);
           if (!$user) {
               print "New user - start registration process if allowed<br>";
           } else {
            $vippsphone = get_usermeta($user->ID,'_vipps_phone');
            if (false && $vippsphone == $phone) {
                 wp_set_auth_cookie($user->ID, false);
                 wp_set_current_user($user->ID,$user->user_login); // 'secure'
                 do_action('wp_login', $user->user_login, $user);
                 $profile = get_edit_user_link($user->ID);
                 $redir = apply_filters('login_redirect', $profile,$profile, $user);
                 wp_safe_redirect($redir, 302, 'Vipps');
                          
                 exit();

            } else {
                // Create a session with a secret word, store this etc.
                print "'$phone' '$email'<br>";
                // $requestid = wp_create_user_request($email,'vipps_connect_login', array('email'=>$email,'vippsphone'=>$phone, 'userid'=>$user->ID));
//wp_send_user_request(825);
                print "This being your first login, we have sent you an email - confirm this and you can continue<br>";
            }
          }
       }

        // Or preferrably, *filter* this so we can return 'handled' or not handled.
        // So this is what actually will do the several actions.
        do_action('continue_with_vipps_' .  $forwhat, $session, $userinfo);

        wp_die('Hooray you got here!');
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


  // Hm. FIXME checkit
  public function email_change_email($email_change_email, $user, $userdata) {
   return $email_change_email;
  }

  // Called when both admin and the user saves his or her profile page. IOK 2017-04-27
  public function profile_update($userid) {
   return $this->profile_update_vipps_fields($userid, get_edit_user_link());
  }

  public function profile_update_vipps_fields($userid,$backlink) {
   if (!get_user_meta($userid, 'vipps_login', true)) return;
   // IOK FIXME UPDATE fields here
   return true;
  }

  // Remove all twitter, facebook etc fields
  public function user_contactmethods ($profile_fields) {
   return $profile_fields;
  }

  function user_profile_update_errors (&$errors, $update = null, &$user  = null) {
   if (!$update) return;
   // $errors->remove('empty_email'); # IOK FIXME probably remove
   return;
  }

  //  Don't show admin bar to non-admins
  public function after_setup_theme () {
  }

  public function plugins_loaded () {
    $this->options =  get_option('vipps_login_options'); 
    add_action('wp_logout', array($this,'wp_logout'),10,3); 
    // Just in case the tables were updated without 'activate' having been run IOK 2019-09-18
    $this->dbtables();
  }

  public function wp_logout () {
   $user = wp_get_current_user();
   if (!empty($user)) {
      if (get_user_meta($user->ID, 'vipps_login', true)) {
          // Kill cookies maybe, or note that this user doesn't want to autologin FIXME
      }
   }
  }

  public function login_redirect($redirect_to,$requested_redirect_to=null,$user=null) {
    // For some reason this might be called with no user.
    if (empty($user)) $user = wp_get_current_user();
    if (empty($user)) return $redirect_to;
    if (!get_user_meta($user->ID, 'vipps_login', true)) return $redirect_to;
 
    return $redirect_to;
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

  // External filter for checking  user
  public function authenticate ($user,$username,$password) {
      if (!$username) return $user;
      if ($user) return $user;

       // If this is a Vipps-only-login, then ensure login is via Vipps
       $existing = get_user_by('login',$username);
       if ($existing && !$existing->ID) $existing = null;

      // IOK FIXME let's see what we need to tdo
      // add_filter('login_redirect', array($this,'login_redirect'));
       return $user;
   }


  // update user meta and main user info from vipps at login
  public function addUserMeta($user,$externaldata) {
    $id = $user->ID; 
    update_user_meta($id, 'vipps_login', 1); 
    update_user_meta($id, 'vipps_email', $externaldata['email']);
    update_user_meta($id,'vipps_last_login', time());
    update_user_meta($id,'vipps_login_count', $count+1);

    $mainkeys = array('first_name','last_name','display_name','nickname','user_nicename');
    $main = array('ID'=>$id);
    foreach($mainkeys as $key) {
     $main[$key] = $external[$key];
    }
    wp_update_user($main);
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

  // Create a new fresh session
  public function createSession($content=array(),$expire = 3600) {
      global $wpdb;
      // Default expire is 3600
      if (!$expire || !is_int($expire)) $expire = 3600;
      $expiretime = gmdate('Y-m-d H:i:s', time() + $expire);
      
      $randombytes = random_bytes(256);
      $hash = hash('sha256',$randombytes,true);
      $sessionkey = base64_encode($hash);
      $ok = false;
      $count = 0;
      $tablename = $wpdb->prefix . 'vipps_login_sessions';
      $content = json_encode($content);
      // If there is a *collision* in the sessionkey, something is really weird with the universe, but hey: try 1000 times. IOK 2019-09-18
      while ($count < 1000 && !$wpdb->insert($tablename,array('state'=>$sessionkey,'expire'=>$expiretime,'content'=>$content), array('%s','%s','%s'))) {
         $count++;
         $sessionkey = base64_encode(hash('sha256',random_bytes(256), true));
      }
      $this->cleanSessions();
      return $sessionkey; 
  }

  public function deleteSession($session) {
      global $wpdb;
      $tablename = $wpdb->prefix . 'vipps_login_sessions';
      $q = $wpdb->prepare("DELETE FROM `{$tablename}` WHERE state = %s ", $session);
      $wpdb->query($q);
  }

  public function cleanSessions() {
      // Delete old sessions.
      global $wpdb;
      $tablename = $wpdb->prefix . 'vipps_login_sessions';
      $q = $wpdb->prepare("DELETE FROM `{$tablename}` WHERE expire < %s ", gmdate('Y-m-d H:i:s', time()));
      $wpdb->query($q);
  }
  public function getSession($session,$key=false) {
     global $wpdb;
     $tablename = $wpdb->prefix . 'vipps_login_sessions';
     $q = $wpdb->prepare("SELECT content FROM `{$tablename}` WHERE state=%s", $session);
     $exists = $wpdb->get_var($q);
     if (!$exists) return false;
     $content = json_decode($exists,true);
     if ($key) return (isset($content[$key]) ? $content[$key] : false);
     return $content;
  }
  public function updateSession($key,$data,$expire=0) {
    global $wpdb;
    $newexpire = "";
    if (intval($expire)) $newexpire = gmdate('Y-m-d H:i:s', time() + $expire);
    $newcontent = json_encode($data);
 
    $tablename = $wpdb->prefix . 'vipps_login_sessions';
    $q = "";
    if ($newexpire) {
      $q = $wpdb->prepare("UPDATE `{$tablename}` SET content=%s,expire=%s WHERE state=%s", $newcontent,$newexpire, $key);
    } else { 
      $q = $wpdb->prepare("UPDATE `{$tablename}` SET content=%s WHERE state=%s", $newcontent, $key);
    }
    $wpdb->query($q);
    return $data;
  }
  public function setSession($session,$key,$value) {
    $content = $this->getSession($session);
    if (!is_array($content)) return false;
    $content[$key] = $value;
    $this->updateSession($session,$content);
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

 </table>
  <div>
   <input type="submit" style="float:left" class="button-primary" value="<?php _e('Save Changes') ?>" />
  </div>
 </div>
</form>


</div>

<?php
  }


  function save_extra_profile_fields( $userid ) {
    if (!current_user_can('edit_user',$userid)) return false;
    $is_customer = intval(trim($_POST['vipps_login']));
    update_user_meta( $userid, 'vipps_login', $is_customer);

    if ($is_customer) {
      foreach($_POST as $key => $v) {
         if (preg_match("!^vipps_customer_!",$key)) {
           $value = sanitize_text_field($v);
           update_user_meta($userid,$key,$value);
         }
      }
    }
 
  }

  function show_extra_profile_fields( $user ) {
    $is_customer = get_user_meta($user->ID, 'vipps_login', true);
 ?>
   <h3><?php _e('Login with Vipps enabled', 'vipps-login'); ?></h3>
    <table class="form-table">
     <tr>
      <th><label for="vipps_customer"><?php _e('Login with Vipps','vipps-login'); ?></label></th>
       <td>
        <span id='vipps_login' class="description"><?php _e('This user logs in with Vipps', 'vipps-login'); ?></span><br>
       </td>
     </tr>
<?php if ($is_customer): ?>
<?php endif; ?>
    </table>
<?php }


}
