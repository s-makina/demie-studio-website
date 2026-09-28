<?php
/**
 * Theme auto-seed for Demie Photography.
 *
 * Decision: docs/adr/0001-cpts-and-metaboxes-for-editable-content.md
 *
 * Runs on theme activation and whenever the theme version changes
 * (admin_init). Idempotent: every item is created only if it does not
 * already exist, so re-activation and version bumps never duplicate content.
 *
 * Creates:
 * - pretty permalinks
 * - pages: home, about-us, services, gallery, blog, contact (template files
 *   assigned, front page + posts page set)
 * - CPT entries carrying the current template copy: services, testimonials,
 *   FAQs, slides, portfolio items
 * - Studio Details settings (phone, email, location, socials)
 * - Home Sections + Page Headings meta on the seeded pages
 */

if (!defined('ABSPATH')) exit;

add_action('after_switch_theme', 'demie_seed_run');
add_action('admin_init', 'demie_seed_maybe_upgrade');

/**
 * Re-run the seed when the theme version changes.
 */
function demie_seed_maybe_upgrade() {
    if (get_option('demie_seed_version') === DEMIE_VERSION) {
        return;
    }
    demie_seed_run();
    update_option('demie_seed_version', DEMIE_VERSION);
}

/**
 * Entry point.
 */
function demie_seed_run() {
    demie_seed_permalinks();
    demie_seed_pages();
    demie_seed_settings();
    demie_seed_services();
    demie_seed_testimonials();
    demie_seed_faqs();
    demie_seed_slides();
    demie_seed_portfolio();
    demie_seed_home_meta();
    demie_seed_page_meta();
    flush_rewrite_rules();
}

/* ---------- Helpers ---------- */

/**
 * Pretty permalinks for the menu fallback links.
 */
function demie_seed_permalinks() {
    $structure = get_option('permalink_structure');
    if (empty($structure) || strpos($structure, '%postname%') === false) {
        update_option('permalink_structure', '/%postname%/');
    }
}

/**
 * Existing page ID by slug, or 0.
 */
function demie_seed_page_id($slug) {
    $page = get_page_by_path($slug);
    return $page ? (int) $page->ID : 0;
}

/**
 * Create a page if missing (idempotent), ensure its template, return ID (0 on failure).
 */
function demie_seed_page($slug, $title, $template = '') {
    $id = demie_seed_page_id($slug);
    if ($id) {
        if ($template && get_page_template_slug($id) !== $template) {
            update_post_meta($id, '_wp_page_template', $template);
        }
        return $id;
    }

    $id = wp_insert_post([
        'post_type'   => 'page',
        'post_status' => 'publish',
        'post_name'   => $slug,
        'post_title'  => $title,
    ]);
    if (is_wp_error($id) || !$id) {
        return 0;
    }
    if ($template) {
        update_post_meta($id, '_wp_page_template', $template);
    }
    return (int) $id;
}

/**
 * Create a CPT entry if a post of that type with the same slug exists;
 * otherwise insert it (idempotent) and attach a bundled image as its
 * featured image. The thumbnail is only set on creation — re-seeds never
 * overwrite the owner's chosen images. Returns the post ID or 0.
 */
function demie_seed_post($type, $slug, $title, $content = '', $meta = [], $menu_order = 0, $thumb_path = '') {
    $existing = get_posts([
        'post_type'      => $type,
        'name'           => $slug,
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);
    if (!empty($existing)) {
        $existing_id = (int) $existing[0];
        // Backfill: attach the bundled image only when the entry has no
        // thumbnail of its own — never overwrite the owner's choice.
        if ($thumb_path && !has_post_thumbnail($existing_id)) {
            $attachment_id = demie_seed_attachment($thumb_path, $title);
            if ($attachment_id) {
                set_post_thumbnail($existing_id, $attachment_id);
            }
        }
        return $existing_id;
    }

    $id = wp_insert_post([
        'post_type'    => $type,
        'post_status'  => 'publish',
        'post_name'    => $slug,
        'post_title'   => $title,
        'post_content' => $content,
        'menu_order'   => $menu_order,
    ]);
    if (is_wp_error($id) || !$id) {
        return 0;
    }
    foreach ($meta as $key => $value) {
        update_post_meta($id, $key, $value);
    }
    if ($thumb_path) {
        $attachment_id = demie_seed_attachment($thumb_path, $title);
        if ($attachment_id) {
            set_post_thumbnail($id, $attachment_id);
        }
    }
    return (int) $id;
}

/**
 * Copy a bundled theme image into the media library (idempotently) and
 * return the attachment ID, or 0 on failure. Deduped by the source file's
 * md5 stored on the attachment, so re-seeds never duplicate entries.
 */
function demie_seed_attachment($source_path, $title = '') {
    $source_path = DEMIE_DIR . '/' . ltrim($source_path, '/');
    if (!file_exists($source_path)) {
        return 0;
    }

    $hash = md5_file($source_path);
    $existing = get_posts([
        'post_type'      => 'attachment',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_key'       => '_demie_seed_md5',
        'meta_value'     => $hash,
    ]);
    if (!empty($existing)) {
        return (int) $existing[0];
    }

    if (!function_exists('wp_generate_attachment_metadata')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    // Copy the trusted theme file into the uploads dir directly (bypassing
    // upload_mimes — seeding must not depend on who is logged in, and SVGs
    // are blocked for anonymous contexts).
    $uploads  = wp_upload_dir();
    if (!empty($uploads['error'])) {
        return 0;
    }
    $filename = wp_unique_filename($uploads['path'], basename($source_path));
    $dest     = trailingslashit($uploads['path']) . $filename;
    if (!copy($source_path, $dest)) {
        return 0;
    }

    $mimes = [
        'svg'  => 'image/svg+xml',
        'svgz' => 'image/svg+xml',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
    ];
    $ext  = strtolower(pathinfo($dest, PATHINFO_EXTENSION));
    $mime = isset($mimes[$ext]) ? $mimes[$ext] : 'application/octet-stream';

    $attachment_id = wp_insert_attachment([
        'post_mime_type' => $mime,
        'post_title'     => $title !== '' ? $title : sanitize_file_name(pathinfo($dest, PATHINFO_FILENAME)),
        'post_status'    => 'inherit',
    ], $dest);
    if (is_wp_error($attachment_id) || !$attachment_id) {
        return 0;
    }

    wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $dest));
    update_post_meta($attachment_id, '_demie_seed_md5', $hash);
    return (int) $attachment_id;
}

/* ---------- Pages ---------- */

function demie_seed_pages() {
    $front_id = demie_seed_page('home', __('Home', 'demie-photography'));
    $about    = demie_seed_page('about-us', __('About Us', 'demie-photography'), 'page-about-us.php');
    $services = demie_seed_page('services', __('Services', 'demie-photography'), 'page-services.php');
    $gallery  = demie_seed_page('gallery', __('Gallery', 'demie-photography'), 'page-gallery.php');
    $blog     = demie_seed_page('blog', __('Blog', 'demie-photography'), 'page-blog.php');
    $contact  = demie_seed_page('contact', __('Contact', 'demie-photography'), 'page-contact.php');

    if ($front_id) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $front_id);
    }
    if ($blog) {
        update_option('page_for_posts', $blog);
    }
}

/* ---------- Studio Details settings ---------- */

function demie_seed_settings() {
    $existing = get_option('demie_settings');
    if (is_array($existing) && isset($existing['phone']) && $existing['phone'] !== '') {
        return; // never overwrite owner edits
    }

    update_option('demie_settings', demie_settings_defaults());
}

/* ---------- CPT seed content (current template copy) ---------- */

function demie_seed_services() {
    // [slug, title, short blurb (homepage), full description (services page), icon file]
    $services = [
        ['wedding-photography', 'Wedding Photography',
            'Timeless wedding photography that tells the story of your day, from preparations to the last dance.',
            'Full-day coverage of your wedding — preparations, ceremony and reception — delivered as a beautifully edited gallery.',
            'icon-1.svg'],
        ['drone-cinematography', 'Drone Cinematography',
            'Stunning aerial views of venues, ceremonies and landscapes across Malawi.',
            'Licensed aerial photography and video for venues, events and landscapes across Malawi.',
            'icon-2.svg'],
        ['wedding-cinematography', 'Wedding Cinematography',
            'Cinematic films that let you relive every vow, speech and celebration.',
            'A cinematic film of your day, from the first look to the last dance, edited to music you love.',
            'icon-3.svg'],
        ['personal-portfolio-shoot', 'Personal Portfolio Shoot',
            'Studio and on-location portraits, editorial looks and personal branding sessions.',
            'Studio or on-location portrait sessions for individuals, creatives and professionals.',
            'icon-4.svg'],
        ['studio-photography', 'Studio Photography',
            'Controlled studio light for newborns, families, products and editorial looks.',
            'Controlled studio light for newborns, families, products and editorial looks.',
            'icon-5.svg'],
        ['event-photography', 'Event Photography',
            'Corporate events, parties and ceremonies covered discreetly and delivered fast.',
            'Corporate events, parties and ceremonies covered discreetly and delivered fast.',
            'icon-6.svg'],
    ];

    foreach ($services as $i => $s) {
        demie_seed_post('demie_service', $s[0], $s[1], $s[3], [
            '_demie_short_desc' => $s[2],
        ], $i, 'assets/img/services/' . $s[4]);
    }
}

function demie_seed_testimonials() {
    // [slug, author, quote, location, stars, photo]
    $testimonials = [
        ['rachel-jackson', 'Rachel Jackson',
            'I had an amazing photography session with team Demie Photography, highly recommended. They have an amazing atmosphere in their studio. I would love to visit again.',
            'Blantyre', 5, '4.jpg'],
        ['helen-jordan', 'Helen Jordan',
            'Demie captured our wedding beautifully. Every special moment of the day is there in the photos — we could not be happier with the results.',
            'Lilongwe', 5, '5.jpg'],
        ['chikondi-banda', 'Chikondi Banda',
            'Professional, friendly and creative. Our family portraits came out stunning and the whole session was so much fun. Thank you Demie Photography!',
            'Chilomoni', 5, '6.jpg'],
    ];

    foreach ($testimonials as $i => $t) {
        demie_seed_post('demie_testimonial', $t[0], $t[1], '', [
            '_demie_quote'    => $t[2],
            '_demie_location' => $t[3],
            '_demie_rating'   => (string) $t[4],
        ], $i, 'assets/img/testimonial/' . $t[5]);
    }
}

function demie_seed_faqs() {
    // [slug, question, answer]
    $faqs = [
        ['what-areas-do-you-serve', 'What areas do you serve?',
            'We are based in Chilomoni, Blantyre, and cover sessions across Malawi, including Lilongwe and surrounding areas.'],
        ['how-do-i-book-a-session', 'How do I book a session?',
            'Call or WhatsApp us on +265 884 44 48 02, or send a message through the contact form. We will confirm your date and package.'],
        ['how-long-does-a-session-take', 'How long does a session take?',
            'Portrait sessions usually take 1–2 hours. Weddings and events are quoted for a full or half day depending on your schedule.'],
        ['when-will-we-receive-our-photos', 'When will we receive our photos?',
            'Sneak peeks are delivered within a few days. Full edited galleries are typically ready within 2–3 weeks.'],
    ];

    foreach ($faqs as $i => $f) {
        demie_seed_post('demie_faq', $f[0], $f[1], $f[2], [], $i);
    }
}

function demie_seed_slides() {
    // [slug, title, subtitle, slider image]
    $slides = [
        ['demie-photography-weddings', 'Demie Photography', 'Weddings', '4.jpg'],
        ['demie-photography-portraits', 'Demie Photography', 'Portraits', '5.jpg'],
        ['demie-photography-events', 'Demie Photography', 'Events', '6.jpg'],
    ];

    foreach ($slides as $i => $s) {
        demie_seed_post('demie_slide', $s[0], $s[1], '', [
            '_demie_subtitle' => $s[2],
        ], $i, 'assets/img/slider/' . $s[3]);
    }
}

function demie_seed_portfolio() {
    // [slug, title, project image]
    $projects = [
        ['bright-boho-sunshine', 'Bright Boho Sunshine', '1.jpg'],
        ['golden-hour-sessions', 'Golden Hour Sessions', '2.jpg'],
        ['studio-portraits', 'Studio Portraits', '3.jpg'],
        ['weddings-celebrations', 'Weddings & Celebrations', '4.jpg'],
        ['events-gatherings', 'Events & Gatherings', '5.jpg'],
        ['faces-of-blantyre', 'Faces of Blantyre', '6.jpg'],
    ];

    foreach ($projects as $i => $p) {
        demie_seed_post('demie_portfolio', $p[0], $p[1], '', [], $i, 'assets/img/projects/4/' . $p[2]);
    }
}

/* ---------- Page meta (Home Sections + Page Headings) ---------- */

function demie_seed_home_meta() {
    $front_id = demie_seed_page_id('home');
    if (!$front_id) {
        return;
    }

    $meta = [
        '_demie_about_heading'    => 'About Demie Photography',
        '_demie_about_p1'         => 'Demie Photography is a photography studio based in Chilomoni, Blantyre, serving couples, families and brands across Malawi.',
        '_demie_about_p2'         => 'From weddings and portraits to events and drone cinematography, our team captures the moments that matter with care, creativity and a personal touch. Hire Demie Photography for your next event.',
        '_demie_exp_l1'           => 'From First Hello',
        '_demie_exp_l2'           => 'to Final Gallery',
        '_demie_exp_text'         => 'No confusing packages or endless back-and-forth. Tell us about your wedding, portrait session or event, and we handle the rest — planning, shooting and editing — so all you have to do is show up and enjoy your moment.',
        '_demie_exp_badge_number' => '3',
        '_demie_exp_badge_label'  => 'Simple Steps',
        '_demie_step1_title'      => 'Tell Us Your Date',
        '_demie_step1_text'       => 'Send a WhatsApp message or fill in the contact form with your event details.',
        '_demie_step2_title'      => 'We Plan Your Shoot',
        '_demie_step2_text'       => 'We agree on the venue, timing and style, and handle everything on the day.',
        '_demie_step3_title'      => 'Receive Your Gallery',
        '_demie_step3_text'       => 'Carefully edited photos delivered online, ready to share with family and friends.',
        '_demie_h_about_l1'       => 'Demie Photography captures',
        '_demie_h_about_l2'       => 'All of Your',
        '_demie_h_about_l3'       => 'beautiful memories',
        '_demie_h_portfolio_l1'   => 'Demie Photography captures',
        '_demie_h_portfolio_l2'   => 'All of Your',
        '_demie_h_portfolio_l3'   => 'beautiful memories',
        '_demie_h_blog_l1'        => 'Our Photography',
        '_demie_h_blog_l2'        => 'Related Blog',
        '_demie_h_blog_desc'      => 'We are deeply passionate about catching your lovely memories on camera and conveying your love for every moment of life as a whole.',
        '_demie_h_contact_l1'     => 'Get In Touch',
        '_demie_h_contact_desc'   => 'Contact us for a great photography session & beautiful captured moments',
    ];

    // Bundled homepage images: field => theme-relative path.
    $images = [
        '_demie_img_about'    => 'assets/img/more/7.png',
        '_demie_img_exp'      => 'assets/img/more/3.png',
        '_demie_img_exp_bg'   => 'assets/img/background/bg-13.jpg',
        '_demie_img_testi_bg' => 'assets/img/background/bg-16.jpg',
        '_demie_img_insta_1'  => 'assets/img/instagram/1.jpg',
        '_demie_img_insta_2'  => 'assets/img/instagram/2.jpg',
        '_demie_img_insta_3'  => 'assets/img/instagram/3.jpg',
        '_demie_img_insta_4'  => 'assets/img/instagram/4.jpg',
        '_demie_img_insta_5'  => 'assets/img/instagram/5.jpg',
    ];

    // Legacy cleanup: earlier seeds created homepage label fields ("02 //" etc.)
    // that have been removed from the metabox — delete any leftovers.
    foreach (['_demie_h_about_sub', '_demie_h_portfolio_sub', '_demie_h_blog_sub'] as $legacy_key) {
        delete_post_meta($front_id, $legacy_key);
    }

    // One-time migration to the How-It-Works section: the "20 Amazing
    // Photographers" copy and the fake counter stats ("50+ Cameras" etc.)
    // are retired. Seeded defaults are deleted so the new copy below takes
    // their place; anything the owner customized is left untouched. The
    // retired counter/badge meta is always deleted.
    foreach ([
        '_demie_exp_l1'   => '20 Amazing',
        '_demie_exp_l2'   => 'Photographers',
        '_demie_exp_text' => 'The talent at Demie Photography runs wide and deep. From weddings to events and drone work, our team members are some of the finest photographers in the industry, capturing beautiful memories across Malawi.',
    ] as $legacy_key => $legacy_value) {
        if (get_post_meta($front_id, $legacy_key, true) === $legacy_value) {
            delete_post_meta($front_id, $legacy_key);
        }
    }
    foreach ([
        '_demie_counter1_number', '_demie_counter1_suffix', '_demie_counter1_label',
        '_demie_counter2_number', '_demie_counter2_suffix', '_demie_counter2_label',
        '_demie_counter3_number', '_demie_counter3_suffix', '_demie_counter3_label',
        '_demie_exp_years',
    ] as $legacy_key) {
        delete_post_meta($front_id, $legacy_key);
    }

    foreach ($meta as $key => $value) {
        if (metadata_exists('post', $front_id, $key) === false) {
            update_post_meta($front_id, $key, $value);
        }
    }

    foreach ($images as $key => $path) {
        if ((int) get_post_meta($front_id, $key, true) === 0) {
            $attachment_id = demie_seed_attachment($path, ucfirst(pathinfo($path, PATHINFO_FILENAME)));
            if ($attachment_id) {
                update_post_meta($front_id, $key, (string) $attachment_id);
            }
        }
    }
}

function demie_seed_page_meta() {
    $pages = [
        'about-us' => [
            '_demie_h_sub' => 'About Us',
            '_demie_h_l1'  => 'About',
            '_demie_h_l2'  => 'Demie Photography',
            '_demie_h_l3'  => '',
        ],
        'services' => [
            '_demie_h_sub' => 'Our Services',
            '_demie_h_l1'  => 'Demie Photography offers',
            '_demie_h_l2'  => 'All of the',
            '_demie_h_l3'  => 'services you need',
        ],
        'gallery'  => [
            '_demie_h_sub' => 'Our Portfolio',
            '_demie_h_l1'  => 'Demie Photography captures',
            '_demie_h_l2'  => 'All of Your',
            '_demie_h_l3'  => 'beautiful memories',
        ],
        'contact'  => [
            '_demie_h_sub' => '',
            '_demie_h_l1'  => 'Get In Touch',
            '_demie_h_l2'  => '',
            '_demie_h_l3'  => '',
            '_demie_h_desc' => 'Contact us for a great photography session & beautiful captured moments',
        ],
    ];

    foreach ($pages as $slug => $meta) {
        $page_id = demie_seed_page_id($slug);
        if (!$page_id) {
            continue;
        }
        foreach ($meta as $key => $value) {
            if (metadata_exists('post', $page_id, $key) === false) {
                update_post_meta($page_id, $key, $value);
            }
        }

        // Legacy cleanup: replace seeded labels that still carry a numeric
        // "NN //" prefix, unless the owner already changed the label.
        $legacy = [
            '01 // About Us'    => 'About Us',
            '01//'              => 'Our Services',
            '01// Our Portfolio' => 'Our Portfolio',
        ];
        $current = get_post_meta($page_id, '_demie_h_sub', true);
        if (isset($legacy[$current])) {
            update_post_meta($page_id, '_demie_h_sub', $legacy[$current]);
        }
    }
}
