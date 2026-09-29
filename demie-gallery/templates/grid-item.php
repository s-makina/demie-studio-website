<?php
/**
 * One Gallery Media Item as a Kimono grid item.
 *
 * In: $item (raw), $layout, $gallery_id
 *
 * Markup mirrors the Kimono project-* templates: .grid-item > .wptb-item--inner
 * > .wptb-item--image (+ .wptb-item--link) + .wptb-item--holder > .wptb-item--meta.
 * The plugin self-initializes Isotope on .demie-g-grid, so grid-item never
 * carries col-md-* spans — width comes from the grid-N class or the layout's
 * span pattern below.
 */

if (!defined('ABSPATH')) exit;

$resolved = demie_g_resolve_item($item);
if (empty($resolved['thumb']) && empty($resolved['full'])) {
    return; // nothing renderable (e.g. broken attachment)
}

$kind   = $resolved['kind'];
$is_embed = ('embed' === $resolved['type']);

$span_map = [
    'masonry'    => ['col-md-4', 'col-md-4', 'col-md-4', 'col-md-8', 'col-md-8', 'col-md-4', 'col-md-4', 'col-md-4', 'col-md-8', 'col-md-4', 'col-md-4', 'col-md-4'],
    'distortion' => ['width-50', 'width-50', 'width-100', 'width-50', 'width-50', 'width-100'],
];
$static_span = isset($span_map[$layout]) ? $span_map[$layout] : [];

/** Compute the Kimono span for this item position. */
$span = '';
if ($static_span) {
    $static_index = isset($GLOBALS['demie_g_item_index']) ? (int) $GLOBALS['demie_g_item_index'] : 0;
    $span = $static_span[$static_index % count($static_span)];
} elseif ('overlapping' === $layout) {
    // Collage: 3 columns of fixed-width tiles (CSS in gallery.css).
    $span = 'demie-g-overlap__item';
}

$lightbox_group = 'demie-g-' . (int) $gallery_id;
$fancybox       = ' data-fancybox="' . esc_attr($lightbox_group) . '"';
$caption        = $resolved['title'];

// What opens when the tile is clicked:
// - attachment photo  → the full-size image (Fancybox)
// - attachment video  → the video file (Fancybox <video> player)
// - embed             → the provider page URL; Fancybox opens it as an iframe
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
<div class="grid-item<?php echo $span ? ' ' . esc_attr($span) : ''; ?> demie-g-<?php echo esc_attr($kind); ?> demie-g-item"
     data-kind="<?php echo esc_attr($kind); ?>">
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
<?php
// Advance the position counter AFTER the item rendered (see span computation).
$GLOBALS['demie_g_item_index'] = (isset($GLOBALS['demie_g_item_index']) ? (int) $GLOBALS['demie_g_item_index'] : 0) + 1;
