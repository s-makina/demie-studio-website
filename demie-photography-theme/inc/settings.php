<?php
/**
 * Demie Settings admin page — Studio Details (phone, email, location, socials).
 *
 * Decision: docs/adr/0001-cpts-and-metaboxes-for-editable-content.md
 * Brand facts come from CONTEXT.md and are used as seed defaults.
 *
 * WhatsApp links stay derived from the phone number (no drift between them),
 * and the map link is derived from the location string.
 */

if (!defined('ABSPATH')) exit;

/* ---------- Defaults (CONTEXT.md brand facts) ---------- */

function demie_settings_defaults() {
    return [
        'phone'     => '+265 884 44 48 02',
        'email'     => 'demiestudios@gmail.com',
        'location'  => 'Chilomoni, Blantyre, Malawi',
        'facebook'  => 'https://www.facebook.com/people/Demie-photography/100063646432000/',
        'instagram' => '',
        'linkedin'  => '',
        'youtube'   => '',
        'behance'   => '',
    ];
}

/**
 * Read a Studio Details value with seed-default fallback.
 */
function demie_get($key) {
    $opts = get_option('demie_settings', []);
    if (is_array($opts) && isset($opts[$key]) && $opts[$key] !== '') {
        return $opts[$key];
    }
    $defaults = demie_settings_defaults();
    return isset($defaults[$key]) ? $defaults[$key] : '';
}

/* ---------- Brand helpers (used across templates) ---------- */

function demie_phone()        { return demie_get('phone'); }
function demie_email()        { return demie_get('email'); }
function demie_location()     { return demie_get('location'); }
function demie_phone_url()    { return 'tel:' . preg_replace('/[^0-9+]/', '', demie_get('phone')); }
function demie_whatsapp_url() { return 'https://wa.me/' . preg_replace('/[^0-9]/', '', demie_get('phone')); }
function demie_maps_url()     { return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(demie_get('location')); }
function demie_social_url($network) { return demie_get($network); }

/* ---------- Admin page ---------- */

add_action('admin_menu', function () {
    add_options_page(
        __('Demie Settings', 'demie-photography'),
        __('Demie Settings', 'demie-photography'),
        'manage_options',
        'demie-settings',
        'demie_render_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('demie_settings_group', 'demie_settings', [
        'type'              => 'array',
        'sanitize_callback' => 'demie_sanitize_settings',
    ]);
});

function demie_sanitize_settings($input) {
    $out = [];
    $input = is_array($input) ? $input : [];

    $out['phone']     = sanitize_text_field(isset($input['phone']) ? $input['phone'] : '');
    $out['email']     = sanitize_email(isset($input['email']) ? $input['email'] : '');
    $out['location']  = sanitize_text_field(isset($input['location']) ? $input['location'] : '');

    foreach (['facebook', 'instagram', 'linkedin', 'youtube', 'behance'] as $network) {
        $url = isset($input[$network]) ? esc_url_raw($input[$network]) : '';
        $out[$network] = $url;
    }

    return $out;
}

function demie_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $opts = wp_parse_args(get_option('demie_settings', []), demie_settings_defaults());
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Demie Settings', 'demie-photography'); ?></h1>
        <p><?php esc_html_e('Studio contact details used across the whole site. Leave a social network empty to hide it.', 'demie-photography'); ?></p>

        <form method="post" action="options.php">
            <?php settings_fields('demie_settings_group'); ?>

            <h2 class="title"><?php esc_html_e('Studio Details', 'demie-photography'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="demie-phone"><?php esc_html_e('Phone', 'demie-photography'); ?></label></th>
                    <td>
                        <input type="text" id="demie-phone" name="demie_settings[phone]" value="<?php echo esc_attr($opts['phone']); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('International format. Also used for the WhatsApp (wa.me) and call (tel:) links.', 'demie-photography'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="demie-email"><?php esc_html_e('Email', 'demie-photography'); ?></label></th>
                    <td>
                        <input type="email" id="demie-email" name="demie_settings[email]" value="<?php echo esc_attr($opts['email']); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Also receives the contact form submissions.', 'demie-photography'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="demie-location"><?php esc_html_e('Studio location', 'demie-photography'); ?></label></th>
                    <td>
                        <input type="text" id="demie-location" name="demie_settings[location]" value="<?php echo esc_attr($opts['location']); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Also builds the "View Map" link.', 'demie-photography'); ?></p>
                    </td>
                </tr>
            </table>

            <h2 class="title"><?php esc_html_e('Social Links', 'demie-photography'); ?></h2>
            <table class="form-table" role="presentation">
                <?php
                foreach (['facebook' => __('Facebook', 'demie-photography'), 'instagram' => __('Instagram', 'demie-photography'), 'linkedin' => __('LinkedIn', 'demie-photography'), 'youtube' => __('YouTube', 'demie-photography'), 'behance' => __('Behance', 'demie-photography')] as $network => $label) :
                    ?>
                    <tr>
                        <th scope="row"><label for="demie-<?php echo esc_attr($network); ?>"><?php echo esc_html($label); ?></label></th>
                        <td>
                            <input type="url" id="demie-<?php echo esc_attr($network); ?>" name="demie_settings[<?php echo esc_attr($network); ?>]" value="<?php echo esc_attr($opts[$network]); ?>" class="regular-text code" placeholder="https://…">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
