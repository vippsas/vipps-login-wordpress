<?php 

class VippsLogin {
  public $options = array();
  public $settings = array();
 
  function __construct() {
  }

  // We are going to do the Singleton pattern here so 
  // the basic login mechanism will be available in general with the same settings etc.
  protected static $instance = null;
  public static function instance()  {
        if (!static::$instance) static::$instance = new VippsLogin();
        return static::$instance;
  }

  public function admin_init () {
   register_setting('vipps_login_options','vipps_login_options', array($this,'validate'));
   add_action('show_user_profile', array($this,'show_extra_profile_fields'));
   add_action('show_user_profile', array($this,'show_profile_subscription'));
  }

  public function admin_menu () {
    add_options_page(__('Login with Vipps', 'login-vipps'), __('Login with Vipps','login-vipps'), 'manage_options', 'vipps_login_options',array($this,'toolpage'));

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

    if (!is_admin()) {
     add_filter('page_template',array($this,'page_template'));
    }

    // Profile updates for customers 
    add_action('personal_options_update',array($this,'profile_update'));
    add_action('edit_user_profile_update',array($this,'profile_update'));

    add_action('user_profile_update_errors', array($this,'user_profile_update_errors'), 10,3);

    add_filter('email_change_email', array($this, 'email_change_email'), 10, 3);

    add_action('login_form', array($this, 'login_form_continue_with_vipps'));

  }

  public function login_form_continue_with_vipps () {
     echo "<div style='margin:20px;' class='continue-with-vipps'><input style='width:100%' type=button value='Login with Vipps yo!'/></div>";
  }

  public function continue_from_vipps () {
        wc_nocache_headers();
        // Actually, we're totally going to redirect somewhere here. FIXME
        status_header(200,'OK');
        wp_die('Hooray you got here!');
  }

  public function make_callback_url ($forwhat) {
      if ( !get_option('permalink_structure')) {
            return set_url_scheme(home_url(),'https') . "/?wp-vipps-login=$forwhatk&callback=";
          } else {
            return set_url_scheme(home_url(),'https') . "/wp-vipps-login/$forwhat?&callback=";
         }
  }

  // This is used to recognize the Vipps 'callback' - written like this to allow for sites wihtout pretty URLs IOK 2019-09-12
   public function is_special_page() {
        $specials = array('continue-from-vipps' => 'continue_from_vipps');
        $method = null;
        if ( get_option('permalink_structure')) {
            foreach($specials as $special=>$specialmethod) {
                // IOK 2018-06-07 Change to add any prefix from home-url for better matching IOK 2018-06-07
                if (preg_match("!/wp-vipps-login/$special/([^/]*)!", $_SERVER['REQUEST_URI'], $matches)) {
                    $method = $specialmethod; break;
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
          $default = array('configpath'=>'');
	  add_option('vipps_login_options',$default,false);
  }

  public function deactivate () {
  }

  // FIXME move this to separate file I guess
  public static function uninstall() {
  }

  public function validate ($input) {
   $current =  get_option('vipps_login_options'); 

   $valid = array();
   foreach($input as $k=>$v) {
     switch ($k) {
      case 'connect': 
       $this->connecting=true;
       break;
      default: 
       $valid[$k] = $v;
     }
   }
   return $valid;
  }

  public function toolpage () {
    if (!is_admin() || !current_user_can('manage_options')) {
      die(__("Insufficient privileges",'login-vipps'));
  }
  $options = get_option('vipps_login_options'); 

  $clientid = $options['clientid'];

  $connected = false;
  if (!$connected) {
    add_action('admin_notices',function () {
       echo "<div class='notice notice-info is-dismissible'><p>Not connected to Beat backend - users cannot log in</p></div>";
     });
  }

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
 </table>
  <div>
   <input type="submit" style="float:left" class="button-primary" value="<?php _e('Save Changes') ?>" />
  </div>
 </div>
</form>

<div class="wrap">
 <form name="connectform" method="post">
   <input <?php if (!$keysok) print "disabled" ?> type="submit" style="float:right" name="connect" class="button-primary" value="<?php _e('Connect!','login-vipps') ?>" />
 </form>
</div>

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
