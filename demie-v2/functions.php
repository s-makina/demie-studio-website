<?php
if (!defined('ABSPATH')) exit;

define('DEMIE_VERSION', '0.1.11');
define('DEMIE_DIR', get_template_directory());
define('DEMIE_URI', get_template_directory_uri());

require_once DEMIE_DIR . '/inc/contact.php';
require_once DEMIE_DIR . '/inc/settings.php';
require_once DEMIE_DIR . '/inc/cpts.php';
require_once DEMIE_DIR . '/inc/metaboxes.php';
require_once DEMIE_DIR . '/inc/template-tags.php';
require_once DEMIE_DIR . '/inc/seed.php';

/* ---------- V2 Customizer: hero + content fallbacks ---------- */

function demie_v2_customize($wp_customize) {
    $wp_customize->add_section('demie_v2_hero', [
        'title'    => __('V2 Hero', 'demie-v2'),
        'priority' => 30,
    ]);
    $settings = [
        'demie_v2_hero_eyebrow' => 'Fine Art Wedding Photography & Cinematography',
        'demie_v2_hero_title_a' => 'Your Story,',
        'demie_v2_hero_title_b' => 'Beautifully Remembered.',
        'demie_v2_hero_sub'     => 'Capturing intimate celebrations, quiet romance, and timeless grandeur worldwide with an elevated editorial eye and pure emotional honesty.',
        'demie_v2_hero_video'   => DEMIE_URI . '/assets/video/hero.mp4',
        'demie_v2_hero_poster'  => DEMIE_URI . '/assets/video/hero-poster.jpg',
    ];
    foreach ($settings as $key => $def) {
        $wp_customize->add_setting($key, [
            'default'           => $def,
            'sanitize_callback' => 'wp_kses_post',
            'transport'         => 'refresh',
        ]);
        $label = ucwords(str_replace(['demie_v2_hero_', '_'], ['', ' '], $key));
        $control_args = ['label' => $label, 'section' => 'demie_v2_hero', 'type' => 'text'];
        if (str_contains($key, 'sub')) $control_args['type'] = 'textarea';
        if (str_contains($key, 'video') || str_contains($key, 'poster')) $control_args['type'] = 'url';
        $wp_customize->add_control($key, $control_args);
    }

    $wp_customize->add_section('demie_v2_general', [
        'title'    => __('V2 Content', 'demie-v2'),
        'priority' => 31,
    ]);
    $general = [
        'demie_v2_statement_quote' => '“For the moments you’ll want to remember forever — raw, poetic, and effortlessly true.”',
        'demie_v2_statement_text'  => 'We believe the most memorable photographs aren’t forced or staged into stiff poses. They are discovered in the shared glances, the unhurried laughter, the gentle clasp of hands, and the spontaneous joy of two lives uniting. We blend documentary intimacy with high-fashion editorial composition to craft archives worthy of heirloom preservation.',
        'demie_v2_booking_title'   => 'Let’s Tell Your Story.',
        'demie_v2_booking_text'    => 'Your wedding deserves more than photographs. It deserves to be remembered with intention, artfulness, and enduring reverence.',
    ];
    foreach ($general as $key => $def) {
        $wp_customize->add_setting($key, ['default' => $def, 'sanitize_callback' => 'wp_kses_post']);
        $wp_customize->add_control($key, [
            'label' => ucwords(str_replace(['demie_v2_', '_'], ['', ' '], $key)),
            'section' => 'demie_v2_general',
            'type' => 'textarea',
        ]);
    }
}
add_action('customize_register', 'demie_v2_customize');

function demie_v2_get($key, $fallback = '') {
    $v = get_theme_mod($key, $fallback);
    return ($v !== '' && $v !== null) ? $v : $fallback;
}

/* ---------- Setup ---------- */

function demie_setup() {
    load_theme_textdomain('demie-v2', DEMIE_DIR . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    add_theme_support('custom-logo', ['height' => 60, 'width' => 250, 'flex-height' => true, 'flex-width' => true]);

    register_nav_menus([
        'primary' => __('Primary Navigation', 'demie-v2'),
        'footer'  => __('Footer Navigation', 'demie-v2'),
    ]);
}
add_action('after_setup_theme', 'demie_setup');

function demie_content_width() {
    $GLOBALS['content_width'] = apply_filters('demie_content_width', 1200);
}
add_action('after_setup_theme', 'demie_content_width', 0);

/* ---------- Assets: Tailwind CDN + v2 css/js ---------- */

function demie_enqueue_assets() {
    // Tailwind via CDN (per design brief — keeps v2/code.html utilities working).
    wp_enqueue_script('demie-tailwind-cdn', 'https://cdn.tailwindcss.com', [], null, false);
    $tailwind_config = "tailwind.config = { theme: { extend: { fontFamily: { serif: ['\"Cormorant Garamond\"','Georgia','serif'], sans: ['\"Plus Jakarta Sans\"','sans-serif'] }, colors: { brand: { cream:'#FDFBF7', bone:'#F7F4EE', stone:'#EAE5DB', champagne:'#D9C8B4', gold:'#C5A880', charcoal:'#1A1816', deep:'#121110', muted:'#7A756E' } }, letterSpacing: { widest2:'0.25em', luxury:'0.18em' } } } }";
    wp_add_inline_script('demie-tailwind-cdn', $tailwind_config, 'after');

    wp_enqueue_style('demie-v2-fonts', 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600&display=swap', [], null);
    wp_enqueue_style('demie-v2', DEMIE_URI . '/assets/css/v2.css', [], DEMIE_VERSION);

    wp_enqueue_script('demie-v2-theme', DEMIE_URI . '/assets/js/theme-v2.js', [], DEMIE_VERSION, true);
    // Contact form AJAX (reuse v1 handler).
    wp_enqueue_script('demie-v2-forms', DEMIE_URI . '/assets/js/demie-forms.js', ['jquery'], DEMIE_VERSION, true);
    wp_localize_script('demie-v2-forms', 'demieCtx', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('demie-contact'),
        'siteUrl' => home_url('/'),
    ]);
}
add_action('wp_enqueue_scripts', 'demie_enqueue_assets');

function demie_head_extras() {
    echo '<link rel="icon" href="' . esc_url(DEMIE_URI . '/favicon.png') . '" type="image/png">' . "\n";
}
add_action('wp_head', 'demie_head_extras', 1);

/* ---------- Nav walker: v2 underline hover markup ---------- */

class Demie_V2_Walker extends Walker_Nav_Menu {
    public function start_lvl(&$output, $depth = 0, $args = null) {
        $output .= '<ul class="sub-menu absolute top-full left-0 bg-brand-deep/95 backdrop-blur-md py-3 px-5 space-y-2 min-w-[200px] text-left shadow-xl">';
    }
    public function end_lvl(&$output, $depth = 0, $args = null) {
        $output .= '</ul>';
    }
    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $is_current = in_array('current-menu-item', $classes, true);
        $li_class = 'relative group' . ($is_current ? ' text-brand-champagne' : '');
        $a_class = "hover:text-brand-champagne transition-colors py-1 relative after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-full after:h-[1px] after:bg-brand-champagne after:transition-transform after:duration-300 " . ($is_current ? 'text-brand-champagne after:scale-x-100' : 'after:scale-x-0 hover:after:scale-x-100');
        $output .= '<li class="' . esc_attr($li_class) . '">';
        $output .= '<a class="' . esc_attr($a_class) . '" href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';
    }
    public function end_el(&$output, $item, $depth = 0, $args = null) {
        $output .= '</li>';
    }
}

function demie_v2_fallback_menu() {
    $items = [
        [__('Home', 'demie-v2'), home_url('/')],
        [__('About', 'demie-v2'), demie_page_url('about-us')],
        [__('Weddings', 'demie-v2'), demie_page_url('gallery')],
        [__('Portfolio', 'demie-v2'), demie_page_url('gallery')],
        [__('Films & Stills', 'demie-v2'), demie_page_url('services')],
        [__('Contact', 'demie-v2'), demie_page_url('contact')],
    ];
    foreach ($items as [$label, $url]) {
        echo '<a class="hover:text-brand-champagne transition-colors py-1" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
}

function demie_render_primary_menu() {
    if (has_nav_menu('primary')) {
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => false,
            'items_wrap'     => '%3$s',
            'walker'         => new Demie_V2_Walker(),
            'fallback_cb'    => 'demie_v2_fallback_menu',
        ]);
    } else {
        demie_v2_fallback_menu();
    }
}

function demie_v2_mobile_menu() {
    if (has_nav_menu('primary')) {
        $items = wp_get_nav_menu_items(get_nav_menu_locations()['primary']);
        if ($items) {
            foreach ($items as $item) {
                echo '<div><a class="mobile-nav-link hover:text-brand-champagne transition-colors" href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a></div>';
            }
            return;
        }
    }
    $fallbacks = [
        [__('Home', 'demie-v2'), '#home'],
        [__('About', 'demie-v2'), '#statement'],
        [__('Weddings & Portfolio', 'demie-v2'), '#gallery'],
        [__('Featured Story', 'demie-v2'), '#featured-story'],
        [__('Photography & Film', 'demie-v2'), '#services'],
        [__('Kind Words', 'demie-v2'), '#testimonials'],
        [__('Contact', 'demie-v2'), '#booking'],
    ];
    foreach ($fallbacks as [$label, $url]) {
        echo '<div><a class="mobile-nav-link hover:text-brand-champagne transition-colors" href="' . esc_url($url) . '">' . esc_html($label) . '</a></div>';
    }
}

function demie_page_url($slug) {
    $page = get_page_by_path($slug);
    if ($page) return get_permalink($page);
    return home_url('/' . $slug);
}

/**
 * Gallery cards for the v2 masonry grid, sourced from the Demie Gallery plugin.
 *
 * Source chain: `homepage` gallery (or latest when $slug is empty) ->
 * Portfolio Items (plugin inactive/empty) -> [] (caller shows curated fallbacks).
 *
 * @param int    $limit Max cards; 0 = all.
 * @param string $slug  Gallery slug; '' = latest published gallery.
 * @return array[] Each: title, loc, img, kind (photo|video).
 */
function demie_v2_gallery_cards($limit = 9, $slug = 'homepage') {
    $cards = [];

    if (function_exists('demie_g_get_media') && function_exists('demie_g_resolve_item')) {
        $gid = 0;
        if ('' !== (string) $slug && function_exists('demie_g_resolve_gallery_id')) {
            $gid = demie_g_resolve_gallery_id(['slug' => $slug]);
        }
        if (!$gid && function_exists('demie_g_latest_gallery_id')) {
            $gid = demie_g_latest_gallery_id();
        }
        if ($gid) {
            $gtitle = get_the_title($gid);
            $media  = demie_g_get_media($gid);
            if ($limit > 0) {
                $media = array_slice($media, 0, $limit);
            }
            foreach ($media as $entry) {
                $r = demie_g_resolve_item($entry);
                if (empty($r['thumb'])) {
                    continue;
                }
                $title = '' !== $r['title'] ? $r['title'] : $gtitle;
                $cards[] = [
                    'title' => $title,
                    'loc'   => 'video' === $r['kind'] ? __('Film', 'demie-v2') . ' • ' . $gtitle : $gtitle,
                    'img'   => $r['thumb'],
                    'kind'  => $r['kind'],
                ];
            }
        }
    }

    if (!$cards && function_exists('demie_get_portfolio')) {
        foreach (demie_get_portfolio($limit > 0 ? $limit : 24) as $p) {
            $img = demie_portfolio_img($p, 'large');
            if (!$img) {
                continue;
            }
            $cards[] = [
                'title' => get_the_title($p),
                'loc'   => get_the_date('', $p),
                'img'   => $img,
                'kind'  => 'photo',
            ];
        }
    }

    return $cards;
}

function demie_logo_wordmark($class = '') {
    if (has_custom_logo()) {
        $logo_id  = get_theme_mod('custom_logo');
        $logo_url = wp_get_attachment_image_url($logo_id, 'full');
        if ($logo_url) {
            echo '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr(get_bloginfo('name')) . '" class="max-h-10 w-auto">';
            return;
        }
    }
    echo '<span class="font-serif text-2xl md:text-3xl tracking-luxury uppercase font-normal">Demie Photography</span>';
}
