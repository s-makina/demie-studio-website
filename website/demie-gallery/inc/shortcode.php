<?php
/**
 * [demie_gallery] shortcode.
 *
 * Attrs:
 *   id         gallery post ID
 *   slug       gallery post slug (used when id is absent)
 *   latest     when "yes", forces the latest published gallery (default fallback)
 *   layout     masonry|masonry-2|classic-2|classic-3|standard-2|standard-3|
 *              modern-2|modern-3|tiles-2|tiles-3|carousel|overlapping|
 *              distortion|filterable   (default masonry)
 *   per_page   items per page for load_more/numbered (default 24; 0 = all)
 *   pagination load_more|numbered|none (default load_more)
 *   columns    2|3 — used by col-2/col-3 layouts that honour the attribute
 *
 * Decision: docs/adr/0002-gallery-as-plugin.md
 */

if (!defined('ABSPATH')) exit;

add_shortcode('demie_gallery', function ($atts) {
    $atts = shortcode_atts([
        'id'         => 0,
        'slug'       => '',
        'latest'     => '',
        'layout'     => 'masonry',
        'per_page'   => 24,
        'pagination' => 'load_more',
        'columns'    => 3,
    ], $atts, 'demie_gallery');

    $gallery_id = demie_g_resolve_gallery_id($atts);

    return demie_g_render_gallery([
        'gallery_id' => $gallery_id,
        'layout'     => (string) $atts['layout'],
        'per_page'   => (int) $atts['per_page'],
        'pagination' => (string) $atts['pagination'],
        'columns'    => (int) $atts['columns'],
    ]);
});
