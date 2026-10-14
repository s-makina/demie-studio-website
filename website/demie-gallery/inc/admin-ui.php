<?php
/**
 * Admin UI: Gallery Media metabox (drag-and-drop builder) + Short Description.
 *
 * Media list is persisted as one hidden input (_demie_g_media JSON), synced
 * live by assets/admin.js on every add/remove/reorder and saved in save_post.
 */

if (!defined('ABSPATH')) exit;

add_action('add_meta_boxes', function () {
    add_meta_box(
        'demie-g-media',
        __('Gallery Media', 'demie-gallery'),
        'demie_g_render_media_metabox',
        'demie_gallery',
        'normal',
        'high'
    );
    add_meta_box(
        'demie-g-desc',
        __('Short Description', 'demie-gallery'),
        'demie_g_render_desc_metabox',
        'demie_gallery',
        'normal',
        'default'
    );
});

/**
 * Gallery Media metabox: sortable tile grid + Add Media + Add Video Link.
 */
function demie_g_render_media_metabox($post) {
    wp_nonce_field('demie_g_save_gallery', 'demie_g_nonce');

    $items = demie_g_get_media($post->ID);
    ?>
    <div class="demie-g-builder">
        <p class="description">
            <?php esc_html_e('Add photos and videos from the Media Library, or paste a YouTube/Vimeo link. Drag tiles to set the display order — the order here is the order visitors see.', 'demie-gallery'); ?>
        </p>

        <div class="demie-g-toolbar">
            <button type="button" class="button button-primary" id="demie-g-add-media">
                <span class="dashicons dashicons-admin-media"></span>
                <?php esc_html_e('Add Media', 'demie-gallery'); ?>
            </button>

            <span class="demie-g-video-add">
                <input type="url" id="demie-g-video-url" placeholder="<?php esc_attr_e('https://youtube.com/… or https://vimeo.com/…', 'demie-gallery'); ?>" />
                <button type="button" class="button" id="demie-g-add-video">
                    <?php esc_html_e('Add Video Link', 'demie-gallery'); ?>
                </button>
            </span>

            <span class="demie-g-count-badge"></span>
        </div>

        <div id="demie-g-grid" class="demie-g-grid <?php echo empty($items) ? 'is-empty' : ''; ?>">
            <?php
            foreach ($items as $item) {
                demie_g_admin_tile($item);
            }
            ?>
        </div>

        <p class="description demie-g-empty-hint <?php echo empty($items) ? '' : 'hidden'; ?>">
            <?php esc_html_e('No media yet — use “Add Media” or “Add Video Link” above.', 'demie-gallery'); ?>
        </p>

        <input type="hidden" id="demie-g-media-input" name="_demie_g_media"
               value="<?php echo esc_attr(wp_json_encode($items)); ?>" />
    </div>
    <?php
}

/**
 * One tile in the admin builder grid. Data attributes carry the item shape so
 * admin.js can rebuild the JSON without re-resolving anything server-side.
 */
function demie_g_admin_tile($item) {
    $resolved = demie_g_resolve_item($item);
    $kind     = $resolved['kind'];
    $thumb    = $resolved['thumb'] ?: demie_g_video_placeholder();
    ?>
    <div class="demie-g-tile" data-type="<?php echo esc_attr($item['type']); ?>"
         data-id="<?php echo esc_attr(isset($item['id']) ? (int) $item['id'] : 0); ?>"
         data-url="<?php echo esc_attr(isset($item['url']) ? $item['url'] : ''); ?>">
        <span class="demie-g-tile-handle dashicons dashicons-menu" title="<?php esc_attr_e('Drag to reorder', 'demie-gallery'); ?>"></span>
        <img src="<?php echo esc_url($thumb); ?>" alt="" />
        <span class="demie-g-tile-badge demie-g-tile-badge--<?php echo esc_attr($kind); ?>">
            <?php echo 'video' === $kind ? esc_html__('Video', 'demie-gallery') : esc_html__('Photo', 'demie-gallery'); ?>
        </span>
        <button type="button" class="demie-g-tile-remove" title="<?php esc_attr_e('Remove', 'demie-gallery'); ?>">&times;</button>
    </div>
    <?php
}

/**
 * Short Description metabox (_demie_g_desc) — shown as intro by layouts.
 */
function demie_g_render_desc_metabox($post) {
    $desc = get_post_meta($post->ID, DEMIE_G_META_DESC, true);
    ?>
    <div class="demie-g-desc-metabox">
        <label for="demie-g-desc-input" class="screen-reader-text"><?php esc_html_e('Short Description', 'demie-gallery'); ?></label>
        <textarea id="demie-g-desc-input" name="_demie_g_desc" rows="4"
                  placeholder="<?php esc_attr_e('Optional intro shown above the gallery on the front end…', 'demie-gallery'); ?>"><?php echo esc_textarea($desc); ?></textarea>
        <p class="description"><?php esc_html_e('Optional. A short intro line some layouts show above the gallery.', 'demie-gallery'); ?></p>
    </div>
    <?php
}

/**
 * Save handler: nonce + capability + only on our CPT screen. Un hooked from
 * save_post with a low-ish priority so meta is stored once.
 */
add_action('save_post_demie_gallery', function ($post_id, $post, $update) {
    // Autosave / revision / permission guards.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!isset($_POST['demie_g_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['demie_g_nonce'])), 'demie_g_save_gallery')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Media list: canonical JSON lives in the hidden input synced by admin.js.
    if (isset($_POST[DEMIE_G_META_MEDIA])) {
        $raw   = wp_unslash($_POST[DEMIE_G_META_MEDIA]);
        $items = demie_g_sanitize_media_list($raw);

        if (empty($items)) {
            delete_post_meta($post_id, DEMIE_G_META_MEDIA);
        } else {
            update_post_meta($post_id, DEMIE_G_META_MEDIA, wp_json_encode($items));
        }
    }

    // Short description.
    if (isset($_POST[DEMIE_G_META_DESC])) {
        $desc = sanitize_textarea_field(wp_unslash($_POST[DEMIE_G_META_DESC]));
        if ('' === $desc) {
            delete_post_meta($post_id, DEMIE_G_META_DESC);
        } else {
            update_post_meta($post_id, DEMIE_G_META_DESC, $desc);
        }
    }
}, 10, 3);

/**
 * Embed validation for "Add Video Link": admin-only AJAX that runs the URL
 * through wp_oembed_get so invalid links never reach the grid.
 */
add_action('wp_ajax_demie_g_validate_embed', function () {
    check_ajax_referer('demie_g_oembed', 'nonce');

    if (!current_user_can('upload_files')) {
        wp_send_json_error(['message' => __('Not allowed.', 'demie-gallery')], 403);
    }

    $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
    if (!$url || !demie_g_is_allowed_embed_url($url)) {
        wp_send_json_error(['message' => __('Enter a valid YouTube or Vimeo URL.', 'demie-gallery')]);
    }

    $oembed = demie_g_oembed($url);
    if (!$oembed || empty($oembed['html'])) {
        wp_send_json_error(['message' => __('That URL could not be embedded. Use a YouTube or Vimeo link.', 'demie-gallery')]);
    }

    wp_send_json_success([
        'item'   => ['type' => 'embed', 'url' => $url],
        'thumb'  => !empty($oembed['thumbnail_url']) ? $oembed['thumbnail_url'] : demie_g_video_placeholder(),
        'title'  => !empty($oembed['title']) ? $oembed['title'] : $url,
    ]);
});
