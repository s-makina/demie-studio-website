<?php

if (!defined('ABSPATH')) exit;

define('DEMIE_VERSION', '1.3.69');
define('DEMIE_DIR', get_template_directory());
define('DEMIE_URI', get_template_directory_uri());

require_once DEMIE_DIR . '/inc/contact.php';
require_once DEMIE_DIR . '/inc/settings.php';
require_once DEMIE_DIR . '/inc/cpts.php';
require_once DEMIE_DIR . '/inc/metaboxes.php';
require_once DEMIE_DIR . '/inc/template-tags.php';
require_once DEMIE_DIR . '/inc/seed.php';


function demie_setup() {
    load_theme_textdomain('demie-photography', DEMIE_DIR . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');

    add_theme_support('custom-logo', [
        'height'      => 60,
        'width'       => 250,
        'flex-height' => true,
        'flex-width'  => true,
    ]);

    register_nav_menus([
        'primary'      => __('Primary Navigation', 'demie-photography'),
        'footer-left'  => __('Footer Links (Left)', 'demie-photography'),
        'footer-right' => __('Footer Links (Right)', 'demie-photography'),
    ]);
}
add_action('after_setup_theme', 'demie_setup');

function demie_content_width() {
    $GLOBALS['content_width'] = apply_filters('demie_content_width', 1200);
}
add_action('after_setup_theme', 'demie_content_width', 0);

/* ---------- Assets ---------- */

function demie_enqueue_assets() {
    // main.css pulls in fonts, bootstrap-icons, bootstrap and all vendor CSS via @import.
    wp_enqueue_style('demie-main', DEMIE_URI . '/assets/css/main.css', [], DEMIE_VERSION);
    wp_enqueue_style('demie-brand', DEMIE_URI . '/assets/css/brand.css', ['demie-main'], DEMIE_VERSION);

    wp_enqueue_script('demie-jquery', DEMIE_URI . '/assets/js/jquery-3.6.0.min.js', [], '3.6.0', true);
    wp_enqueue_script('demie-bootstrap', DEMIE_URI . '/assets/js/bootstrap.min.js', ['demie-jquery'], '5.3.0', true);

    wp_enqueue_script('demie-wow', DEMIE_URI . '/assets/vendor/wow/wow.min.js', ['demie-jquery'], null, true);
    wp_enqueue_script('demie-swiper', DEMIE_URI . '/assets/vendor/swiper/swiper-bundle.min.js', ['demie-jquery'], null, true);
    wp_enqueue_script('demie-swiper-gl', DEMIE_URI . '/assets/vendor/swiper/swiper-gl.min.js', ['demie-jquery'], null, true);
    wp_enqueue_script('demie-appear', DEMIE_URI . '/assets/vendor/odometer/appear.js', ['demie-jquery'], null, true);
    wp_enqueue_script('demie-odometer', DEMIE_URI . '/assets/vendor/odometer/odometer.js', ['demie-jquery'], null, true);
    wp_enqueue_script('demie-isotope', DEMIE_URI . '/assets/vendor/isotope/isotope.pkgd.min.js', ['demie-jquery'], null, true);
    wp_enqueue_script('demie-imagesloaded', DEMIE_URI . '/assets/vendor/isotope/imagesloaded.pkgd.min.js', ['demie-jquery'], null, true);
    wp_enqueue_script('demie-tilt', DEMIE_URI . '/assets/vendor/isotope/tilt.jquery.js', ['demie-jquery'], null, true);
    wp_enqueue_script('demie-isotope-init', DEMIE_URI . '/assets/vendor/isotope/isotope-init.js', ['demie-isotope', 'demie-imagesloaded', 'demie-tilt'], null, true);
    wp_enqueue_script('demie-fancybox', DEMIE_URI . '/assets/vendor/fancybox/jquery.fancybox.min.js', ['demie-jquery'], null, true);
    wp_enqueue_script('demie-flatpickr', DEMIE_URI . '/assets/vendor/flatpickr/flatpickr.min.js', ['demie-jquery'], null, true);
    wp_enqueue_script('demie-nice-select', DEMIE_URI . '/assets/vendor/nice-select/jquery.nice-select.min.js', ['demie-jquery'], null, true);
    wp_enqueue_script('demie-cursor-effect', DEMIE_URI . '/assets/vendor/cursor-effect/cursor-effect.js', ['demie-jquery'], null, true);

    wp_enqueue_script('demie-theme', DEMIE_URI . '/assets/js/theme.js', ['demie-jquery', 'demie-bootstrap'], DEMIE_VERSION, true);
    wp_enqueue_script('demie-forms', DEMIE_URI . '/assets/js/demie-forms.js', ['demie-jquery'], DEMIE_VERSION, true);

    wp_localize_script('demie-theme', 'demieCtx', [
        'ajaxUrl'   => admin_url('admin-ajax.php'),
        'nonce'     => wp_create_nonce('demie-contact'),
        'siteUrl'   => home_url('/'),
    ]);
}
add_action('wp_enqueue_scripts', 'demie_enqueue_assets');

/* ---------- Head extras ---------- */

function demie_head_extras() {
    ?>
    <link rel="icon" href="<?php echo esc_url(DEMIE_URI . '/favicon.png'); ?>" type="image/png">
    <?php
}
add_action('wp_head', 'demie_head_extras', 1);

/* ---------- Nav walker: outputs Kimono's main-menu markup ---------- */

class Demie_Walker extends Walker_Nav_Menu {
    public function start_lvl(&$output, $depth = 0, $args = null) {
        $output .= '<ul class="sub-menu">';
    }

    public function end_lvl(&$output, $depth = 0, $args = null) {
        $output .= '</ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $has_children = in_array('menu-item-has-children', $classes, true);
        $is_current = in_array('current-menu-item', $classes, true);

        $li_classes = ['menu-item'];
        if ($has_children) $li_classes[] = 'menu-item-has-children';
        if ($is_current)   $li_classes[] = 'current-menu-item';

        $output .= '<li class="' . esc_attr(implode(' ', $li_classes)) . '">';
        $output .= '<a href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';
    }

    public function end_el(&$output, $item, $depth = 0, $args = null) {
        $output .= '</li>';
    }
}

/**
 * Fallback menu when no menu is assigned to a location yet: builds the same
 * Kimono markup from the theme's pages so the live site works on first run.
 */
function demie_menu_fallback($args = []) {
    $location = isset($args['theme_location']) ? $args['theme_location'] : '';

    if ('primary' === $location) {
        $items = [
            ['label' => __('Home', 'demie-photography'), 'url' => home_url('/')],
            ['label' => __('Gallery', 'demie-photography'), 'url' => demie_page_url('gallery')],
            ['label' => __('About Us', 'demie-photography'), 'url' => demie_page_url('about-us')],
            ['label' => __('Services', 'demie-photography'), 'url' => demie_page_url('services')],
            ['label' => __('Blog', 'demie-photography'), 'url' => demie_page_url('blog')],
            ['label' => __('Contact', 'demie-photography'), 'url' => demie_page_url('contact')],
        ];
    } elseif ('footer-left' === $location) {
        $items = [
            ['label' => __('About Us', 'demie-photography'), 'url' => demie_page_url('about-us')],
            ['label' => __('Services', 'demie-photography'), 'url' => demie_page_url('services')],
            ['label' => __('Gallery', 'demie-photography'), 'url' => demie_page_url('gallery')],
            ['label' => __('Blog', 'demie-photography'), 'url' => demie_page_url('blog')],
            ['label' => __('Contact Us', 'demie-photography'), 'url' => demie_page_url('contact')],
        ];
    } else {
        $items = [
            ['label' => __('Book a Session', 'demie-photography'), 'url' => demie_page_url('contact')],
            ['label' => __('Recent Posts', 'demie-photography'), 'url' => demie_page_url('blog')],
            ['label' => __('Latest News', 'demie-photography'), 'url' => demie_page_url('blog')],
            ['label' => __('Contact Us', 'demie-photography'), 'url' => demie_page_url('contact')],
        ];
    }

    echo '<ul class="main-menu">';
    foreach ($items as $item) {
        echo '<li class="menu-item"><a href="' . esc_url($item['url']) . '">' . esc_html($item['label']) . '</a></li>';
    }
    echo '</ul>';
}

function demie_page_url($slug) {
    $page = get_page_by_path($slug);
    if ($page) {
        return get_permalink($page);
    }
    return home_url('/' . $slug);
}

function demie_render_primary_menu() {
    if (has_nav_menu('primary')) {
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => false,
            'items_wrap'     => '<ul class="main-menu">%3$s</ul>',
            'walker'         => new Demie_Walker(),
            'fallback_cb'    => 'demie_menu_fallback',
        ]);
    } else {
        demie_menu_fallback(['theme_location' => 'primary']);
    }
}

function demie_render_footer_menu($location) {
    if (has_nav_menu($location)) {
        wp_nav_menu([
            'theme_location' => $location,
            'container'      => false,
            'items_wrap'     => '<ul>%3$s</ul>',
            'fallback_cb'    => 'demie_menu_fallback',
        ]);
    } else {
        demie_menu_fallback(['theme_location' => $location]);
    }
}

/* ---------- Brand helpers (Studio Details from Demie Settings) ---------- */

/* demie_phone(), demie_email(), demie_phone_url(), demie_whatsapp_url(),
 * demie_location(), demie_maps_url() and demie_social_url() now live in
 * inc/settings.php and read from the Demie Settings page with the CONTEXT.md
 * brand facts as seed defaults. */

function demie_logo_wordmark($class = '') {
    $extra = $class ? ' ' . $class : '';
    if (has_custom_logo()) {
        $logo_id  = get_theme_mod('custom_logo');
        $logo_url = wp_get_attachment_image_url($logo_id, 'full');
        echo '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr(get_bloginfo('name')) . '">';
    } else {
        echo '<span class="logo-wordmark' . esc_attr($extra) . '">Demie<span class="logo-sub"> Photography</span></span>';
    }
}
