<?php 
// WooLogin: This singleton object integrates login-with-vipps with Woocommerce.

class WooLogin{
 
  function __construct() {

  }
  // We are going to do the Singleton pattern here so 
  // the basic login mechanism will be available in general with the same settings etc.
  protected static $instance = null;
  public static function instance()  {
        if (!static::$instance) static::$instance = new WooLogin();
        return static::$instance;
  }
  public function admin_init () {
    if (!class_exists( 'WooCommerce' )) return;
     // Settings, that will end up on the simple "Login with Vipps" options screen
     register_setting('vipps_login_woo_options','vipps_login_woo_options', array($this,'validate'));
     add_action('continue_with_vipps_extra_option_fields', array($this,'extra_option_fields'));
  }

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


  public function init () {
    if (!class_exists( 'WooCommerce' )) return;
    
    $options = get_option('vipps_login_woo_options');
    $woologin= $options['woo-login'];
    if ($woologin) {
       add_action( 'woocommerce_before_customer_login_form' , array($this, 'login_with_vipps_button'));
//       add_action('woocommerce_login_form_start' , array($this, 'login_with_vipps_button'));
    } else {
    }

    add_filter('continue_with_vipps_woocommerce_login_redirect', array($this, 'login_redirect'), 10, 2);
    add_filter('continue_with_vipps_woocommerce_users_can_register', array($this, 'users_can_register'), 10, 2);
    add_filter('continue_with_vipps_woocommerce_create_userdata', array($this, 'create_userdata'), 10, 3);
    add_filter('continue_with_vipps_woocommerce_create_username', array($this, 'create_username'), 10, 3);
    add_filter('continue_with_vipps_after_create_woocommerce_user', array($this, 'after_create_user'), 10, 2);
    add_filter('continue_with_vipps_woocommerce_allow_login', array($this, 'allow_login'), 10, 3);
    add_filter('continue_with_vipps_before_woocommerce_user_login', array($this, 'before_login'), 10, 3);
  }

  public function login_redirect ($redir, $session) {
      if (sizeof( WC()->cart->get_cart() ) > 0 ) {
         return wc_get_checkout_url();
      } else {
         $link = wc_get_page_permalink( 'myaccount' );
         if ($link) return $link;
         return $redir;
      }
  }
  public function users_can_register($can_register,$userinfo,$session) {
     $options = get_option('vipps_login_woo_options');
     if ($options['woo-create-users']) return true;
     return false;
  }
  public function create_userdata($userdata,$userinfo,$session) {
     $userdata['role'] = 'customer';
     return $userdata;
  }
  // We'll use woocommerces own username functionality here
  public function create_username($username, $userinfo, $sessio) {
    return wc_create_new_customer_username($email, array('first_name'=>$userinfo['given_name'],  'last_name' =>  $userinfo['family_name']));
  }
  public function after_create_user($user, $session) {
    $userinfo = @$session['userinfo'];
    if (!$userinfo) return false;
    // SET ADDRESS HERE ON THE USER OBJECT DIRECTLY FIXME FIXME
  } 
  // IOK FIXME MAKE DISALLOWING ADMIN LOGINS POSSIBLE HERE
  public function allow_login($allow, $user, $userinfo, $session) {
     return $allow;
  }
  public function before_login($user, $session) {
    $userinfo = @$session['userinfo'];
    if (!$userinfo) return false;
    // IOK FIXME This is where we can update the user with new address-info from Vipps if the user hasn't updated his or her own profile.
  }

  // To be used in a POST: returns an URL that can be used to start the login process.
  public function ajax_vipps_woo_login_get_link () {
     //NB We are not using a nonce here - the user has not yet logged in, and the page may be cached. To continue logging in, 
     // the users' browser must retrieve the url from this json value. IOK 2019-10-03
     $url = VippsLogin::instance()->get_vipps_login_link('woocommerce');
     wp_send_json(array('ok'=>1,'url'=>$url,'message'=>'ok'));
     wp_die();
  }

  public function login_with_vipps_button() {
?>
     <div style='margin:20px;' class='continue-with-vipps'>
<a href='javascript:login_with_vipps("woocommerce");' class='button' style='width:100%'>Login with Vipps yo!</a>
</div>
<?php
     return true;
  }

 function activate () {

      $allowcreatedefault = apply_filters( 'woocommerce_checkout_registration_enabled', 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout' ) );
      $allowcreatedefault = $allowcreatedefault ||  ('yes' === get_option( 'woocommerce_enable_myaccount_registration' )) ;

      $default = array('woo-login'=>true, 'woo-create-users'=>$allowcreatedefault);
      add_option('vipps_login_woo_options',$default,false);
 }
 function deactivate () {

 }

  public function extra_option_fields () {
      $options = get_option('vipps_login_woo_options');
      $woologin= $options['woo-login'];
      $woocreate = $options['woo-create-users'];
?>
<?php settings_fields('vipps_login_woo_options'); ?>
   <tr><th colspan=3><h3><?php _e('Woocommerce integration', 'login-vipps'); ?></th></tr>
   <tr>
       <td><?php _e('Use Login With Vipps for Woocommerce', 'login-vipps'); ?></td>
       <td width=30%> <input type='hidden' name='vipps_login_woo_options[woo-login]' value=0>
                      <input type='checkbox' name='vipps_login_woo_options[woo-login]' value=1 <?php if ( $woologin ) echo ' CHECKED '; ?> >
</td>
       <td>
                      <?php _e('Check this to enable Log in With Vipps on your customers pages in Woocommerce', 'login-vipps'); ?>
       </td>
   </tr>
   <tr>
       <td><?php _e('Allow users to register as customers in Woocommerce using login with Vipps', 'login-vipps'); ?></td>
       <td width=30%> <input type='hidden' name='vipps_login_woo_options[woo-create-users]' value=0>
                      <input type='checkbox' name='vipps_login_woo_options[woo-create-users]' value=1 <?php if ( $woocreate) echo ' CHECKED '; ?> >
</td>
       <td>
                      <?php _e('Check this to allow new users to be created as customers if using Log in With Vipps in a Woocommerce context', 'login-vipps'); ?>
       </td>
   </tr>
<?php
 }


}
