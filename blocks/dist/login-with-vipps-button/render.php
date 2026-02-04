<?php
$block_config = VippsLogin::instance()->login_with_vipps_block_config();
error_log('LP block attributes: ' . print_r($block->attributes, true));
$language = 'store' === $block->attributes['language']
            ? $block_config['storeLanguage']
            : $block->attributes['language'];

error_log('LP language: ' . print_r($language, true));
$wrapper_attributes = get_block_wrapper_attributes(['class' => 'continue-with-vipps-wrapper inline']);
error_log('LP wrapper attrs: ' . $wrapper_attributes);
?>
<div <?php echo $wrapper_attributes; ?>">
    <vipps-mobilepay-button
        brand="<?php echo $block_config['loginMethod']; ?>"
        language="<?php echo $language; ?>"
        variant="<?php echo $block->attributes['variant']; ?>"
        rounded="<?php echo $block->attributes['rounded'] ? 'true' : 'false'; ?>"
        verb="<?php echo $block->attributes['verb']; ?>"
        stretched="true"
        branded="<?php echo $block->attributes['branded'] ? 'true' : 'false'; ?>"
    ></vipps-mobilepay-button>
</div>
