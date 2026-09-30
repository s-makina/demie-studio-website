<?php
/**
 * Plugin Name: Demie Gallery
 * Plugin URI:  https://demiephotography.example
 * Description: Owner-managed Galleries (ordered photos + videos incl. YouTube/Vimeo links) rendered anywhere via [demie_gallery] with the Kimono portfolio layouts.
 * Version:     0.1.0
 * Author:      Demie Photography
 * Text Domain: demie-gallery
 * Domain Path: /languages
 *
 * Decision: docs/adr/0002-gallery-as-plugin.md
 * Plan:     docs/superpowers/specs/2026-09-28-demie-gallery-plugin-plan.md
 *
 * A Gallery is a demie_gallery CPT post whose ordered media list lives in the
 * _demie_g_media post meta as a JSON array of Gallery Media Items
 * ({"type":"attachment","id":N} | {"type":"embed","url":"…"}). Rendering is
 * one shared renderer feeding per-layout templates; pagination is load_more
 * (AJAX), numbered (?gallery-page=N) or none.
 */

if (!defined('ABSPATH')) exit;

define('DEMIE_G_VERSION', '0.1.1');
define('DEMIE_G_DIR', plugin_dir_path(__FILE__));
define('DEMIE_G_URI', plugin_dir_url(__FILE__));

require_once DEMIE_G_DIR . 'inc/cpt.php';
require_once DEMIE_G_DIR . 'inc/media-list.php';
require_once DEMIE_G_DIR . 'inc/admin-ui.php';
require_once DEMIE_G_DIR . 'inc/shortcode.php';
require_once DEMIE_G_DIR . 'inc/render.php';
require_once DEMIE_G_DIR . 'inc/ajax.php';
require_once DEMIE_G_DIR . 'inc/assets.php';
require_once DEMIE_G_DIR . 'inc/seed.php';

/**
 * Activation: register the CPT, then flush rewrite rules so anything
 * permalink-related settles immediately.
 */
function demie_g_activate() {
    demie_g_register_cpt();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'demie_g_activate');

function demie_g_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'demie_g_deactivate');

/**
 * The lightbox (Fancybox) comes from the theme. If it is not enqueued the
 * plugin still renders — items simply fall back to plain links — but warn
 * admins once (ADR-0002: degrade gracefully, not fatal).
 */
add_action('admin_notices', function () {
    if (!function_exists('demie_g_is_our_admin_screen') || !demie_g_is_our_admin_screen() || !current_user_can('activate_plugins')) {
        return;
    }
    if (wp_script_is('demie-fancybox', 'registered') || defined('DEMIE_VERSION')) {
        return; // the Demie theme (or another plugin) provides the lightbox
    }
    echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Demie Gallery', 'demie-gallery') . ':</strong> '
        . esc_html__('The active theme does not provide a Fancybox lightbox. Galleries will render, but images and videos will open as plain links instead of a lightbox.', 'demie-gallery')
        . '</p></div>';
});
