<?php
/**
 * Demo-gallery seed: on first activation (or version bump) create one Gallery
 * seeded with the theme's 12 portfolio photos, so the Gallery page shows real
 * content immediately after upgrade (plan Step 7).
 *
 * Idempotent: runs only when no published gallery exists, and reuses the
 * theme's already-seeded attachments instead of duplicating them.
 */

if (!defined('ABSPATH')) exit;

const DEMIE_G_SEED_VERSION = '0.1.0';
const DEMIE_G_SEED_SLUG    = 'demie-gallery-showcase';

function demie_g_seed_maybe() {
    if (get_option('demie_g_seed_version') === DEMIE_G_VERSION) {
        return;
    }

    // Seed demo gallery only if NO galleries exist
    $existing = get_posts([
        'post_type'      => 'demie_gallery',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);

    if (empty($existing)) {
        demie_g_seed_demo_gallery();
    }

    // Always ensure homepage gallery exists (idempotent)
    demie_g_seed_homepage_gallery();

    update_option('demie_g_seed_version', DEMIE_G_VERSION);
}
add_action('admin_init', 'demie_g_seed_maybe');

/**
 * Build the demo gallery media list from the theme's seeded portfolio items
 * (their featured images are already in the Media Library).
 */
function demie_g_seed_demo_gallery() {
    $items = [];

    $portfolio = get_posts([
        'post_type'      => 'demie_portfolio',
        'post_status'    => 'publish',
        'posts_per_page' => 12,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ]);

    foreach ($portfolio as $item) {
        $thumb_id = (int) get_post_thumbnail_id($item);
        if ($thumb_id && 'attachment' === get_post_type($thumb_id)) {
            $items[] = ['type' => 'attachment', 'id' => $thumb_id];
        }
    }

    // Fall back to any six image attachments (e.g. theme not seeded yet).
    if (count($items) < 3) {
        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => 6,
            'post_status'    => 'inherit',
            'fields'         => 'ids',
        ]);
        foreach ($attachments as $id) {
            $items[] = ['type' => 'attachment', 'id' => (int) $id];
        }
    }

    if (!$items) {
        return 0; // nothing to seed from — skip quietly
    }

    $gallery_id = wp_insert_post([
        'post_type'   => 'demie_gallery',
        'post_status' => 'publish',
        'post_name'   => DEMIE_G_SEED_SLUG,
        'post_title'  => __('Demie Photography Showcase', 'demie-gallery'),
        'post_author' => get_current_user_id() ?: 1,
    ]);

    if (is_wp_error($gallery_id) || !$gallery_id) {
        return 0;
    }

    update_post_meta($gallery_id, DEMIE_G_META_MEDIA, wp_json_encode($items));
    update_post_meta($gallery_id, DEMIE_G_META_DESC, __('A selection of our weddings, portraits, events and studio work.', 'demie-gallery'));

    return (int) $gallery_id;
}

/**
 * Create a dedicated "homepage" gallery with 6 curated images for the
 * front-page gallery section. Runs alongside the demo gallery seed.
 */
function demie_g_seed_homepage_gallery() {
    // Check if homepage gallery already exists
    $existing = get_page_by_path('homepage', OBJECT, 'demie_gallery');
    if ($existing) {
        return 0;
    }

    $items = [];

    // Try to get 6 portfolio items for the homepage gallery
    $portfolio = get_posts([
        'post_type'      => 'demie_portfolio',
        'post_status'    => 'publish',
        'posts_per_page' => 6,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ]);

    foreach ($portfolio as $item) {
        $thumb_id = (int) get_post_thumbnail_id($item);
        if ($thumb_id && 'attachment' === get_post_type($thumb_id)) {
            $items[] = ['type' => 'attachment', 'id' => $thumb_id];
        }
    }

    // Fall back to any 6 image attachments
    if (count($items) < 6) {
        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => 6,
            'post_status'    => 'inherit',
            'fields'         => 'ids',
        ]);
        foreach ($attachments as $id) {
            if (count($items) >= 6) break;
            $items[] = ['type' => 'attachment', 'id' => (int) $id];
        }
    }

    if (!$items) {
        return 0; // nothing to seed from — skip quietly
    }

    $gallery_id = wp_insert_post([
        'post_type'   => 'demie_gallery',
        'post_status' => 'publish',
        'post_name'   => 'homepage',
        'post_title'  => __('Homepage Gallery', 'demie-gallery'),
        'post_author' => get_current_user_id() ?: 1,
    ]);

    if (is_wp_error($gallery_id) || !$gallery_id) {
        return 0;
    }

    update_post_meta($gallery_id, DEMIE_G_META_MEDIA, wp_json_encode($items));
    update_post_meta($gallery_id, DEMIE_G_META_DESC, __('Featured images for the homepage gallery section.', 'demie-gallery'));

    return (int) $gallery_id;
}
