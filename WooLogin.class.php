<?php 
// WooLogin: This singleton object integrates login-with-vipps with Woocommerce.

class WooLogin{
 
  function __construct() {
  }

  // We are going to do the Singleton pattern here so 
  // the basic login mechanism will be available in general with the same settings etc.
  protected static $instance = null;

  protected $loginbuttonshown = 0;
  protected $rewriteruleversion = 1;

 
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

  public function payment_gateway() {
    $gw = null;
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

  public function plugins_loaded () {
       add_action('woocommerce_proceed_to_checkout', array($this,'cart_continue_with_vipps'));
       add_action('woocommerce_widget_shopping_cart_buttons', array($this, 'cart_widget_continue_with_vipps'), 30);
       add_action('woocommerce_before_checkout_form', array($this, 'before_checkout_form_login_button'), 5);
  }

  public function cart_continue_with_vipps () {
    return $this->continue_with_vipps_button_for_carts('cart');
  }
  public function cart_widget_continue_with_vipps () {
    return $this->continue_with_vipps_button_for_carts('widget');
  }

  public function continue_with_vipps_button_for_carts($type='widget'){
    if (is_user_logged_in()) return false;
    if (!$this->is_active()) return false;

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
       <?php VippsLogin::instance()->login_button_html(__('Continue with', 'login-vipps'), 'woocommerce'); ?>
     </div>
<?php
 }



  public function before_checkout_form_login_button () {
    if (is_user_logged_in()) return false;
    if (!$this->is_active()) return false;

    $options =  get_option('vipps_login_woo_options');
    $show_continue_with_vipps = intval($options['woo-checkout-login']);
    $show_continue_with_vipps = apply_filters('continue_with_vipps_woo_show_in_checkout', $show_continue_with_vipps);
    if (!$show_continue_with_vipps) return;

    // Replace expresscheckout here
    add_action('woo_vipps_show_express_checkout', function ($show) { return false; });
    $this->continue_with_vipps_banner();
  }

  public function continue_with_vipps_banner() {
        if (!$this->is_active()) return false;
 
        // This is actually the filter used for customers, so we feed it dummy values - this is decided by an option.
        $can_register = $this->users_can_register(true, array(), array());

        if ($can_register) {
           $text = __('Log in or register an account with %s to continue your checkout!', 'login-vipps');
        } else {
           $text = __('Are you registered as a customer? Log in with %s to continue your checkout!', 'login-vipps');
        }
        $logo = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
        $linktext = __('Click here to continue', 'login-vipps');

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
           $text = __('Log in or register an account using %s !', 'login-vipps');
        } else {
           $text = __('Are you registered as a customer? Log in with %s!', 'login-vipps');
        }
        $logo = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
        $linktext = __('Click here to continue', 'login-vipps');

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
        $linktext = __('Click here to continue', 'login-vipps');
        $text = __('Create an account using ', 'login-vipps');

        $message = sprintf($text, "<img class='inline vipps-logo negative' border=0 src='$logo' alt='Vipps'/>") . "  -  <a href='javascript:login_with_vipps(\"woocommerce\");'>" . $linktext . "</a>";
        $message = apply_filters('continue_with_vipps_register_banner', $message);
        ?>
            <div class="woocommerce-info vipps-info"><?php echo $message;?></div>
        <?php
  }



  public function init () {
    if (!class_exists( 'WooCommerce' )) return;
    
    $woologin= $this->is_active();

    $this->add_rewrite_rules();

    if ($woologin) {
       $this->add_stored_woocommerce_notices();

       add_action('woocommerce_before_customer_login_form' , array($this, 'login_with_vipps_banner'));
       add_action('woocommerce_login_form_start' , array($this, 'login_with_vipps_banner'));
       add_action('woocommerce_register_form_start' , array($this, 'register_with_vipps_banner'));

       add_action('woocommerce_account_dashboard', array($this,'account_dashboard'));
       add_action('woocommerce_account_content', array($this,'account_content'));

       add_filter ('woocommerce_account_menu_items', array($this,'account_menu_items' ));
       add_action('woocommerce_account_vipps_endpoint', array($this,'account_vipps_content'));
       add_filter('add_query_vars', array($this, 'add_vipps_endpoint_query_var'));
       add_filter('the_title', array($this, 'account_vipps_title'), 10,2); // the  woocommerce_endpoint_vipps_filter does not work.

       if (is_user_logged_in()) {
         add_filter('wp_enqueue_scripts', array($this, 'wp_enqueue_account_scripts'));
       }
       add_action('admin_post_disconnect_vipps', array($this,'disconnect_vipps_post_handler'));

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

       add_filter("continue_with_vipps_woocommerce_confirm_redirect", array($this,'confirm_redirect'), 10, 3);
       add_action('continue_with_vipps_error_woocommerce_confirm', array($this, 'add_woocommerce_error'), 10, 4);
       add_filter("continue_with_vipps_error_woocommerce_confirm_redirect", array($this,'error_redirect'), 10, 3); // Use this for successful 

       $this->add_shortcodes();
   }
  }

  // We can't always add notices to the woo session, because we don't always have Woo loaded. So we'll use a transient to carry over .
  // We are always logged in here, so just use the cookie contents as a quickie session, using a short transient.
  public function add_stored_woocommerce_notices() {
       $cookie = @$_COOKIE[LOGGED_IN_COOKIE];
       if (!$cookie) return;
       $cookiehash =  hash('sha256',$cookie,false);
       $notices = get_transient('_vipps_woocommerce_stored_notices_' . $cookiehash);
       if (empty($notices)) return;
       delete_transient('_vipps_woocommerce_stored_notices_' . $cookiehash);
       $notice = sprintf(__('Connection to Vipps account %s <b>removed</b>.', 'login-vipps'), $phone);
       if ( ! WC()->session->has_session() ) {
        WC()->session->set_customer_session_cookie( true );
       }
       foreach($notices as $notice) {
         wc_add_notice($notice['notice'], $notice['type']);
       }
  }

  public function wp_enqueue_account_scripts () {
      if (is_account_page()) {
        wp_enqueue_script('vipps-login-admin',plugins_url('js/vipps-admin.js',__FILE__),array('jquery'),filemtime(dirname(__FILE__) . "/js/vipps-admin.js"), 'true');
        wp_localize_script('vipps-login-admin', 'vippsLoginAdminConfig', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'vippsconfirmnonce'=>wp_create_nonce('vippsconfirmnonce') ) );
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

  // This is run first on the users' main dashboard, right after menus
  public function account_dashboard() {
    // This only flushes rewrite rules when necessary. Adds a menu item for Vipps on the customers' My Account page
    $this->maybe_flush_rewrite_rules();
  }

  // This is the main content of a users my-account page
  public function account_content() {
    $userid = get_current_user_id();
    if (!$userid) return;
    $justconnected = get_usermeta($userid,'_vipps_just_connected');
        if ($justconnected) {
          delete_user_meta($userid, '_vipps_just_connected');
          $vippsphone = get_usermeta($userid,'_vipps_phone');
          $notice = sprintf(__('You are now connected to the Vipps account <b>%s</b>!', 'login-vipps'), $vippsphone);
?>
          <div class='vipps-notice vipps-info vipps-success'><?php echo $notice ?></div>
<?php
        }
  }
  public function account_menu_items($items) {
   $items['vipps'] = __('Vipps', 'login-vipps');
   return $items;
  }
  public function account_vipps_content() {
    add_filter('the_title', function ($title) { return __('Vipps!', 'login-vipps'); });
    $userid = get_current_user_id();
    if (!$userid) print "No user!";
    $user = new WC_Customer($userid);
 
    $allow_login = true;
    $allow_login = apply_filters('continue_with_vipps_allow_login', $allow_login, $user, array(), array());
    $vippsphone = trim(get_usermeta($user->ID,'_vipps_phone'));
    $vippsid = trim(get_usermeta($user->id,'_vipps_id'));
  
?>
<?php    if ($vippsphone && $vippsid): ?>
<h3><?php printf(__('You are connected to the Vipps account with the phone number <b>%s</b>', 'login_vipps'), esc_html($vippsphone)); ?></h3>
<p>
<form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
   <?php wp_nonce_field('disconnect_vipps', 'disconnect_vipps_nonce'); ?>
  <input type="hidden" name="action" value="disconnect_vipps">
  <input type="hidden" name="data" value="foobarid">
  <button type="submit" class='button vipps-button vipps-disconnect'><?php _e('Press here to disconnect', 'login-vipps'); ?></button>
</form>
</p>
<?php else: ?>
  <p><button type="button" onclick="connect_vipps_account('woocommerce');return false"; class="button vipps-connect" value="1" name="vipps-connect"><?php _e('Press here to connect with your app','login-vipps'); ?></button></p>
<?php endif; ?>
  <p> <?php _e('With Vipps, logging in is easier than ever - no passwords!', 'login-vipps'); ?> </p>
<?php
  }

  public function disconnect_vipps_post_handler () {
       check_admin_referer('disconnect_vipps', 'disconnect_vipps_nonce');
       $userid = get_current_user_id();
       if (!$userid) wp_die(__('You must be logged in to disconnect', 'login-vipps'));
       $phone = get_usermeta($userid, '_vipps_phone');

       delete_user_meta($userid,'_vipps_phone');
       delete_user_meta($userid,'_vipps_id');
       
       // Woocommerce hasn't loaded yet, so we'll just add the notices in a transient - we can't use the session
       // If they were critical, the users' metadata would have worked. IOK 2019-10-08
       // We are always logged in here, so just use the cookie contents as a quickie session.
       $cookie = @$_COOKIE[LOGGED_IN_COOKIE];
       if ($cookie) {
         $cookiehash =  hash('sha256',$cookie,false);
         $notices = get_transient('_vipps_woocommerce_stored_notices_' . $cookiehash);
         $notice = sprintf(__('Connection to Vipps account %s <b>removed</b>.', 'login-vipps'), $phone);
         $notices[]=array('notice'=>$notice, 'type'=>'success');
         set_transient('_vipps_woocommerce_stored_notices_' . $cookiehash, $notices, 60);
       }        

       wp_safe_redirect(wp_get_referer());
       exit();
  }

 // For some reason we can't do this with woocommerce_endpoint_vipps_title.
  public function account_vipps_title($title, $id) {
    if (in_the_loop() && !is_admin() && is_main_query() && is_account_page() ) {
       global $wp_query;
       $is_endpoint = isset($wp_query->query_vars['vipps']);
       if ($is_endpoint) {
           $title = __('Vipps!', 'login-vipps'); 
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

  public function add_woocommerce_error ($error, $errordesc, $errorhint, $session) {
     // We can add woocommerce already here, as Woocommerce handles the session itself  IOK 2019-10-08
     // NB: This require that the woocommerce session is active.
     if ( ! WC()->session->has_session() ) {
        WC()->session->set_customer_session_cookie( true );
     }
     wc_add_notice(__($errordesc,'login-vipps'),'error');
     wc()->session->save_data();
  }

  // Handle errors for the 'woocommerce' login application on the users home
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

  // When confirming, return to the same page
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
    /* Logging in with Vipps, so set that as payment method if possible and if the customer hasn't already chosen one IOK 2019-10-10 */
    $gw = $this->is_gateway_active();
    if ($gw) {
        $set = WC()->session->get('chosen_payment_method') ? false : true;
        $set = apply_filters('continue_with_vipps_set_default_payment_method', $set);
        if ($set) {
          WC()->session->set('chosen_payment_method', $gw->id);
        }
    }

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
     if (!get_user_meta($user->ID,'_vipps_shipping_address_changed', 1)) {
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


 function activate () {
      $allowcreatedefault = apply_filters( 'woocommerce_checkout_registration_enabled', 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout' ) );
      $allowcreatedefault = $allowcreatedefault ||  ('yes' === get_option( 'woocommerce_enable_myaccount_registration' )) ;

      $default = array('rewriteruleversion'=>0, 'woo-login'=>true, 'woo-create-users'=>$allowcreatedefault, 'woo-cart-login'=>true,'woo-checkout-login'=>true);
      add_option('vipps_login_woo_options',$default,true);
      $this->add_rewrite_rules();
      $this->maybe_flush_rewrite_rules();
 }
 
 function add_rewrite_rules() {
      // This is for the myaccount/vipps endpoint
      add_rewrite_endpoint( 'vipps', EP_ROOT | EP_PAGES );
 }

 function maybe_flush_rewrite_rules() {
      $options = get_option('vipps_login_woo_options');
      $rewrite = intval($options['rewriteruleversion']);
      if ($this->rewriteruleversion > $rewrite) {
          $this->add_rewrite_rules();
          $options['rewriteruleversion'] = $this->rewriteruleversion;
          update_option('vipps_login_woo_options', $options, true);
      }
 }
 function add_vipps_endpoint_query_var ($vars) {
   $vars[]='vipps';
   return $vars;
 }

 function is_active() {
   $options = get_option('vipps_login_woo_options');
   return intval($options['woo-login']);
 }

 function deactivate () {

 }

  public function extra_option_fields () {
      $options = get_option('vipps_login_woo_options');
      $woologin= $options['woo-login'];
      $woocreate = $options['woo-create-users'];
      $woocart = $options['woo-cart-login'];
      $woocheckout = $options['woo-checkout-login'];
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
   <tr>
       <td><?php _e('Show "Continue with Vipps" in shopping cart', 'login-vipps'); ?></td>
       <td width=30%> <input type='hidden' name='vipps_login_woo_options[woo-cart-login]' value=0>
                      <input type='checkbox' name='vipps_login_woo_options[woo-cart-login]' value=1 <?php if ( $woocart) echo ' CHECKED '; ?> >
</td>
       <td>
                      <?php _e('Check this to enable "Continue with Vipps" in the Shopping Cart and widgets. If you are using Express checkout, that will be shown instead.', 'login-vipps'); ?>
       </td>
   </tr>
   <tr>
       <td><?php _e('Show "Continue with Vipps" on the checkout page', 'login-vipps'); ?></td>
       <td width=30%> <input type='hidden' name='vipps_login_woo_options[woo-checkout-login]' value=0>
                      <input type='checkbox' name='vipps_login_woo_options[woo-checkout-login]' value=1 <?php if ( $woocheckout) echo ' CHECKED '; ?> >
</td>
       <td>
                      <?php _e('Check this to enable "Continue with Vipps" on the checkout page. This will replace express checkout on the checkout page.', 'login-vipps'); ?>
       </td>
   </tr>
<?php
 }


}
