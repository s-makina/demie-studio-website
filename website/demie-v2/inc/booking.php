<?php
/**
 * Slotfy booking widget.
 *
 * The widget is a single script tag that mounts an iframe pointing at
 * /embed/{tenantSlug}/booking. Tenant and origin are configured in
 * Settings → Demie Settings → Booking; the return URL is derived from the
 * Booking page so it is correct on localhost and production.
 *
 * Reference: slotfy/docs/integration/embed-widget.md
 */

if (!defined('ABSPATH')) exit;

/**
 * Booking settings with defaults for existing installs.
 *
 * Unlike demie_get(), a deliberately saved empty value is preserved so the
 * owner can disable the widget by clearing the tenant or base URL.
 */
function demie_slotfy_settings() {
    $defaults = [
        'slotfy_enabled'  => '1',
        'slotfy_tenant'   => 'demie-photography',
        'slotfy_base_url' => 'https://slotfy.app',
    ];

    $opts = get_option('demie_settings', []);
    $opts = is_array($opts) ? $opts : [];

    $out = [];
    foreach ($defaults as $key => $default) {
        $out[$key] = array_key_exists($key, $opts) ? (string) $opts[$key] : $default;
    }
    return $out;
}

/**
 * Normalise a Slotfy origin.
 *
 * Accepts absolute http(s) URLs (including http://localhost:3000) and rejects
 * anything else, so a typo cannot become the widget src origin.
 */
function demie_slotfy_normalize_url($raw) {
    $raw = trim((string) $raw);
    if ('' === $raw) {
        return '';
    }

    $parts = wp_parse_url($raw);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }
    if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
        return '';
    }
    // Never allow embedded credentials in a public widget origin.
    if (!empty($parts['user']) || !empty($parts['pass'])) {
        return '';
    }

    $url = esc_url_raw($raw, ['http', 'https']);
    return $url ? untrailingslashit($url) : '';
}

/**
 * Valid Slotfy origin, or '' when the configured value is not http(s).
 */
function demie_slotfy_base_url() {
    $settings = demie_slotfy_settings();
    return demie_slotfy_normalize_url($settings['slotfy_base_url']);
}

/**
 * Tenant slug, or '' when unset.
 */
function demie_slotfy_tenant() {
    $settings = demie_slotfy_settings();
    return sanitize_title($settings['slotfy_tenant']);
}

/**
 * Whether the booking page can render a working widget.
 */
function demie_slotfy_enabled() {
    $settings = demie_slotfy_settings();
    return '1' === $settings['slotfy_enabled']
        && '' !== demie_slotfy_tenant()
        && '' !== demie_slotfy_base_url();
}

/**
 * Public permalink of the Booking page.
 */
function demie_slotfy_booking_url() {
    $page = get_page_by_path('booking');
    return $page ? get_permalink($page) : home_url('/booking/');
}

/**
 * Where the payment gateway sends the customer after checkout.
 */
function demie_slotfy_return_url() {
    return add_query_arg('payment', 'return', demie_slotfy_booking_url());
}

/**
 * Print the widget container and script tag.
 *
 * @param array $args Optional overrides:
 *                    'id'     container id (default slotfy-booking)
 *                    'height' initial iframe height (default 650px)
 * @return bool True when the widget was rendered.
 */
function demie_render_slotfy_widget($args = []) {
    if (!demie_slotfy_enabled()) {
        return false;
    }

    $args = wp_parse_args($args, [
        'id'     => 'slotfy-booking',
        'height' => '650px',
    ]);

    $tenant   = demie_slotfy_tenant();
    $base_url = demie_slotfy_base_url();
    $return   = demie_slotfy_return_url();

    $attributes = [
        'data-tenant-slug'   => $tenant,
        'data-target'        => '#' . $args['id'],
        'data-base-url'      => $base_url,
        'data-width'         => '100%',
        'data-height'        => $args['height'],
        'data-auto-resize'   => 'true',
        'data-return-url'    => $return,
        'data-theme'         => 'dark',
        'data-bg'            => '#151515',
        'data-surface-1'     => '#1E1C1A',
        'data-surface-2'     => '#252220',
        'data-surface-3'     => '#2E2A26',
        'data-text-primary'  => '#FDFBF7',
        'data-text-secondary' => '#B9B2A7',
        'data-border'        => 'rgba(255,255,255,0.08)',
        'data-border-strong' => 'rgba(255,255,255,0.14)',
        'data-primary-color' => '#8A6A3C',
        'data-primary-dark'  => '#6F542E',
        'data-primary-light' => 'rgba(197,168,128,0.14)',
        'data-accent'        => '#C5A880',
        'data-accent-2'      => '#D9C8B4',
        'data-radius'        => '12px',
        'data-font'          => '"Plus Jakarta Sans", sans-serif',
    ];

    $attr_html = '';
    foreach ($attributes as $name => $value) {
        $attr_html .= ' ' . $name . '="' . esc_attr($value) . '"';
    }
    ?>
    <div id="<?php echo esc_attr($args['id']); ?>" class="slotfy-booking"></div>
    <script src="<?php echo esc_url($base_url . '/widget.js'); ?>"<?php echo $attr_html; ?>></script>
    <?php
    return true;
}
