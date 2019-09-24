<?php 
// VippsLogin: This singleton object does basically all the work 

class VippsLogin {
 
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
    // This is for creating a page for admins to manage user confirmations. It's not needed here, so this line is just information.
    // add_management_page( 'Show user confirmations', 'Show user confirmations!', 'install_plugins', 'vipps_connect_login', array( $this, 'show_confirmations' ), '' );
    if (current_user_can('manage_options')) {
         $uid = isset($_REQUEST['user_id']) ? intval($_REQUEST['user_id']) : 0;
         if ($uid>0 && current_user_can('edit_user', $uid)) {
             add_action( 'edit_user_profile_update', array($this,'save_extra_profile_fields'));
             add_action( 'edit_user_profile', array($this,'show_extra_profile_fields'));
         }
    }

     add_action('show_user_profile', array($this,'show_extra_profile_fields'));
     add_action('show_user_profile', array($this,'show_profile_subscription'));

     // Settings, that will end up on the simple "Login with Vipps" options screen
     register_setting('vipps_login_options2','vipps_login_options2', array($this,'validate'));
     add_action('continue_with_vipps_extra_option_fields', array($this,'extra_option_fields'));

  }


  public function validate ($input) {
   $current =  get_option('vipps_login_options2');

   $valid = array();
   foreach($input as $k=>$v) {
     switch ($k) {
      default:
       $valid[$k] = $v;
     }
   }
   return $valid;
  }



  public function init () {
    // Hook into standard auth and logout
    add_filter('authenticate', array($this,'authenticate'),10,3); 
    add_action('wp_logout', array($this,'wp_logout'),10,3); 
    // Profile updates for customers 
    add_action('personal_options_update',array($this,'profile_update'));
    add_action('edit_user_profile_update',array($this,'profile_update'));
    add_action('user_profile_update_errors', array($this,'user_profile_update_errors'), 10,3);


    // Login form button
    add_action('login_form', array($this, 'login_form_continue_with_vipps'));
    add_action( 'login_enqueue_scripts', array($this,'login_enqueue_scripts' ));

    // Ajax code to get the redir url
    add_action('wp_ajax_vipps_login_get_link', array($this,'ajax_vipps_login_get_link'));
    add_action('wp_ajax_nopriv_vipps_login_get_link', array($this,'ajax_vipps_login_get_link'));


    // THE CONFIRM HANDLING IOK FIXME 
    // handler
    add_action('user_request_action_confirmed', array($this, 'confirm_vipps_connect_and_login'), 11); // Should happen before 12; the reuest is updated at 10

    // The user email
    add_filter('user_request_action_description', array($this, 'confirm_vipps_connect_and_login_description'), 10, 2);
    add_filter('user_request_action_email_content', array($this, 'confirm_vipps_connect_and_login_email_content'), 10, 2);
    add_filter('user_request_action_email_subject', array($this, 'confirm_vipps_connect_and_login_email_subject'), 10, 3);

    // The admin email (not used)
    add_filter( 'user_confirmed_action_email_content', array($this, 'user_confirmed_vipps_connection_email_content'), 10, 2);
    add_filter( 'user_request_confirmed_email_subject', array($this, 'user_confirmed_vipps_connection_email_subject'), 10, 3);

    // Main return handler
    add_action('continue_with_vipps_login', array($this, 'continue_with_vipps_login'), 10, 2);
    add_action('continue_with_vipps_error_login', array($this, 'continue_with_vipps_error_login'), 10, 4);


  }

  // To be used in a POST: returns an URL that can be used to start the login process.
  public function ajax_vipps_login_get_link () {
     check_ajax_referer ('vippslogin','vlnonce',true);
     $continue = ContinueWithVipps::instance();
     $session = $continue->createSession(array('action'=>'login'));
     $url = $continue->getAuthRedirect($session);
     wp_send_json(array('ok'=>1,'url'=>$url,'message'=>'ok'));
     wp_die();
  }


#### START CONFIRMATION
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

       // Check post author, check email etc IOK FIXME
       $vippsphone = update_user_meta($userid,'_vipps_phone',$phone);

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

  public function confirm_vipps_connect_and_login_description ($desc, $action) {
      if ($action !== 'vipps_connect_login') return $desc;
      return __('Connect your Vipps account', 'login-vipps');
  }
  public function confirm_vipps_connect_and_login_email_content ($email_text, $email_data) {
       if ($email_data->request->action_name !== 'vipps_connect_login') return $email_text;
       return $email_text; // Bleh
  }
  public function confirm_vipps_connect_and_login_email_subject ($subject,$sitename,$email_data) {
       if ($email_data->request->action_name !== 'vipps_connect_login') return $subject;
       return sprintf(__('Confirm that you want to connect your Vipps account on %s', 'login-vipps'), $sitename); 
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

#### END CONFIRMATION
  public function continue_with_vipps_error_login($error,$errordesc,$error_hint, $session) {
    print "Error $error!";
    print "<div>$errordesc</div>";
  }

  public function continue_with_vipps_login($session,$userinfo) {

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


           if (!$user)  {
            if (get_option('users_can_register')) {
               // Fix username here so it's unique
               $pass = wp_generate_password( 32, true);
	       $user_id = wp_create_user( $username, $random_password, $email);
               // Errorhandling
               $userdata = array('ID'=>$user_id, 'user_nicename'=>$name, 
                                 'nickname'=>$firstname, 'first_name'=>$firstname, 'last_name'=>$lastname,
                                 'user_registered'=>date('Y-m-d H:i:s'));
               // If Woo here, also set address fields etc.
               wp_update_user($userdata);
               update_user_meta($user_id,'_vipps_phone',$phone);
               $user = get_user_by('id', $user_id);
               wp_set_auth_cookie($user->ID, false);
               wp_set_current_user($user->ID,$user->user_login); // 'secure'
               do_action('wp_login', $user->user_login, $user);
               wp_new_user_notification($user->ID, null, 'both');
               $profile = get_edit_user_link($user->ID);
               $redir = apply_filters('login_redirect', $profile,$profile, $user);

// create welcome message

               wp_safe_redirect($redir, 302, 'Vipps');
               exit();
               print "New user - start registration process if allowed<br>";
            } else {
               wp_die("Fant ingen bruker med eposten du er registrert med - kan ikke logge inn.");
            }
           } else {
            $vippsphone = get_usermeta($user->ID,'_vipps_phone');
            if ($vippsphone == $phone) {
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
                 // First check that we didn't already send one to this email, if we did, mark as failed
                 $requestid = wp_create_user_request($email,'vipps_connect_login', array('email'=>$email,'vippsphone'=>$phone, 'userid'=>$user->ID));
                 if (is_wp_error($requestid)) {
                   // and -> errors contain ''duplicate_request'
                   print "<pre>";print_r($requestid); print "</pre>";
                 }

                 wp_update_post(array('ID'=>$requestid, 'post_author'=>$user->ID));
                 wp_send_user_request($requestid); // ERRORHANDLE!
                print "This being your first login, we have sent you an email - confirm this and you can continue<br>";
            }
          }
       }
  }

  // IOK FIXME REPLACE THIS WITH SOME NICE STUFF
  public function login_enqueue_scripts() {
    wp_enqueue_script('jquery');
  }
  public function login_form_continue_with_vipps () {
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

  public function authenticate ($user, $username, $password) {
     return $user;
  }

  public function wp_logout () {
   $user = wp_get_current_user();
   error_log("User logging out");
  }

  public function extra_option_fields () {
  $options = get_option('vipps_login_options2');


?>
<?php settings_fields('vipps_login_options2'); ?>
   <tr>
       <td><?php _e('TEST', 'login-vipps'); ?></td>
       <td width=30%><input id=configpath style="width:20em" name="vipps_login_options2[TEST]" value="<?php echo htmlspecialchars($options['TEST']);?>" type="text"></td>
       <td><?php _e('TEST FIELD','vipps-login'); ?></td>
   </tr>
<?php
 }


}
