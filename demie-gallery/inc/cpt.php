<?php
/**
 * demie_gallery CPT + admin list table columns.
 *
 * Decision: docs/adr/0002-gallery-as-plugin.md — Galleries are a standalone
 * plugin entity, separate from the theme's Portfolio Items.
 */

if (!defined('ABSPATH')) exit;

/**
 * True on plugin admin screens (our CPT edit/list pages), excluding AJAX.
 */
function demie_g_is_our_admin_screen() {
    if (!is_admin() || wp_doing_ajax()) {
        return false;
    }
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    return $screen && !empty($screen->post_type) && 'demie_gallery' === $screen->post_type;
}

/**
 * The Gallery post type. Media list lives in a metabox, so only `title` is a
 * "support"; the short description is its own metabox (Step 3).
 */
function demie_g_register_cpt() {
    register_post_type('demie_gallery', [
        'labels' => [
            'name'          => __('Galleries', 'demie-gallery'),
            'singular_name' => __('Gallery', 'demie-gallery'),
            'add_new'       => __('Add New Gallery', 'demie-gallery'),
            'add_new_item'  => __('Add New Gallery', 'demie-gallery'),
            'edit_item'     => __('Edit Gallery', 'demie-gallery'),
            'new_item'      => __('New Gallery', 'demie-gallery'),
            'view_item'     => __('View Gallery', 'demie-gallery'),
            'search_items'  => __('Search Galleries', 'demie-gallery'),
            'not_found'     => __('No galleries found', 'demie-gallery'),
            'menu_name'     => __('Galleries', 'demie-gallery'),
        ],
        'public'              => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => false,
        'menu_position'       => 26,
        'menu_icon'           => 'dashicons-format-gallery',
        'supports'            => ['title'],
        'has_archive'         => false,
        'rewrite'             => false,
        'capability_type'     => 'post',
    ]);
}
add_action('init', 'demie_g_register_cpt');

/* ---------- Admin list columns ---------- */

/**
 * Columns: cover thumbnail (first media item) | media count | shortcode hint.
 */
add_filter('manage_demie_gallery_posts_columns', function ($columns) {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ('title' === $key) {
            $new['demie_g_cover']  = __('Cover', 'demie-gallery');
            $new['demie_g_count']  = __('Media', 'demie-gallery');
            $new['demie_g_sc']     = __('Shortcode', 'demie-gallery');
        }
    }
    return $new;
});

add_action('manage_demie_gallery_posts_custom_column', function ($column, $post_id) {
    if ('demie_g_cover' === $column) {
        $cover = demie_g_cover($post_id);
        if ($cover) {
            printf(
                '<img src="%s" alt="" style="width:60px;height:44px;object-fit:cover;border-radius:4px;display:block;" />',
                esc_url($cover)
            );
        } else {
            echo '&mdash;';
        }
    } elseif ('demie_g_count' === $column) {
        echo esc_html(number_format_i18n(demie_g_count($post_id)));
    } elseif ('demie_g_sc' === $column) {
        printf(
            '<code class="demie-g-copy" data-shortcode="[demie_gallery id=&quot;%d&quot;]" title="%s">[demie_gal… id=&quot;%d&quot;]</code>',
            (int) $post_id,
            esc_attr__('Click to copy', 'demie-gallery'),
            (int) $post_id
        );
    }
}, 10, 2);

/**
 * Cover + count columns need the media-list data layer; load admin JS for the
 * copy-to-clipboard behaviour on list + edit screens.
 */
add_action('admin_enqueue_scripts', function ($hook) {
    $screen = get_current_screen();
    if (!$screen || 'demie_gallery' !== $screen->post_type) {
        return;
    }
    wp_enqueue_style('demie-g-admin', DEMIE_G_URI . 'assets/admin.css', [], DEMIE_G_VERSION);
    wp_enqueue_script('demie-g-admin', DEMIE_G_URI . 'assets/admin.js', ['jquery', 'jquery-ui-sortable', 'media-editor'], DEMIE_G_VERSION, true);

    wp_localize_script('demie-g-admin', 'demieGAdmin', [
        'nonce'        => wp_create_nonce('demie_g_oembed'),
        'ajaxUrl'      => admin_url('admin-ajax.php'),
        'invalidUrl'   => __('That URL could not be embedded. Use a YouTube or Vimeo link.', 'demie-gallery'),
        'placeholder'  => DEMIE_G_URI . 'assets/img/video-placeholder.svg',
        'selectTitle'  => __('Add media to the gallery', 'demie-gallery'),
        'addToGallery' => __('Add to gallery', 'demie-gallery'),
        'photoLabel'   => __('Photo', 'demie-gallery'),
        'videoLabel'   => __('Video', 'demie-gallery'),
    ]);
});

/**
 * Copy-to-clipboard footer script for the list table (plain JS, no jQuery UI).
 */
add_action('admin_footer-edit.php', function () {
    $screen = get_current_screen();
    if (!$screen || 'demie_gallery' !== $screen->post_type) {
        return;
    }
    ?>
    <script>
    (function () {
        document.addEventListener('click', function (e) {
            var el = e.target.closest('.demie-g-copy');
            if (!el) return;
            e.preventDefault();
            var text = el.getAttribute('data-shortcode') || el.textContent;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text);
            } else {
                var ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }
            var old = el.title;
            el.title = el.getAttribute('data-copied') || 'Copied!';
            setTimeout(function () { el.title = old; }, 1200);
        });
    })();
    </script>
    <?php
});
