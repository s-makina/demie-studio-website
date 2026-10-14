<?php
/**
 * Front-end asset enqueuing + lightbox wiring.
 *
 * gallery.css / gallery.js are enqueued only when the current post actually
 * contains the [demie_gallery] shortcode.
 */

if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
    $enqueue = false;

    if (is_singular()) {
        $post = get_post();
        if ($post && has_shortcode((string) $post->post_content, 'demie_gallery')) {
            $enqueue = true;
        }
    }

    // The Gallery page wrapper also renders the shortcode via do_shortcode
    // without it being in post_content.
    if (!$enqueue && is_page_template('page-gallery.php')) {
        $enqueue = true;
    }

    if (!$enqueue) {
        return;
    }

    // Theme-provided vendor stack this plugin relies on.
    foreach (['demie-jquery', 'demie-isotope', 'demie-imagesloaded', 'demie-swiper', 'demie-fancybox'] as $handle) {
        if (wp_script_is($handle, 'registered')) {
            wp_enqueue_script($handle);
        }
    }

    wp_enqueue_style('demie-g-gallery', DEMIE_G_URI . 'assets/gallery.css', [], DEMIE_G_VERSION);
    wp_enqueue_script('demie-g-gallery', DEMIE_G_URI . 'assets/gallery.js', ['demie-jquery'], DEMIE_G_VERSION, true);

    wp_localize_script('demie-g-gallery', 'demieGallery', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('demie_g_front'),
    ]);
}, 20);

/**
 * Filterable layouts need the Fancybox lightbox to span the whole gallery;
 * embeds open as iframes. Items carry data-fancybox="demie-g-{id}" which
 * Fancybox auto-binds; nothing else to wire server-side.
 */
