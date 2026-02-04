<?php
$block_config = VippsLogin::instance()->login_with_vipps_block_config();
$language = 'store' === $block->attributes['language']
            ? $block_config['storeLanguage']
            : $block->attributes['language'];

$wrapper_attributes = get_block_wrapper_attributes(['class' => 'continue-with-vipps-wrapper inline']);
?>
<div <?php echo $wrapper_attributes; ?>">
    <a class="button vipps-orange vipps-button continue-with-vipps continue-with-vipps-action"
        title="<?php echo $block_config['title'];?>"
        data-application="<?php echo $block->attributes['application'];?>"
        href="javascript: void(0);"
    >
        <vipps-mobilepay-button
            brand="<?php echo $block_config['loginMethod']; ?>"
            language="<?php echo $language; ?>"
            variant="<?php echo $block->attributes['variant']; ?>"
            rounded="<?php echo $block->attributes['rounded'] ? 'true' : 'false'; ?>"
            verb="<?php echo $block->attributes['verb']; ?>"
            stretched="true"
            branded="<?php echo $block->attributes['branded'] ? 'true' : 'false'; ?>"
        ></vipps-mobilepay-button>
    </a>
</div>
