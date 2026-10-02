<?php
/**
 * Custom post types for the Demie Photography theme.
 *
 * Decision: docs/adr/0001-cpts-and-metaboxes-for-editable-content.md
 *
 * - demie_service      Service (short blurb + full description; home shows first 4, services page shows all)
 * - demie_testimonial  Testimonial (quote, author, location, photo, star rating)
 * - demie_faq          FAQ (question + answer)
 * - demie_slide        Slide (homepage hero slider: title, subtitle, background image)
 * - demie_portfolio    Portfolio Item (non-public; gallery + homepage portfolio, lightbox only)
 */

if (!defined('ABSPATH')) exit;

/**
 * True on admin page loads (excludes AJAX), used to guard metabox rendering/saving.
 */
function demie_is_admin_screen() {
    return is_admin() && !wp_doing_ajax();
}

/**
 * Allow SVG uploads for administrators — the service icons are SVGs.
 */
add_filter('upload_mimes', function ($mimes) {
    if (current_user_can('manage_options')) {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
    }
    return $mimes;
});

/**
 * WordPress's real-MIME check trips on SVGs; trust admins here.
 */
add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename, $mimes) {
    if (current_user_can('manage_options') && is_string($filename) && preg_match('/\.svgz?$/i', $filename)) {
        $data['ext']  = pathinfo($filename, PATHINFO_EXTENSION);
        $data['type'] = 'image/svg+xml';
    }
    return $data;
}, 10, 4);

/**
 * Shared admin list columns: show the featured image (icon/photo/slide art)
 * and the manual order, for every Demie CPT.
 */
add_filter('manage_posts_columns', function ($columns, $post_type) {
    if (in_array($post_type, ['demie_service', 'demie_testimonial', 'demie_faq', 'demie_slide', 'demie_portfolio'], true)) {
        $columns['demie_thumb'] = __('Image / Icon', 'demie-photography');
        $columns['demie_order'] = __('Order', 'demie-photography');
    }
    return $columns;
}, 10, 2);

add_action('manage_posts_custom_column', function ($column, $post_id) {
    if ('demie_thumb' === $column) {
        echo get_the_post_thumbnail($post_id, [50, 50], ['style' => 'width:50px;height:50px;object-fit:contain;']);
    } elseif ('demie_order' === $column) {
        echo esc_html((string) get_post_field('menu_order', $post_id));
    }
}, 10, 2);

add_action('init', function () {
    // Service
    register_post_type('demie_service', [
        'labels' => [
            'name'          => __('Services', 'demie-photography'),
            'singular_name' => __('Service', 'demie-photography'),
            'add_new_item'  => __('Add New Service', 'demie-photography'),
            'edit_item'     => __('Edit Service', 'demie-photography'),
            'menu_name'     => __('Services', 'demie-photography'),
        ],
        'public'              => true,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_rest'        => true,
        'menu_position'       => 21,
        'menu_icon'           => 'dashicons-camera',
        'supports'            => ['title', 'editor', 'page-attributes', 'thumbnail'],
        'has_archive'         => false,
        'rewrite'             => false,
    ]);

    // Testimonial
    register_post_type('demie_testimonial', [
        'labels' => [
            'name'          => __('Testimonials', 'demie-photography'),
            'singular_name' => __('Testimonial', 'demie-photography'),
            'add_new_item'  => __('Add New Testimonial', 'demie-photography'),
            'edit_item'     => __('Edit Testimonial', 'demie-photography'),
            'menu_name'     => __('Testimonials', 'demie-photography'),
        ],
        'public'              => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_rest'        => true,
        'menu_position'       => 22,
        'menu_icon'           => 'dashicons-format-quote',
        'supports'            => ['title', 'thumbnail', 'page-attributes'],
        'has_archive'         => false,
        'rewrite'             => false,
    ]);

    // FAQ
    register_post_type('demie_faq', [
        'labels' => [
            'name'          => __('FAQs', 'demie-photography'),
            'singular_name' => __('FAQ', 'demie-photography'),
            'add_new_item'  => __('Add New FAQ', 'demie-photography'),
            'edit_item'     => __('Edit FAQ', 'demie-photography'),
            'menu_name'     => __('FAQs', 'demie-photography'),
        ],
        'public'              => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_rest'        => true,
        'menu_position'       => 23,
        'menu_icon'           => 'dashicons-editor-help',
        'supports'            => ['title', 'editor', 'page-attributes'],
        'has_archive'         => false,
        'rewrite'             => false,
    ]);

    // Slide
    register_post_type('demie_slide', [
        'labels' => [
            'name'          => __('Slides', 'demie-photography'),
            'singular_name' => __('Slide', 'demie-photography'),
            'add_new_item'  => __('Add New Slide', 'demie-photography'),
            'edit_item'     => __('Edit Slide', 'demie-photography'),
            'menu_name'     => __('Slides', 'demie-photography'),
        ],
        'public'              => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_rest'        => true,
        'menu_position'       => 24,
        'menu_icon'           => 'dashicons-images-alt2',
        'supports'            => ['title', 'thumbnail', 'page-attributes'],
        'has_archive'         => false,
        'rewrite'             => false,
    ]);

    // Portfolio Item
    register_post_type('demie_portfolio', [
        'labels' => [
            'name'          => __('Portfolio Items', 'demie-photography'),
            'singular_name' => __('Portfolio Item', 'demie-photography'),
            'add_new_item'  => __('Add New Portfolio Item', 'demie-photography'),
            'edit_item'     => __('Edit Portfolio Item', 'demie-photography'),
            'menu_name'     => __('Portfolio', 'demie-photography'),
        ],
        'public'              => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_rest'        => true,
        'menu_position'       => 25,
        'menu_icon'           => 'dashicons-format-gallery',
        'supports'            => ['title', 'editor', 'thumbnail', 'page-attributes'],
        'has_archive'         => false,
        'rewrite'             => false,
    ]);
});
