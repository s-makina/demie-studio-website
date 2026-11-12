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
        'slotfy_api_url'  => '',
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
 * Slotfy public API origin (…/api), or '' when the base URL is invalid.
 */
function demie_slotfy_api_base() {
    $settings = demie_slotfy_settings();

    // Split deployments (frontend and backend on different hosts) set an
    // explicit API origin. Single-origin installs proxy /api from the frontend.
    $api = demie_slotfy_normalize_url($settings['slotfy_api_url']);
    if ('' !== $api) {
        return preg_match('#/api$#i', $api) ? $api : $api . '/api';
    }

    $base = demie_slotfy_base_url();
    return '' === $base ? '' : $base . '/api';
}

/**
 * Decode a public Slotfy endpoint, unwrapping the { data } envelope.
 *
 * Returns null on transport, HTTP or JSON errors so callers can fall back to
 * an empty catalogue instead of showing an error to visitors.
 *
 * @param string $url    Absolute endpoint URL.
 * @param string $tenant Tenant slug sent as x-tenant-slug.
 * @return mixed|null Decoded data, or null on failure.
 */
function demie_slotfy_api_get($url, $tenant) {
    $response = wp_remote_get($url, [
        'timeout'     => 6,
        'redirection' => 2,
        'headers'     => [
            'Accept'        => 'application/json',
            'x-tenant-slug' => $tenant,
        ],
    ]);

    if (is_wp_error($response)) {
        return null;
    }
    if (200 !== (int) wp_remote_retrieve_response_code($response)) {
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($body)) {
        return null;
    }

    // The API wraps success bodies as { "@@wrapped": true, data: …, meta: … }.
    return array_key_exists('data', $body) ? $body['data'] : $body;
}

/**
 * Public Slotfy general settings (currency and formatting), cached briefly.
 *
 * @return array Decoded settings, or [] when the API is unreachable.
 */
function demie_slotfy_general_settings() {
    static $general = null;
    if (null !== $general) {
        return $general;
    }

    $tenant = demie_slotfy_tenant();
    $api    = demie_slotfy_api_base();
    if ('' === $tenant || '' === $api) {
        return $general = [];
    }

    $cache_key = 'demie_slotfy_general_' . md5($tenant . '|' . $api);
    $cached    = get_transient($cache_key);
    if (is_array($cached)) {
        return $general = $cached;
    }

    $data    = demie_slotfy_api_get($api . '/settings/general', $tenant);
    $general = is_array($data) ? $data : [];
    set_transient($cache_key, $general, $general ? 15 * MINUTE_IN_SECONDS : 5 * MINUTE_IN_SECONDS);

    return $general;
}

/**
 * Currency settings in the shape demie_slotfy_format_price() expects.
 *
 * @return array{currency: string, symbol_position: string, decimal_places: int}
 */
function demie_slotfy_currency() {
    $general = demie_slotfy_general_settings();

    return [
        'currency'        => !empty($general['currency'])
            ? sanitize_text_field((string) $general['currency'])
            : '',
        'symbol_position' => (isset($general['symbolPosition']) && 'after' === $general['symbolPosition'])
            ? 'after'
            : 'before',
        'decimal_places'  => isset($general['decimalPlaces'])
            ? max(0, min(4, (int) $general['decimalPlaces']))
            : 2,
    ];
}

/**
 * Human-readable duration, e.g. 480 -> "8 hrs".
 */
function demie_slotfy_format_duration($minutes) {
    $minutes = max(0, (int) $minutes);
    if (0 === $minutes) {
        return '';
    }
    if ($minutes < 60) {
        return sprintf(_n('%s min', '%s mins', $minutes, 'demie-v2'), number_format_i18n($minutes));
    }

    $hours = (int) floor($minutes / 60);
    $rest  = $minutes % 60;
    $label = sprintf(_n('%s hr', '%s hrs', $hours, 'demie-v2'), number_format_i18n($hours));
    if ($rest > 0) {
        $label .= ' ' . sprintf(_n('%s min', '%s mins', $rest, 'demie-v2'), number_format_i18n($rest));
    }

    return $label;
}

/**
 * Normalise one public Slotfy service into what the theme renders.
 *
 * @param mixed $raw One row from GET /services?isPublic=true.
 * @return array|null Service, or null when the row is unusable.
 */
function demie_slotfy_normalize_service($raw) {
    if (!is_array($raw) || empty($raw['name'])) {
        return null;
    }

    $features = [];
    if (!empty($raw['features'])) {
        if (is_string($raw['features'])) {
            $decoded = json_decode($raw['features'], true);
            $features = is_array($decoded) ? $decoded : explode(',', $raw['features']);
        } elseif (is_array($raw['features'])) {
            $features = $raw['features'];
        }
    }

    $clean_features = [];
    foreach ($features as $feature) {
        $feature = sanitize_text_field(trim((string) $feature));
        if ('' !== $feature) {
            $clean_features[] = $feature;
        }
    }

    return [
        'id'          => isset($raw['id']) ? sanitize_text_field((string) $raw['id']) : '',
        'name'        => sanitize_text_field((string) $raw['name']),
        'description' => isset($raw['description'])
            ? html_entity_decode(wp_strip_all_tags((string) $raw['description']), ENT_QUOTES, 'UTF-8')
            : '',
        'price'       => isset($raw['price']) ? (float) $raw['price'] : 0.0,
        'duration'    => isset($raw['duration']) ? max(0, (int) $raw['duration']) : 0,
        'features'    => array_slice($clean_features, 0, 10),
        'category_id' => isset($raw['categoryId']) ? sanitize_text_field((string) $raw['categoryId']) : '',
    ];
}

/**
 * Public service catalogue from Slotfy, grouped by visible category.
 *
 * Mirrors the booking widget: services come from /services?isPublic=true,
 * grouped under the categories returned by /categories/public. Services with
 * no visible category land under "More services".
 *
 * @return array{money: array, categories: array}
 */
function demie_slotfy_service_catalog() {
    static $catalog = null;
    if (null !== $catalog) {
        return $catalog;
    }

    $tenant = demie_slotfy_tenant();
    $api    = demie_slotfy_api_base();
    $empty  = ['money' => demie_slotfy_currency(), 'categories' => []];

    if ('' === $tenant || '' === $api) {
        return $catalog = $empty;
    }

    $cache_key = 'demie_slotfy_services_' . md5($tenant . '|' . $api);
    $cached    = get_transient($cache_key);
    if (is_array($cached) && isset($cached['categories'])) {
        return $catalog = $cached;
    }

    $raw_services   = demie_slotfy_api_get($api . '/services?isPublic=true', $tenant);
    $raw_categories = demie_slotfy_api_get($api . '/categories/public', $tenant);

    $services = [];
    foreach (is_array($raw_services) ? $raw_services : [] as $raw) {
        $service = demie_slotfy_normalize_service($raw);
        if (null !== $service) {
            $services[] = $service;
        }
    }

    $categories = [];
    foreach (is_array($raw_categories) ? $raw_categories : [] as $raw_category) {
        if (!is_array($raw_category) || empty($raw_category['id']) || empty($raw_category['name'])) {
            continue;
        }
        $category_id = sanitize_text_field((string) $raw_category['id']);
        $members     = [];
        foreach ($services as $service) {
            if ($service['category_id'] === $category_id) {
                $members[] = $service;
            }
        }
        if (!$members) {
            continue;
        }
        $categories[] = [
            'id'          => $category_id,
            'name'        => sanitize_text_field((string) $raw_category['name']),
            'description' => !empty($raw_category['description'])
                ? html_entity_decode(wp_strip_all_tags((string) $raw_category['description']), ENT_QUOTES, 'UTF-8')
                : '',
            'services'    => $members,
        ];
    }

    // Services outside a visible category, mirroring the booking widget.
    $loose = [];
    foreach ($services as $service) {
        $matched = false;
        foreach ($categories as $category) {
            if ($service['category_id'] === $category['id']) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            $loose[] = $service;
        }
    }
    if ($loose) {
        $categories[] = [
            'id'          => 'more',
            'name'        => __('More services', 'demie-v2'),
            'description' => '',
            'services'    => $loose,
        ];
    }

    $catalog = [
        'money'      => demie_slotfy_currency(),
        'categories' => $categories,
    ];

    $ttl       = max(0, (int) apply_filters('demie_slotfy_services_cache_ttl', 15 * MINUTE_IN_SECONDS));
    $empty_ttl = max(0, (int) apply_filters('demie_slotfy_services_empty_cache_ttl', 5 * MINUTE_IN_SECONDS));
    set_transient($cache_key, $catalog, $categories ? $ttl : $empty_ttl);

    return $catalog;
}

/**
 * Normalise one public Slotfy package into what the theme renders.
 *
 * @param mixed $raw One row from GET /packages/public.
 * @return array|null Package, or null when the row is unusable.
 */
function demie_slotfy_normalize_package($raw) {
    if (!is_array($raw) || empty($raw['name'])) {
        return null;
    }

    $inclusions = [];
    $sessions   = 0;
    if (!empty($raw['packageServices']) && is_array($raw['packageServices'])) {
        foreach ($raw['packageServices'] as $line) {
            $name = isset($line['service']['name']) ? trim((string) $line['service']['name']) : '';
            $quantity = isset($line['quantity']) ? (int) $line['quantity'] : 0;
            if ('' === $name || $quantity < 1) {
                continue;
            }
            $sessions += $quantity;
            $inclusions[] = [
                'name'     => sanitize_text_field($name),
                'quantity' => $quantity,
            ];
        }
    }

    $validity = '';
    if (!empty($raw['durationCount']) && !empty($raw['durationType'])) {
        $count = max(0, (int) $raw['durationCount']);
        $unit  = strtolower((string) $raw['durationType']);
        $label = '';
        if ('day' === $unit) {
            $label = _n('day', 'days', $count, 'demie-v2');
        } elseif ('week' === $unit) {
            $label = _n('week', 'weeks', $count, 'demie-v2');
        } elseif ('month' === $unit) {
            $label = _n('month', 'months', $count, 'demie-v2');
        }
        if ('' !== $label) {
            $validity = sprintf(
                /* translators: 1: number of days/weeks/months, 2: unit label */
                __('Valid for %1$s %2$s', 'demie-v2'),
                number_format_i18n($count),
                $label
            );
        }
    }

    return [
        'id'          => isset($raw['id']) ? sanitize_text_field((string) $raw['id']) : '',
        'name'        => sanitize_text_field((string) $raw['name']),
        'description' => isset($raw['description'])
            ? html_entity_decode(wp_strip_all_tags((string) $raw['description']), ENT_QUOTES, 'UTF-8')
            : '',
        'price'       => isset($raw['price']) ? (float) $raw['price'] : 0.0,
        'sessions'    => $sessions,
        'validity'    => $validity,
        'inclusions'  => $inclusions,
    ];
}

/**
 * Public package catalogue from Slotfy, cached in a transient.
 *
 * Returns packages plus the tenant's currency settings so prices render the
 * same way they do inside the booking widget. A failed request caches an
 * empty list for a short time, so the site neither hammers Slotfy nor leaves
 * a broken section on the page.
 *
 * @return array{packages: array, currency: string, symbol_position: string, decimal_places: int}
 */
function demie_slotfy_package_catalog() {
    static $catalog = null;
    if (null !== $catalog) {
        return $catalog;
    }

    $tenant = demie_slotfy_tenant();
    $api    = demie_slotfy_api_base();
    $empty  = [
        'packages'        => [],
        'currency'        => '',
        'symbol_position' => 'before',
        'decimal_places'  => 2,
    ];

    if ('' === $tenant || '' === $api) {
        return $catalog = $empty;
    }

    $cache_key = 'demie_slotfy_packages_' . md5($tenant . '|' . $api);
    $cached    = get_transient($cache_key);
    if (is_array($cached) && isset($cached['packages'])) {
        return $catalog = $cached;
    }

    $raw_list = demie_slotfy_api_get($api . '/packages/public', $tenant);

    $packages = [];
    foreach (is_array($raw_list) ? $raw_list : [] as $raw) {
        $package = demie_slotfy_normalize_package($raw);
        if (null !== $package) {
            $packages[] = $package;
        }
    }

    $catalog = array_merge(['packages' => $packages], demie_slotfy_currency());

    $ttl       = max(0, (int) apply_filters('demie_slotfy_packages_cache_ttl', 15 * MINUTE_IN_SECONDS));
    $empty_ttl = max(0, (int) apply_filters('demie_slotfy_packages_empty_cache_ttl', 5 * MINUTE_IN_SECONDS));
    set_transient($cache_key, $catalog, $packages ? $ttl : $empty_ttl);

    return $catalog;
}

/**
 * Format a package price with the tenant's Slotfy currency settings.
 *
 * @param float      $amount  Price in major units, as Slotfy stores it.
 * @param array|null $catalog Catalogue from demie_slotfy_package_catalog().
 */
function demie_slotfy_format_price($amount, $catalog = null) {
    $catalog  = null === $catalog ? demie_slotfy_package_catalog() : $catalog;
    $decimals = isset($catalog['decimal_places']) ? max(0, min(4, (int) $catalog['decimal_places'])) : 2;
    $number   = number_format_i18n((float) $amount, $decimals);
    $currency = isset($catalog['currency']) ? (string) $catalog['currency'] : '';

    if ('' === $currency) {
        return $number;
    }

    $position = isset($catalog['symbol_position']) ? $catalog['symbol_position'] : 'before';

    return 'after' === $position
        ? $number . ' ' . $currency
        : $currency . ' ' . $number;
}

/**
 * Short "3 sessions · Valid for 2 months" line for a package card.
 */
function demie_slotfy_package_meta($package) {
    $parts = [];

    if (!empty($package['sessions'])) {
        $count   = (int) $package['sessions'];
        $parts[] = sprintf(
            _n('%s session', '%s sessions', $count, 'demie-v2'),
            number_format_i18n($count)
        );
    }
    if (!empty($package['validity'])) {
        $parts[] = $package['validity'];
    }

    return implode(' · ', $parts);
}

/**
 * Print the widget container and script tag.
 *
 * @param array $args Optional overrides:
 *                    'id'      container id (default slotfy-booking)
 *                    'height'  initial iframe height (default 650px)
 *                    'package' package id to open straight away (default '')
 * @return bool True when the widget was rendered.
 */
function demie_render_slotfy_widget($args = []) {
    if (!demie_slotfy_enabled()) {
        return false;
    }

    $args = wp_parse_args($args, [
        'id'      => 'slotfy-booking',
        'height'  => '650px',
        'package' => '',
        'service' => '',
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

    if ('' !== $args['service']) {
        $attributes['data-service-id'] = $args['service'];
    }

    if ('' !== $args['package']) {
        $attributes['data-package-id'] = $args['package'];
    }

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
