<?php
if (! defined('ABSPATH')) {
    exit;
}

$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'login-with-vipps-block frontend continue-with-vipps-checkout',
]);
?>
<div <?php echo $wrapper_attributes; ?>>
    <span>
        <?php if (class_exists('VippsWooLogin')) {
                VippsWooLogin::instance()->checkout_continue_with_vipps();
              }
        ?>
    </span>
</div>
