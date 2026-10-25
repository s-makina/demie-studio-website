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
        'slotfy_enabled'  => '1',
        'slotfy_tenant'   => 'demie-photography',
        'slotfy_base_url' => 'https://slotfy.app',
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

/**
 * Social networks configured in Demie Settings, in display order.
 * Format: [network => [short label, bootstrap-icon class]].
 * Networks with an empty URL are hidden.
 */
function demie_social_networks() {
    $all = [
        'facebook'  => ['FB', 'bi-facebook'],
        'instagram' => ['IG', 'bi-instagram'],
        'linkedin'  => ['LI', 'bi-linkedin'],
        'youtube'   => ['YT', 'bi-youtube'],
        'behance'   => ['BE', 'bi-behance'],
    ];
    $out = [];
    foreach ($all as $network => $meta) {
        $url = demie_get($network);
        if ($url !== '') {
            $out[$network] = [
                'label' => $meta[0],
                'icon'  => $meta[1],
                'url'   => $url,
            ];
        }
    }
    return $out;
}

/**
 * Render a social-box <ul> (short labels) or icon list.
 * $style: 'labels' (FB / IG / …) or 'icons' (bootstrap icons).
 */
function demie_render_social_box($style = 'labels') {
    $networks = demie_social_networks();
    if (!$networks) {
        return;
    }
    echo '<ul>';
    foreach ($networks as $network) {
        if ('icons' === $style) {
            echo '<li><a href="' . esc_url($network['url']) . '" target="_blank" rel="noopener" class="' . esc_attr($network['icon']) . '"></a></li>';
        } else {
            echo '<li><a href="' . esc_url($network['url']) . '" target="_blank" rel="noopener">' . esc_html($network['label']) . '</a></li>';
        }
    }
    echo '</ul>';
}

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

    // Booking (Slotfy). An empty tenant or base URL disables the widget; the
    // enable toggle is the owner-facing switch for normal use.
    $out['slotfy_enabled'] = empty($input['slotfy_enabled']) ? '0' : '1';
    $out['slotfy_tenant']  = sanitize_title(isset($input['slotfy_tenant']) ? $input['slotfy_tenant'] : '');
    $out['slotfy_base_url'] = demie_slotfy_normalize_url(isset($input['slotfy_base_url']) ? $input['slotfy_base_url'] : '');

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

            <h2 class="title"><?php esc_html_e('Booking (Slotfy)', 'demie-v2'); ?></h2>
            <p class="description"><?php esc_html_e('Online booking is embedded on the Booking page from the Slotfy tenant below.', 'demie-v2'); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Online booking', 'demie-v2'); ?></th>
                    <td>
                        <input type="hidden" name="demie_settings[slotfy_enabled]" value="0">
                        <label for="demie-slotfy-enabled">
                            <input type="checkbox" id="demie-slotfy-enabled" name="demie_settings[slotfy_enabled]" value="1" <?php checked($opts['slotfy_enabled'], '1'); ?>>
                            <?php esc_html_e('Show the Slotfy booking widget on the Booking page', 'demie-v2'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="demie-slotfy-tenant"><?php esc_html_e('Tenant slug', 'demie-v2'); ?></label></th>
                    <td>
                        <input type="text" id="demie-slotfy-tenant" name="demie_settings[slotfy_tenant]" value="<?php echo esc_attr($opts['slotfy_tenant']); ?>" class="regular-text code">
                        <p class="description">
                            <?php
                            if ('' !== $opts['slotfy_tenant']) {
                                printf(
                                    /* translators: %s: configured Slotfy tenant slug, wrapped in <code> */
                                    esc_html__('Services and availability come from the tenant at /embed/%s/booking.', 'demie-v2'),
                                    '<code>' . esc_html($opts['slotfy_tenant']) . '</code>'
                                );
                            } else {
                                esc_html_e('The slug used in /embed/{slug}/booking. Leave empty to disable the widget.', 'demie-v2');
                            }
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="demie-slotfy-base-url"><?php esc_html_e('Slotfy base URL', 'demie-v2'); ?></label></th>
                    <td>
                        <input type="url" id="demie-slotfy-base-url" name="demie_settings[slotfy_base_url]" value="<?php echo esc_attr($opts['slotfy_base_url']); ?>" class="regular-text code" placeholder="https://slotfy.app">
                        <p class="description"><?php esc_html_e('Use https://slotfy.app in production, or http://localhost:3000 when testing against the local Slotfy dev server.', 'demie-v2'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
