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

    foreach ($fields as $field) {
        list($key, $label, $type) = $field;
        $args    = isset($field[3]) ? (array) $field[3] : [];
        $value   = get_post_meta($post->ID, $key, true);
        $id      = esc_attr(ltrim($key, '_'));
        $name    = esc_attr($key);
        $desc    = isset($args['desc']) ? '<p class="description">' . esc_html($args['desc']) . '</p>' : '';

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

        // Experience / stats section
        ['_demie_exp_heading', __('Experience heading', 'demie-photography'), 'text', ['desc' => __('e.g. "20 Amazing Photographers"', 'demie-photography')]],
        ['_demie_exp_text', __('Experience paragraph', 'demie-photography'), 'textarea', ['rows' => 3]],
        ['_demie_exp_years', __('Years experience (number)', 'demie-photography'), 'number', ['min' => 0, 'max' => 99]],

        // Counters
        ['_demie_counter1_number', __('Counter 1 — number', 'demie-photography'), 'number', ['min' => 0, 'max' => 99999]],
        ['_demie_counter1_suffix', __('Counter 1 — suffix (e.g. +)', 'demie-photography'), 'text'],
        ['_demie_counter1_label', __('Counter 1 — label', 'demie-photography'), 'text'],
        ['_demie_counter2_number', __('Counter 2 — number', 'demie-photography'), 'number', ['min' => 0, 'max' => 99999]],
        ['_demie_counter2_suffix', __('Counter 2 — suffix', 'demie-photography'), 'text'],
        ['_demie_counter2_label', __('Counter 2 — label', 'demie-photography'), 'text'],
        ['_demie_counter3_number', __('Counter 3 — number', 'demie-photography'), 'number', ['min' => 0, 'max' => 99999]],
        ['_demie_counter3_suffix', __('Counter 3 — suffix', 'demie-photography'), 'text'],
        ['_demie_counter3_label', __('Counter 3 — label', 'demie-photography'), 'text'],

        // Section headings
        ['_demie_h_about_sub', __('Section label 02 (e.g. "About Agency")', 'demie-photography'), 'text'],
        ['_demie_h_about_l1', __('About section H1, part 1', 'demie-photography'), 'text'],
        ['_demie_h_about_l2', __('About section H1, highlighted part', 'demie-photography'), 'text'],
        ['_demie_h_portfolio_sub', __('Section label 03 (e.g. "Our Portfolio")', 'demie-photography'), 'text'],
        ['_demie_h_portfolio_l1', __('Portfolio H1, part 1', 'demie-photography'), 'text'],
        ['_demie_h_portfolio_l2', __('Portfolio H1, highlighted part', 'demie-photography'), 'text'],
        ['_demie_h_blog_sub', __('Section label 04 (e.g. "Latest News")', 'demie-photography'), 'text'],
        ['_demie_h_blog_l1', __('Blog section H1, part 1', 'demie-photography'), 'text'],
        ['_demie_h_blog_l2', __('Blog section H1, highlighted part', 'demie-photography'), 'text'],
        ['_demie_h_blog_desc', __('Blog section description', 'demie-photography'), 'textarea', ['rows' => 2]],
        ['_demie_h_contact_l1', __('Contact section H1', 'demie-photography'), 'text'],
        ['_demie_h_contact_desc', __('Contact section description', 'demie-photography'), 'textarea', ['rows' => 2]],
    ];
}

function demie_page_heading_fields() {
    return [
        ['_demie_h_sub', __('Section label (e.g. "01 // About Us")', 'demie-photography'), 'text'],
        ['_demie_h_l1', __('Page H1, part 1', 'demie-photography'), 'text'],
        ['_demie_h_l2', __('Page H1, highlighted part', 'demie-photography'), 'text'],
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

    // Page Headings: on the brochure pages.
    add_meta_box('demie-page-headings', __('Demie Page Headings', 'demie-photography'), function ($post) {
        demie_render_fields($post, demie_page_heading_fields());
    }, 'page', 'side', 'default', ['demie_only_brochure' => true]);
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

add_filter('postbox_classes_page_demie-page-headings', function ($classes) {
    $post = get_post();
    $brochure = ['home', 'about-us', 'services', 'gallery', 'blog', 'contact'];
    if (!$post || !in_array($post->post_name, $brochure, true)) {
        $classes[] = 'hidden';
    }
    return $classes;
});

/* ---------- Saving ---------- */

add_action('save_post', function ($post_id, $post, $update) {
    if (demie_is_admin_screen() === false && !wp_is_post_revision($post_id)) {
        // still allow saves triggered programmatically
    }
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
    }
}, 10, 3);
