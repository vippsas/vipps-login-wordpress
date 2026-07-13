<?php
// Render legacy content from the old static block until the block is restored and updated in backend editor. LP 2026-02-26
if ($content) {
    echo $content;
    return;
}

$block_config = VippsLogin::instance()->login_with_vipps_block_config();
$language = 'store' === $block->attributes['language'] || 'site' === $block->attributes['language']
            ? $block_config['siteLanguage']
            : $block->attributes['language'];

$wrapper_attributes = get_block_wrapper_attributes();
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
            stretched="false"
            compact="<?php echo $block->attributes['branded'] ? 'false' : 'true'; ?>"
        ></vipps-mobilepay-button>
    </a>
</div>
