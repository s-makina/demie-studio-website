<?php
/**
 * Portfolio index support: per-gallery "Show on portfolio" gate.
 *
 * Decision: docs/adr/0003-portal-leaves-wordpress-portfolio-stays-curated-index.md
 * The portfolio/ page lists curated Galleries (one Gallery = one card).
 * Visibility is a single checkbox; unchecked galleries stay hidden from the
 * index and from direct ?project= detail. No ordering UI (date DESC).
 */

if (!defined('ABSPATH')) exit;

const DEMIE_G_META_PORTFOLIO = '_demie_g_show_on_portfolio';

add_action('add_meta_boxes', function () {
    add_meta_box(
        'demie-g-portfolio',
        __('Portfolio', 'demie-gallery'),
        'demie_g_render_portfolio_metabox',
        'demie_gallery',
        'side',
        'default'
    );
});

function demie_g_render_portfolio_metabox($post) {
    $checked = '1' === (string) get_post_meta($post->ID, DEMIE_G_META_PORTFOLIO, true);
    ?>
    <p>
        <label>
            <input type="checkbox" name="_demie_g_show_on_portfolio" value="1"<?php checked($checked); ?> />
            <?php esc_html_e('Show on portfolio page', 'demie-gallery'); ?>
        </label>
    </p>
    <p class="description"><?php esc_html_e('Checked galleries appear as cards on portfolio/ and can be opened inline. Unchecked galleries stay hidden everywhere on portfolio/.', 'demie-gallery'); ?></p>
    <?php
}

add_action('save_post_demie_gallery', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!isset($_POST['demie_g_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['demie_g_nonce'])), 'demie_g_save_gallery')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $checked = isset($_POST['_demie_g_show_on_portfolio']) && '1' === (string) wp_unslash($_POST['_demie_g_show_on_portfolio']);
    if ($checked) {
        update_post_meta($post_id, DEMIE_G_META_PORTFOLIO, '1');
    } else {
        delete_post_meta($post_id, DEMIE_G_META_PORTFOLIO);
    }
}, 20, 1);

/**
 * True when a gallery is flagged visible on the portfolio page (and published).
 */
function demie_g_is_on_portfolio($gallery_id) {
    $gallery_id = (int) $gallery_id;
    if (!$gallery_id) {
        return false;
    }
    if (demie_g_is_admin_context()) {
        return '1' === (string) get_post_meta($gallery_id, DEMIE_G_META_PORTFOLIO, true);
    }
    $post = get_post($gallery_id);
    if (!$post || 'demie_gallery' !== $post->post_type || 'publish' !== $post->post_status) {
        return false;
    }
    return '1' === (string) get_post_meta($gallery_id, DEMIE_G_META_PORTFOLIO, true);
}

/**
 * Alias used by the portfolio template for readability.
 */
function demie_g_can_show_on_portfolio($gallery_id) {
    return demie_g_is_on_portfolio($gallery_id);
}

/**
 * Published + flagged galleries, newest first. Unpaged by design (card counts
 * stay small); paginate here only if the index ever passes ~50.
 *
 * @return WP_Post[]
 */
function demie_g_portfolio_galleries() {
    return get_posts([
        'post_type'      => 'demie_gallery',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_key'       => DEMIE_G_META_PORTFOLIO,
        'meta_value'     => '1',
        'no_found_rows'  => true,
    ]);
}

/**
 * Resolve a portfolio ?project= slug to a published gallery ID (0 when none).
 * Visibility is checked separately so the template can fall back to the index.
 */
function demie_g_resolve_portfolio_gallery_id($slug) {
    $slug = sanitize_title((string) $slug);
    if ('' === $slug) {
        return 0;
    }
    $post = get_page_by_path($slug, OBJECT, 'demie_gallery');
    if (!$post || 'publish' !== $post->post_status) {
        return 0;
    }
    return (int) $post->ID;
}
