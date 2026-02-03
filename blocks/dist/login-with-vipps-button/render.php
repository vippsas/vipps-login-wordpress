<?php
error_log('LP block attributes: ' . print_r($block->attributes, true));
$language = 'store' === $block->attributes['language']
            ? $block->attributes['storeLanguage']
            : $block->attributes['language'];

error_log('LP language: ' . print_r($language, true));
?>
<div class="continue-with-vipps-wrapper inline <?php echo $block->attributes['className'] ?? ''; ?>">
    <vipps-mobilepay-button
        brand="<?php echo $block->attributes['loginMethod']; ?>"
        language="<?php echo $language; ?>"
        variant="<?php echo $block->attributes['variant']; ?>"
        rounded="<?php echo $block->attributes['rounded']; ?>"
        verb="<?php echo $block->attributes['verb']; ?>"
        stretched="true"
        branded="<?php echo $block->attributes['branded']; ?>"
    ></vipps-mobilepay-button>
</div>
