<?php 
// WooLogin: This singleton object integrates login-with-vipps with Woocommerce.

class WooLogin{
 
  function __construct() {

  }
  // We are going to do the Singleton pattern here so 
  // the basic login mechanism will be available in general with the same settings etc.
  protected static $instance = null;

  protected $loginbuttonshown = 0;

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
       add_action('woocommerce_before_customer_login_form' , array($this, 'login_with_vipps_button'));
       add_action('woocommerce_login_form_start' , array($this, 'login_with_vipps_button'));
       add_action('woocommerce_register_form_start' , array($this, 'login_with_vipps_button'));
    } else {
    }

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

  }

  // Error handling doesn't require an extra session for Woocommerce. 2019-10-08
  public function login_error_create_session($createSession, $sessiondata) {
    return false;
  }

  public function add_woocommerce_error ($error, $errordesc, $errorhint, $session) {
     // We can add woocommerce already here, as Woocommerce handles the session itself  IOK 2019-10-08
     wc_add_notice(__($errordesc,'login-vipps'),'error');
  }

  // Handle errors for the 'woocommerce' login application on the users home
  public function error_redirect ($redir, $error, $session) {
         // If this happend on the checkout page then redirect there I guess
         $link = wc_get_page_permalink( 'myaccount' );
         if ($link) return $link;
         return $redir;
  }

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
    $this->maybe_update_address_info($user,$userinfo);
  } 
  // IOK FIXME MAKE DISALLOWING ADMIN LOGINS POSSIBLE HERE
  public function allow_login($allow, $user, $userinfo, $session) {
     return $allow;
  }
  public function before_login($user, $session) {
    $userinfo = @$session['userinfo'];
    if (!$userinfo) return false;
    $this->maybe_update_address_info($user,$userinfo);
  }

  // IOK 2019-10-04 normally we want to update the users' address every time we log in, because this allows Vipps to be the repository of the users' address.
  // However, if the user has changed his or her address in woo itself, we will let it stay as it is.
  public function maybe_update_address_info($user, $userinfo) {
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

     // User haven't updated their billing address, so reset it from Vipps now.
     if (!get_user_meta($user-ID,'_vipps_billing_address_changed', 1)) {
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
      }
     // Same for shipping adddresses
     if (!get_user_meta($user-ID,'_vipps_shipping_address_changed', 1)) {
        $customer->set_shipping_first_name($firstname);
        $customer->set_shipping_last_name($lastname);
        $customer->set_shipping_address_1($street_address);
//        $customer->set_shipping_address_2($addressline2);
        $customer->set_shipping_city($region);
        $customer->set_shipping_state('');
        $customer->set_shipping_postcode($postal_code);
        $customer->set_shipping_country($country);
      }

     $customer->save();
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
     // We'll only show this button once on a page IOK 2019-10-08
     if ($this->loginbuttonshown) return;
     $this->loginbuttonshown=1;

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
