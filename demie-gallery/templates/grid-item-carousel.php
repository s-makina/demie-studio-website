<?php
/**
 * One Gallery Media Item as a Swiper slide (carousel layout).
 *
 * In: $item, $gallery_id
 */

if (!defined('ABSPATH')) exit;

$resolved = demie_g_resolve_item($item);
if (empty($resolved['thumb']) && empty($resolved['full'])) {
    return;
}

$kind     = $resolved['kind'];
$is_embed = ('embed' === $resolved['type']);

$lightbox_group = 'demie-g-' . (int) $gallery_id;
$fancybox       = ' data-fancybox="' . esc_attr($lightbox_group) . '"';
$caption        = $resolved['title'];

if ('photo' === $kind) {
    $href = $resolved['full'] ? $resolved['full'] : $resolved['thumb'];
} elseif ('attachment' === $resolved['type']) {
    $href = $resolved['full'];
} else {
    $href = $resolved['url'];
}

$img_src = $resolved['thumb'] ? $resolved['thumb'] : $resolved['full'];
$alt     = $caption ? $caption : __('Gallery media', 'demie-gallery');
?>
<div class="swiper-slide">
    <div class="grid-item demie-g-item demie-g-<?php echo esc_attr($kind); ?>" data-kind="<?php echo esc_attr($kind); ?>">
        <div class="wptb-item--inner">
            <div class="wptb-item--image">
                <img src="<?php echo esc_url($img_src); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy">

                <a class="wptb-item--link" href="<?php echo esc_url($href); ?>"<?php echo $fancybox; ?>
                   data-caption="<?php echo esc_attr($caption); ?>"
                   <?php if ($is_embed) : ?>data-type="iframe" data-preload="false"<?php endif; ?>
                   aria-label="<?php echo esc_attr($alt); ?>">
                    <?php if ('video' === $kind) : ?>
                        <i class="bi bi-play-btn" aria-hidden="true"></i>
                    <?php else : ?>
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <?php endif; ?>
                </a>
            </div>

            <div class="wptb-item--holder">
                <div class="wptb-item--meta">
                    <?php if ($caption) : ?>
                        <h4><a href="<?php echo esc_url($href); ?>"<?php echo $fancybox; ?>
                               data-caption="<?php echo esc_attr($caption); ?>"
                               <?php if ($is_embed) : ?>data-type="iframe" data-preload="false"<?php endif; ?>><?php echo esc_html($caption); ?></a></h4>
                    <?php endif; ?>
                    <p><?php echo 'video' === $kind ? esc_html__('Video', 'demie-gallery') : esc_html__('Photo', 'demie-gallery'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
