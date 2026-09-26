<?php

if (!defined('ABSPATH')) exit;

/**
 * AJAX enquiry form for Demie Photography.
 * Posts to admin-ajax.php with action "demie_contact", nonce "demie-contact".
 */
function demie_contact_ajax() {
    check_ajax_referer('demie-contact', 'nonce');

    $name    = isset($_POST['name'])    ? sanitize_text_field(wp_unslash($_POST['name']))    : '';
    $email   = isset($_POST['email'])   ? sanitize_email(wp_unslash($_POST['email']))        : '';
    $phone   = isset($_POST['phone'])   ? sanitize_text_field(wp_unslash($_POST['phone']))   : '';
    $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

    $errors = [];

    if ('' === $name)  $errors['name'] = __('Please tell us your name.', 'demie-photography');
    if (!is_email($email)) $errors['email'] = __('Please enter a valid email address.', 'demie-photography');
    if ('' === $message) $errors['message'] = __('Please write a short message.', 'demie-photography');

    if ($errors) {
        wp_send_json_error(['errors' => $errors], 400);
    }

    $to = demie_email();

    $subject_line = $subject ? $subject : __('Website enquiry', 'demie-photography');
    $subject_line = sprintf('[%s] %s', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES), $subject_line);

    $lines = [
        sprintf(__('Name: %s', 'demie-photography'), $name),
        sprintf(__('Email: %s', 'demie-photography'), $email),
    ];
    if ($phone) {
        $lines[] = sprintf(__('Phone: %s', 'demie-photography'), $phone);
    }
    $lines[] = '';
    $lines[] = $message;
    $lines[] = '';
    $lines[] = sprintf(__('— Sent from %s', 'demie-photography'), home_url('/'));

    $headers = [
        'Reply-To: ' . $name . ' <' . $email . '>',
    ];

    $sent = wp_mail($to, $subject_line, implode("\r\n", $lines), $headers);

    if ($sent) {
        wp_send_json_success([
            'message' => __('Thank you! Your message has been sent. We will get back to you soon.', 'demie-photography'),
        ]);
    }

    wp_send_json_error([
        'message' => __('Sorry, the message could not be sent. Please email us directly at ', 'demie-photography') . demie_email(),
    ], 500);
}
add_action('wp_ajax_demie_contact', 'demie_contact_ajax');
add_action('wp_ajax_nopriv_demie_contact', 'demie_contact_ajax');

/**
 * Render the shared enquiry form (used on the homepage and the Contact page).
 * Submits via AJAX to the demie_contact handler above.
 */
function demie_render_contact_form() {
    ?>
    <form class="wptb-form demie-contact-form" method="post">
        <div class="wptb-form--inner">
            <div class="row">
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="form-group">
                        <input type="text" name="name" class="form-control" placeholder="<?php esc_attr_e('Name*', 'demie-photography'); ?>" required>
                    </div>
                </div>

                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="form-group">
                        <input type="email" name="email" class="form-control" placeholder="<?php esc_attr_e('E-mail*', 'demie-photography'); ?>" required>
                    </div>
                </div>

                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="form-group">
                        <input type="tel" name="phone" class="form-control" placeholder="<?php esc_attr_e('Phone', 'demie-photography'); ?>">
                    </div>
                </div>

                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="form-group">
                        <input type="text" name="subject" class="form-control" placeholder="<?php esc_attr_e('Subject', 'demie-photography'); ?>">
                    </div>
                </div>

                <div class="col-md-12 col-lg-12 mb-4">
                    <div class="form-group">
                        <textarea name="message" class="form-control" placeholder="<?php esc_attr_e('Text', 'demie-photography'); ?>" required></textarea>
                    </div>
                </div>

                <div class="col-md-12 col-lg-12">
                    <div class="wptb-item--button text-center">
                        <button class="btn white-opacity creative text-uppercase" type="submit">
                            <span class="btn-wrap">
                                <span class="text-first"><?php esc_html_e('Send Mail', 'demie-photography'); ?></span>
                            </span>
                        </button>
                    </div>
                    <div class="demie-form-feedback" role="status" aria-live="polite" hidden></div>
                </div>
            </div>
        </div>
    </form>
    <?php
}
