<?php
/**
 * Front AJAX: load-more endpoint.
 *
 * Reuses demie_g_render_gallery internals — the returned item HTML is the
 * exact markup the server rendered for page 1, so no markup drift.
 */

if (!defined('ABSPATH')) exit;

add_action('wp_ajax_demie_g_load_more', 'demie_g_ajax_load_more');
add_action('wp_ajax_nopriv_demie_g_load_more', 'demie_g_ajax_load_more');

function demie_g_ajax_load_more() {
    check_ajax_referer('demie_g_front', 'nonce');

    $args = demie_g_normalize_args([
        'gallery_id' => isset($_REQUEST['gallery_id']) ? absint($_REQUEST['gallery_id']) : 0,
        'page'       => isset($_REQUEST['page']) ? absint($_REQUEST['page']) : 2,
        'per_page'   => isset($_REQUEST['per_page']) ? absint($_REQUEST['per_page']) : 24,
        'layout'     => isset($_REQUEST['layout']) ? sanitize_key(wp_unslash($_REQUEST['layout'])) : 'masonry',
        'columns'    => isset($_REQUEST['columns']) ? absint($_REQUEST['columns']) : 3,
        'items_only' => true, // respond with item markup for the existing grid
    ]);

    // Carousel/static layouts never paginate; per_page=0 would render all.
    if (0 === $args['per_page'] || 'none' === $args['pagination']) {
        wp_send_json_error(['message' => 'not paginated'], 400);
    }

    $gallery_id = $args['gallery_id'];
    $gallery    = $gallery_id ? get_post($gallery_id) : null;
    if (!$gallery || 'demie_gallery' !== $gallery->post_type || 'publish' !== $gallery->post_status) {
        wp_send_json_error(['message' => 'gallery not found'], 404);
    }

    // Render just the item markup for this page slice (same grid-item
    // template as the initial paint — no drift by construction).
    $items_html = demie_g_render_gallery($args);

    $media    = demie_g_get_media($gallery_id);
    $total    = count($media);
    $per_page = $args['per_page'];
    $page     = isset($args['page']) ? max(1, (int) $args['page']) : 2;
    $offset   = ($page - 1) * $per_page;

    $has_more = $offset + $per_page < $total;

    if ($total <= $offset) {
        // Over-scrolled: this page slice is empty.
        wp_send_json_success([
            'items'    => '',
            'has_more' => false,
            'total'    => $total,
            'page'     => $page,
        ]);
    }

    wp_send_json_success([
        'items'    => $items_html,
        'has_more' => $has_more,
        'total'    => $total,
        'page'     => $args['page'],
    ]);
}
