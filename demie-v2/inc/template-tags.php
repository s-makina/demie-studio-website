<?php
/**
 * Template tags for reading the Demie dynamic content layer.
 *
 * All heading helpers fall back to the seeded/current template copy when a
 * field is empty, so templates never render empty H1s.
 */

if (!defined('ABSPATH')) exit;

/**
 * Get a Demie page meta value by page slug (empty value -> fallback).
 */
function demie_page_meta($slug, $key, $fallback = '') {
    $page = get_page_by_path($slug);
    if (!$page) {
        return $fallback;
    }
    $value = get_post_meta($page->ID, $key, true);
    return ($value !== '' && $value !== null) ? $value : $fallback;
}

/**
 * Get a Demie page meta value for the current page in the loop.
 */
function demie_current_meta($key, $fallback = '') {
    $value = get_post_meta(get_the_ID(), $key, true);
    return ($value !== '' && $value !== null) ? $value : $fallback;
}

/**
 * Echo a section label with the leading "NN //" prefix auto-wrapped in the
 * accent <span>, exactly like the Kimono markup: <span>01//</span> Our Services
 */
function demie_heading_sub($key, $fallback = '', $slug = null) {
    $value = is_null($slug) ? demie_current_meta($key) : demie_page_meta($slug, $key);
    if ($value === '') {
        $value = $fallback;
    }
    if ($value === '') {
        return;
    }
    if (preg_match('/^((?:\d+\s*\/\/|#)\s*)(.*)$/s', $value, $m)) {
        echo '<span>' . esc_html($m[1]) . '</span>' . esc_html($m[2]);
    } else {
        echo esc_html($value);
    }
}

/**
 * Echo a multi-part heading: part1 <span>part2</span> [<br> part3]
 * Matches the Kimono H1 pattern. Empty parts are skipped gracefully.
 */
function demie_heading_h1($l1_key, $l2_key, $l3_key = null, $l1_fb = '', $l2_fb = '', $l3_fb = '', $slug = null) {
    $get = function ($key, $fb) use ($slug) {
        $v = is_null($slug) ? demie_current_meta($key) : demie_page_meta($slug, $key);
        return ($v === '') ? $fb : $v;
    };

    $l1 = $get($l1_key, $l1_fb);
    $l2 = $get($l2_key, $l2_fb);
    $l3 = $l3_key ? $get($l3_key, $l3_fb) : '';

    if ($l1 === '' && $l2 === '' && $l3 === '') {
        return;
    }

    echo esc_html($l1);
    if ($l2 !== '') {
        echo ' <span>' . esc_html($l2) . '</span>';
    }
    if ($l3 !== '') {
        echo ' <br>' . esc_html($l3);
    }
}

/* ---------- Images ---------- */

/**
 * Resolve an image meta field (attachment ID) to a URL. Falls back to a
 * bundled theme image when the field is empty.
 */
function demie_image_url($key, $fallback = '', $size = 'full') {
    $id = (int) demie_current_meta($key);
    if ($id) {
        $url = wp_get_attachment_image_url($id, $size);
        if ($url) {
            return $url;
        }
    }
    return $fallback ? DEMIE_URI . '/assets/img/' . ltrim($fallback, '/') : '';
}

/* ---------- Slider ---------- */

/**
 * Slides for the homepage hero, in admin menu order.
 */
function demie_get_slides() {
    return get_posts([
        'post_type'      => 'demie_slide',
        'posts_per_page' => 12,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);
}

/* ---------- Services ---------- */

/**
 * Services in admin menu order.
 */
function demie_get_services() {
    return get_posts([
        'post_type'      => 'demie_service',
        'posts_per_page' => 24,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);
}

/**
 * Short blurb of a service (homepage card text).
 */
function demie_service_short($post) {
    $short = get_post_meta($post->ID, '_demie_short_desc', true);
    return ($short !== '') ? $short : wp_strip_all_tags(get_the_excerpt($post));
}

/* ---------- Portfolio ---------- */

/**
 * Portfolio items in admin menu order, featured images fetched.
 */
function demie_get_portfolio($limit = -1) {
    return get_posts([
        'post_type'      => 'demie_portfolio',
        'posts_per_page' => $limit,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);
}

/**
 * Full-size featured image URL of a portfolio item ('' when none set).
 */
function demie_portfolio_img($post, $size = 'large') {
    $img = get_the_post_thumbnail_url($post, $size);
    return $img ? $img : '';
}

/* ---------- Testimonials ---------- */

/**
 * Testimonials in admin menu order.
 */
function demie_get_testimonials() {
    return get_posts([
        'post_type'      => 'demie_testimonial',
        'posts_per_page' => 12,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);
}

/**
 * Render the star rating block for a testimonial.
 */
function demie_stars($count) {
    $count = max(1, min(5, (int) $count));
    for ($i = 0; $i < $count; $i++) {
        echo '<i class="bi bi-star-fill"></i>';
    }
}

/* ---------- FAQ ---------- */

/**
 * FAQs in admin menu order.
 */
function demie_get_faqs() {
    return get_posts([
        'post_type'      => 'demie_faq',
        'posts_per_page' => 24,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);
}
