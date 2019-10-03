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
    // Ajax code to get the redir url
    add_action('wp_ajax_vipps_woo_login_get_link', array($this,'ajax_vipps_login_get_link'));
    add_action('wp_ajax_nopriv_vipps_woo_login_get_link', array($this,'ajax_vipps_login_get_link'));

    
    $options = get_option('vipps_login_woo_options');
    $woologin= $options['woo-login'];
    if ($woologin) {
       error_log("Yarp");
       add_action( 'woocommerce_before_customer_login_form' , array($this, 'login_with_vipps_button'));
//       add_action('woocommerce_login_form_start' , array($this, 'login_with_vipps_button'));
    } else {
       error_log("Narp");
    }
  }

  public function login_with_vipps_button() {
?>
     <div style='margin:20px;' class='continue-with-vipps'>
<script>
 function woo_login_with_vipps() {
     console.log("yayay");
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
<a href='javascript:woo_login_with_vipps();' class='button' style='width:100%'>Login with Vipps yo!</a>
</div>
<?php
     return true;
  }

 function activate () {
      $default = array('woo-login'=>true);
      add_option('vipps_login_woo_options',$default,false);
      error_log(print_r($default,true));
 }
 function deactivate () {

 }

  public function extra_option_fields () {
      print "Hey ho\n";
      $options = get_option('vipps_login_woo_options');
      $woologin= $options['woo-login'];
?>
<?php settings_fields('vipps_login_woo_options'); ?>
   <tr>
       <td><?php _e('Use Login With Vipps for Woocommerce', 'login-vipps'); ?></td>
       <td width=30%> <input type='hidden' name='vipps_login_woo_options[woo-login]' value=0>
                      <input type='checkbox' name='vipps_login_woo_options[woo-login]' value=1 <?php if ( $woologin ) echo ' CHECKED '; ?> >
</td>
       <td>
                      <?php _e('Check this to enable Log in With Vipps on your customers pages in Woocommerce', 'login-vipps'); ?>
       </td>
   </tr>
<?php
 }


}
