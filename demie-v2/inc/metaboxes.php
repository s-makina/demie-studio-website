<?php
/**
 * Metaboxes for the Demie Photography theme.
 *
 * Decision: docs/adr/0001-cpts-and-metaboxes-for-editable-content.md
 *
 * One small field framework renders and saves all metabox groups.
 * All meta keys are prefixed `_demie_` (ADR consequence: stable field names).
 */

if (!defined('ABSPATH')) exit;

/* ---------- Field framework ---------- */

/**
 * Render a set of fields inside a metabox.
 * Field: [key, label, type, args?] where type is text|textarea|number|select|wysiwyg-lite.
 */
function demie_render_fields($post, array $fields) {
    wp_nonce_field('demie_meta_save', 'demie_meta_nonce');

    // Media picker for 'image' fields.
    wp_enqueue_media();
    ?>
    <script>
    (function () {
        var bind = function () {
            document.querySelectorAll('.demie-img-field').forEach(function (wrap) {
                if (wrap.dataset.bound) { return; }
                wrap.dataset.bound = '1';
                var input = wrap.querySelector('input[type=hidden]'),
                    prev  = wrap.querySelector('img'),
                    pick  = wrap.querySelector('.demie-img-pick'),
                    clear = wrap.querySelector('.demie-img-clear');
                pick.addEventListener('click', function (e) {
                    e.preventDefault();
                    var frame = wp.media({ title: 'Choose image', multiple: false, library: { type: 'image' } });
                    frame.on('select', function () {
                        var att = frame.state().get('selection').first().toJSON();
                        input.value = att.id;
                        prev.src = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
                        prev.style.display = '';
                        clear.style.display = '';
                    });
                    frame.open();
                });
                clear.addEventListener('click', function (e) {
                    e.preventDefault();
                    input.value = '';
                    prev.style.display = 'none';
                    clear.style.display = 'none';
                });
            });
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bind);
        } else {
            bind();
        }
    })();
    </script>
    <?php

    foreach ($fields as $field) {
        list($key, $label, $type) = $field;
        $args    = isset($field[3]) ? (array) $field[3] : [];
        $value   = get_post_meta($post->ID, $key, true);
        $id      = esc_attr(ltrim($key, '_'));
        $name    = esc_attr($key);
        $desc    = isset($args['desc']) ? '<p class="description">' . esc_html($args['desc']) . '</p>' : '';

        if ('image' === $type) {
            $img_id  = (int) $value;
            $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'thumbnail') : '';
            echo '<p style="margin:1em 0;"><label class="demie-img-field" style="display:block;">';
            echo '<strong>' . esc_html($label) . '</strong><br>';
            echo '<img src="' . esc_url($img_url) . '" alt="" style="' . ($img_url ? '' : 'display:none;') . 'max-width:120px;height:80px;object-fit:cover;margin:6px 0;border:1px solid #dcdcde;border-radius:4px;">';
            echo '<input type="hidden" name="' . $name . '" value="' . esc_attr($value) . '">';
            echo '<button type="button" class="button demie-img-pick">' . esc_html($img_id ? 'Replace image' : 'Choose image') . '</button> ';
            echo '<button type="button" class="button-link demie-img-clear" style="' . ($img_id ? '' : 'display:none;') . 'color:#b32d2e;">Remove</button>';
            echo $desc . '</p>';
            continue;
        }

        echo '<p style="margin:1em 0;"><label for="' . $id . '"><strong>' . esc_html($label) . '</strong></label><br>';

        switch ($type) {
            case 'textarea':
                printf(
                    '<textarea class="large-text" rows="%d" id="%s" name="%s">%s</textarea>',
                    isset($args['rows']) ? (int) $args['rows'] : 4,
                    $id, $name, esc_textarea($value)
                );
                break;

            case 'number':
                printf(
                    '<input type="number" class="small-text" id="%s" name="%s" value="%s" min="%s" max="%s">',
                    $id, $name, esc_attr($value),
                    isset($args['min']) ? esc_attr($args['min']) : '0',
                    isset($args['max']) ? esc_attr($args['max']) : '9999'
                );
                break;

            case 'select':
                echo '<select id="' . $id . '" name="' . $name . '">';
                foreach ($args['options'] as $opt_val => $opt_label) {
                    printf(
                        '<option value="%s"%s>%s</option>',
                        esc_attr($opt_val),
                        selected((string) $value, (string) $opt_val, false),
                        esc_html($opt_label)
                    );
                }
                echo '</select>';
                break;

            default: // text
                printf(
                    '<input type="text" class="large-text" id="%s" name="%s" value="%s">',
                    $id, $name, esc_attr($value)
                );
        }

        echo $desc . '</p>';
    }
}

/**
 * Save a set of fields with per-type sanitization.
 */
function demie_save_fields($post_id, array $fields) {
    if (!isset($_POST['demie_meta_nonce']) || !wp_verify_nonce($_POST['demie_meta_nonce'], 'demie_meta_save')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    foreach ($fields as $field) {
        list($key, , $type) = $field;
        if (!isset($_POST[$key])) {
            continue;
        }
        $raw = wp_unslash($_POST[$key]);

        switch ($type) {
            case 'number':
                $value = (string) intval($raw);
                break;
            case 'image':
                $value = (string) absint($raw);
                break;
            case 'textarea':
                $value = sanitize_textarea_field($raw);
                break;
            default:
                $value = sanitize_text_field($raw);
        }

        update_post_meta($post_id, $key, $value);
    }
}

/* ---------- Field definitions ---------- */

function demie_service_fields() {
    return [
        ['_demie_short_desc', __('Short blurb (homepage card)', 'demie-photography'), 'textarea', ['rows' => 3, 'desc' => __('Shown on the homepage services cards. The full description on the Services page comes from the content editor.', 'demie-photography')]],
    ];
}

function demie_testimonial_fields() {
    return [
        ['_demie_quote', __('Quote', 'demie-photography'), 'textarea', ['rows' => 4]],
        ['_demie_location', __('Location (e.g. Blantyre)', 'demie-photography'), 'text'],
        ['_demie_rating', __('Stars', 'demie-photography'), 'select', ['options' => [5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1']]],
    ];
}

function demie_slide_fields() {
    return [
        ['_demie_subtitle', __('Slide subtitle (e.g. Weddings)', 'demie-photography'), 'text'],
    ];
}

function demie_home_fields() {
    return [
        // About section
        ['_demie_about_heading', __('About section heading', 'demie-photography'), 'text'],
        ['_demie_about_p1', __('About paragraph 1', 'demie-photography'), 'textarea', ['rows' => 3]],
        ['_demie_about_p2', __('About paragraph 2', 'demie-photography'), 'textarea', ['rows' => 3]],

        // How It Works section (formerly Experience / stats)
        ['_demie_exp_l1', __('How It Works heading, part 1 (e.g. "From First Hello")', 'demie-photography'), 'text'],
        ['_demie_exp_l2', __('How It Works heading, outlined part (e.g. "to Final Gallery")', 'demie-photography'), 'text'],
        ['_demie_exp_text', __('How It Works paragraph', 'demie-photography'), 'textarea', ['rows' => 3]],
        ['_demie_exp_badge_number', __('Steps badge — number', 'demie-photography'), 'text'],
        ['_demie_exp_badge_label', __('Steps badge — label (e.g. "Simple Steps")', 'demie-photography'), 'text'],

        // Steps (replaces counters)
        ['_demie_step1_title', __('Step 1 — title', 'demie-photography'), 'text'],
        ['_demie_step1_text', __('Step 1 — description', 'demie-photography'), 'textarea', ['rows' => 2]],
        ['_demie_step2_title', __('Step 2 — title', 'demie-photography'), 'text'],
        ['_demie_step2_text', __('Step 2 — description', 'demie-photography'), 'textarea', ['rows' => 2]],
        ['_demie_step3_title', __('Step 3 — title', 'demie-photography'), 'text'],
        ['_demie_step3_text', __('Step 3 — description', 'demie-photography'), 'textarea', ['rows' => 2]],

        // Section headings
        ['_demie_h_about_l1', __('About section H1, part 1', 'demie-photography'), 'text'],
        ['_demie_h_about_l2', __('About section H1, highlighted part', 'demie-photography'), 'text'],
        ['_demie_h_about_l3', __('About section H1, part 3 (after the line break)', 'demie-photography'), 'text'],
        ['_demie_h_portfolio_l1', __('Portfolio H1, part 1', 'demie-photography'), 'text'],
        ['_demie_h_portfolio_l2', __('Portfolio H1, highlighted part', 'demie-photography'), 'text'],
        ['_demie_h_portfolio_l3', __('Portfolio H1, part 3 (after the line break)', 'demie-photography'), 'text'],
        ['_demie_h_blog_l1', __('Blog section H1, part 1', 'demie-photography'), 'text'],
        ['_demie_h_blog_l2', __('Blog section H1, highlighted part', 'demie-photography'), 'text'],
        ['_demie_h_blog_desc', __('Blog section description', 'demie-photography'), 'textarea', ['rows' => 2]],
        ['_demie_h_contact_l1', __('Contact section H1', 'demie-photography'), 'text'],
        ['_demie_h_contact_desc', __('Contact section description', 'demie-photography'), 'textarea', ['rows' => 2]],

        // Images
        ['_demie_img_about', __('About section photo', 'demie-photography'), 'image', ['desc' => __('The photo with the "Explore Us" button (default: bundled about image).', 'demie-photography')]],
        ['_demie_img_exp', __('Experience section photo', 'demie-photography'), 'image', ['desc' => __('Small team photo in the experience section (desktop only).', 'demie-photography')]],
        ['_demie_img_exp_bg', __('Experience section background', 'demie-photography'), 'image'],
        ['_demie_img_testi_bg', __('Testimonial section background', 'demie-photography'), 'image'],
        ['_demie_img_insta_1', __('Instagram strip image 1', 'demie-photography'), 'image'],
        ['_demie_img_insta_2', __('Instagram strip image 2', 'demie-photography'), 'image'],
        ['_demie_img_insta_3', __('Instagram strip image 3', 'demie-photography'), 'image'],
        ['_demie_img_insta_4', __('Instagram strip image 4', 'demie-photography'), 'image'],
        ['_demie_img_insta_5', __('Instagram strip image 5', 'demie-photography'), 'image'],
    ];
}

function demie_page_heading_fields() {
    return [
        ['_demie_h_sub', __('Section label (e.g. "Our Services")', 'demie-photography'), 'text', ['desc' => __('Shown above the page heading.', 'demie-photography')]],
        ['_demie_h_l1', __('Page H1, part 1', 'demie-photography'), 'text'],
        ['_demie_h_l2', __('Page H1, highlighted part', 'demie-photography'), 'text'],
        ['_demie_h_l3', __('Page H1, part 3 (after the line break)', 'demie-photography'), 'text'],
        ['_demie_h_desc', __('Section description (Contact page)', 'demie-photography'), 'textarea', ['rows' => 2]],
    ];
}

/* ---------- Registration ---------- */

add_action('add_meta_boxes', function () {
    add_meta_box('demie-service', __('Service Details', 'demie-photography'), function ($post) {
        demie_render_fields($post, demie_service_fields());
    }, 'demie_service', 'normal', 'high');

    add_meta_box('demie-testimonial', __('Testimonial Details', 'demie-photography'), function ($post) {
        demie_render_fields($post, demie_testimonial_fields());
    }, 'demie_testimonial', 'normal', 'high');

    add_meta_box('demie-slide', __('Slide Details', 'demie-photography'), function ($post) {
        demie_render_fields($post, demie_slide_fields());
    }, 'demie_slide', 'normal', 'high');

    // Home Sections: only on the page set as the static front page.
    add_meta_box('demie-home', __('Demie Home Sections', 'demie-photography'), function ($post) {
        demie_render_fields($post, demie_home_fields());
    }, 'page', 'normal', 'high', ['__back_compat_meta_box' => false, 'demie_only_front' => true]);

    // Page Headings: on all pages and posts (brochure pages are seeded; generic
    // pages/posts start empty and hide the heading block until filled).
    add_meta_box('demie-page-headings', __('Demie Page Headings', 'demie-photography'), function ($post) {
        demie_render_fields($post, demie_page_heading_fields());
    }, ['page', 'post'], 'side', 'default');
}, 10, 0);

/**
 * Restrict page metaboxes to the front page / brochure pages.
 */
add_filter('postbox_classes_page_demie-home', function ($classes) {
    $post = get_post();
    if (!$post || (int) get_option('page_on_front') !== $post->ID) {
        $classes[] = 'hidden';
    }
    return $classes;
});

/* ---------- Saving ---------- */

add_action('save_post', function ($post_id, $post, $update) {
    if (!$post || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    switch ($post->post_type) {
        case 'demie_service':
            demie_save_fields($post_id, demie_service_fields());
            break;
        case 'demie_testimonial':
            demie_save_fields($post_id, demie_testimonial_fields());
            break;
        case 'demie_slide':
            demie_save_fields($post_id, demie_slide_fields());
            break;
        case 'page':
            // Both page metaboxes share the nonce; saving both is harmless.
            demie_save_fields($post_id, demie_home_fields());
            demie_save_fields($post_id, demie_page_heading_fields());
            break;
        case 'post':
            demie_save_fields($post_id, demie_page_heading_fields());
            break;
    }
}, 10, 3);
