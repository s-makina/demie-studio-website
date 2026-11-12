<?php
/**
 * Lead capture for the Services page.
 *
 * Three entry points share one pipeline:
 *  - "Check my date"  → Slotfy availability lookup, then a contact step.
 *  - "Send this package to my WhatsApp" → package-specific enquiry.
 *  - "Get the planning guide" → guide request.
 *
 * Leads are stored as demie_lead posts (admin-only), emailed to the studio,
 * and never exposed publicly. No plugin required.
 */

if (!defined('ABSPATH')) exit;

/**
 * Current request URL, used to send non-JS form posts back to this page.
 */
function demie_current_url() {
    $path = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
    return home_url($path);
}

/* ---------- Storage ---------- */

add_action('init', function () {
    register_post_type('demie_lead', [
        'labels' => [
            'name'          => __('Enquiries', 'demie-v2'),
            'singular_name' => __('Enquiry', 'demie-v2'),
            'menu_name'     => __('Enquiries', 'demie-v2'),
            'all_items'     => __('All enquiries', 'demie-v2'),
            'edit_item'     => __('Enquiry details', 'demie-v2'),
            'search_items'  => __('Search enquiries', 'demie-v2'),
            'not_found'     => __('No enquiries yet.', 'demie-v2'),
        ],
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => true,
        'menu_position'   => 26,
        'menu_icon'       => 'dashicons-email-alt',
        'supports'        => ['title'],
        'capabilities'    => ['create_posts' => 'do_not_allow'],
        'map_meta_cap'    => true,
    ]);
});

function demie_lead_statuses() {
    return [
        'new'       => __('New', 'demie-v2'),
        'contacted' => __('Contacted', 'demie-v2'),
        'booked'    => __('Booked', 'demie-v2'),
        'lost'      => __('Lost', 'demie-v2'),
    ];
}

function demie_lead_meta($post_id, $key, $default = '') {
    $value = get_post_meta($post_id, '_demie_lead_' . $key, true);
    return '' !== $value ? $value : $default;
}

/* ---------- Admin list ---------- */

add_filter('manage_demie_lead_posts_columns', function ($columns) {
    $cb = isset($columns['cb']) ? $columns['cb'] : '';
    return [
        'cb'                   => $cb,
        'title'                => __('Name', 'demie-v2'),
        'demie_lead_whatsapp'  => __('WhatsApp', 'demie-v2'),
        'demie_lead_date'      => __('Wedding date', 'demie-v2'),
        'demie_lead_package'   => __('Package', 'demie-v2'),
        'demie_lead_source'    => __('Source', 'demie-v2'),
        'demie_lead_status'    => __('Status', 'demie-v2'),
        'date'                 => __('Received', 'demie-v2'),
    ];
});

add_action('manage_demie_lead_posts_custom_column', function ($column, $post_id) {
    if ('demie_lead_whatsapp' === $column) {
        $number = demie_lead_meta($post_id, 'whatsapp');
        if ('' === $number) {
            echo '&mdash;';
            return;
        }
        $digits = preg_replace('/\D+/', '', $number);
        printf(
            '<a href="%s" target="_blank" rel="noopener">%s</a>',
            esc_url('https://wa.me/' . $digits),
            esc_html($number)
        );
    } elseif ('demie_lead_date' === $column) {
        echo esc_html(demie_lead_meta($post_id, 'date', '—'));
    } elseif ('demie_lead_package' === $column) {
        echo esc_html(demie_lead_meta($post_id, 'package', '—'));
    } elseif ('demie_lead_source' === $column) {
        echo esc_html(demie_lead_meta($post_id, 'source', '—'));
    } elseif ('demie_lead_status' === $column) {
        $statuses = demie_lead_statuses();
        $status   = demie_lead_meta($post_id, 'status', 'new');
        echo esc_html(isset($statuses[$status]) ? $statuses[$status] : $status);
    }
}, 10, 2);

/* ---------- Admin detail box ---------- */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'demie-lead-details',
        __('Enquiry details', 'demie-v2'),
        'demie_render_lead_metabox',
        'demie_lead',
        'normal',
        'high'
    );
});

function demie_render_lead_metabox($post) {
    wp_nonce_field('demie_lead_meta', 'demie_lead_meta_nonce');

    $rows = [
        __('Name', 'demie-v2')           => demie_lead_meta($post->ID, 'name'),
        __('WhatsApp', 'demie-v2')       => demie_lead_meta($post->ID, 'whatsapp'),
        __('Email', 'demie-v2')          => demie_lead_meta($post->ID, 'email'),
        __('Wedding date', 'demie-v2')   => demie_lead_meta($post->ID, 'date'),
        __('Venue', 'demie-v2')          => demie_lead_meta($post->ID, 'venue'),
        __('Package', 'demie-v2')        => demie_lead_meta($post->ID, 'package'),
        __('Source', 'demie-v2')         => demie_lead_meta($post->ID, 'source'),
        __('Intent', 'demie-v2')         => demie_lead_meta($post->ID, 'intent'),
        __('WhatsApp consent', 'demie-v2') => '1' === demie_lead_meta($post->ID, 'consent') ? __('Yes', 'demie-v2') : __('No', 'demie-v2'),
    ];

    echo '<table class="widefat striped" style="max-width:720px"><tbody>';
    foreach ($rows as $label => $value) {
        echo '<tr><th style="width:180px;text-align:left">' . esc_html($label) . '</th><td>';
        echo '' !== $value ? esc_html($value) : '&mdash;';
        echo '</td></tr>';
    }
    echo '</tbody></table>';

    $digits = preg_replace('/\D+/', '', demie_lead_meta($post->ID, 'whatsapp'));
    if ('' !== $digits) {
        echo '<p style="margin-top:14px"><a class="button button-primary" target="_blank" rel="noopener" href="'
            . esc_url('https://wa.me/' . $digits) . '">' . esc_html__('Reply on WhatsApp', 'demie-v2') . '</a></p>';
    }

    $statuses = demie_lead_statuses();
    $current  = demie_lead_meta($post->ID, 'status', 'new');
    echo '<p style="margin-top:16px"><label for="demie-lead-status"><strong>'
        . esc_html__('Status', 'demie-v2') . '</strong></label><br>';
    echo '<select id="demie-lead-status" name="demie_lead_status">';
    foreach ($statuses as $value => $label) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($value),
            selected($current, $value, false),
            esc_html($label)
        );
    }
    echo '</select></p>';
}

add_action('save_post_demie_lead', function ($post_id) {
    if (!isset($_POST['demie_lead_meta_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['demie_lead_meta_nonce'])), 'demie_lead_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (isset($_POST['demie_lead_status'])) {
        $statuses = demie_lead_statuses();
        $status   = sanitize_key(wp_unslash($_POST['demie_lead_status']));
        if (isset($statuses[$status])) {
            update_post_meta($post_id, '_demie_lead_status', $status);
        }
    }
});

/* ---------- Availability lookup ---------- */

/**
 * Available dates for one service in a YYYY-MM month.
 *
 * @return array{ok: bool, dates: string[]} ok=false means "could not check".
 */
function demie_slotfy_available_dates($service_id, $month) {
    if (!function_exists('demie_slotfy_api_get')) {
        return ['ok' => false, 'dates' => []];
    }

    $api    = demie_slotfy_api_base();
    $tenant = demie_slotfy_tenant();
    if ('' === $api || '' === $tenant) {
        return ['ok' => false, 'dates' => []];
    }

    $cache_key = 'demie_avail_' . md5($service_id . '|' . $month . '|' . $api);
    $cached    = get_transient($cache_key);
    if (is_array($cached) && array_key_exists('ok', $cached)) {
        return $cached;
    }

    $url  = add_query_arg(
        ['serviceId' => $service_id, 'month' => $month],
        $api . '/availability/dates'
    );
    $data = demie_slotfy_api_get($url, $tenant);

    if (!is_array($data)) {
        $result = ['ok' => false, 'dates' => []];
        set_transient($cache_key, $result, 2 * MINUTE_IN_SECONDS);
        return $result;
    }

    $dates = [];
    foreach ($data as $date) {
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $dates[] = $date;
        }
    }

    $result = ['ok' => true, 'dates' => array_values(array_unique($dates))];
    set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);

    return $result;
}

add_action('wp_ajax_nopriv_demie_availability', 'demie_handle_availability');
add_action('wp_ajax_demie_availability', 'demie_handle_availability');

function demie_handle_availability() {
    if (!check_ajax_referer('demie_availability', 'nonce', false)) {
        wp_send_json_error(['message' => __('Your session expired. Refresh the page and try again.', 'demie-v2')], 403);
    }

    $service_id = isset($_POST['service_id']) ? sanitize_text_field(wp_unslash($_POST['service_id'])) : '';
    $date       = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';

    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $service_id)) {
        wp_send_json_error(['message' => __('Choose a package so we can check the right calendar.', 'demie-v2')], 400);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        wp_send_json_error(['message' => __('Choose your wedding date.', 'demie-v2')], 400);
    }

    $timestamp = strtotime($date);
    if (false === $timestamp || $timestamp < strtotime('today')) {
        wp_send_json_error(['message' => __('Please choose a date in the future.', 'demie-v2')], 400);
    }

    $result = demie_slotfy_available_dates($service_id, substr($date, 0, 7));

    if (empty($result['ok']) || empty($result['dates'])) {
        wp_send_json_success([
            'status'  => 'unknown',
            'message' => __('We could not confirm that date from the live calendar. Leave your WhatsApp and we will check it for you.', 'demie-v2'),
        ]);
    }

    if (in_array($date, $result['dates'], true)) {
        wp_send_json_success([
            'status'  => 'available',
            'message' => __('Good news — that date looks open. Leave your WhatsApp and we will hold it while we confirm the details.', 'demie-v2'),
        ]);
    }

    wp_send_json_success([
        'status'  => 'unavailable',
        'message' => __('That date is already taken. Leave your WhatsApp and we will suggest the closest open dates.', 'demie-v2'),
    ]);
}

/* ---------- Lead submission ---------- */

add_action('admin_post_nopriv_demie_lead', 'demie_handle_lead');
add_action('admin_post_demie_lead', 'demie_handle_lead');
add_action('wp_ajax_nopriv_demie_lead', 'demie_handle_lead');
add_action('wp_ajax_demie_lead', 'demie_handle_lead');

function demie_handle_lead() {
    $is_ajax = (defined('DOING_AJAX') && DOING_AJAX)
        || (isset($_POST['demie_ajax']) && '1' === sanitize_text_field(wp_unslash($_POST['demie_ajax'])));

    $respond = function ($success, $message) use ($is_ajax) {
        if ($is_ajax) {
            if ($success) {
                wp_send_json_success(['message' => $message]);
            }
            wp_send_json_error(['message' => $message], 400);
        }

        $back = wp_get_referer();
        if (isset($_POST['demie_lead_redirect'])) {
            $redirect = esc_url_raw(wp_unslash($_POST['demie_lead_redirect']));
            if ('' !== $redirect) {
                $back = $redirect;
            }
        }
        $back = $back ? $back : home_url('/');
        $back = remove_query_arg(['demie_lead'], $back);
        $back = add_query_arg('demie_lead', $success ? 'success' : 'error', $back);
        wp_safe_redirect($back . '#enquiry');
        exit;
    };

    if (!isset($_POST['demie_lead_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['demie_lead_nonce'])), 'demie_lead')) {
        $respond(false, __('Your session expired. Refresh the page and try again.', 'demie-v2'));
    }

    // Honeypot: real visitors never fill this.
    if (!empty($_POST['demie_hp'])) {
        $respond(false, __('Something went wrong. Please try again.', 'demie-v2'));
    }

    // Light rate limit: six enquiries per IP per ten minutes.
    $ip  = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $key = 'demie_lead_rate_' . md5($ip);
    $hit = (int) get_transient($key);
    if ($hit >= 6) {
        $respond(false, __('Please wait a moment before sending another enquiry.', 'demie-v2'));
    }
    set_transient($key, $hit + 1, 10 * MINUTE_IN_SECONDS);

    $name         = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $whatsapp     = isset($_POST['whatsapp']) ? sanitize_text_field(wp_unslash($_POST['whatsapp'])) : '';
    $email        = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $date         = isset($_POST['wedding_date']) ? sanitize_text_field(wp_unslash($_POST['wedding_date'])) : '';
    $venue        = isset($_POST['venue']) ? sanitize_text_field(wp_unslash($_POST['venue'])) : '';
    $package      = isset($_POST['package']) ? sanitize_text_field(wp_unslash($_POST['package'])) : '';
    $package_id   = isset($_POST['package_id']) ? sanitize_text_field(wp_unslash($_POST['package_id'])) : '';
    $source       = isset($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : 'services';
    $intent       = isset($_POST['intent']) ? sanitize_key(wp_unslash($_POST['intent'])) : 'enquiry';
    $consent      = !empty($_POST['consent']) ? '1' : '0';

    if ('' === $name || '' === $whatsapp) {
        $respond(false, __('Please add your name and WhatsApp number.', 'demie-v2'));
    }
    if (!preg_match('/^\+?[0-9][0-9 ()-]{6,19}$/', $whatsapp)) {
        $respond(false, __('That WhatsApp number does not look right. Include the country code, e.g. +265 884 44 48 02.', 'demie-v2'));
    }
    if ('1' !== $consent) {
        $respond(false, __('Please tick the consent box so we know we may contact you on WhatsApp.', 'demie-v2'));
    }
    if ('' !== $date) {
        $parsed = DateTime::createFromFormat('Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date || $parsed->getTimestamp() < strtotime('today')) {
            $date = '';
        }
    }
    if ('' !== $package_id
        && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $package_id)) {
        $package_id = '';
    }

    $title = $name;
    if ('' !== $date) {
        $title .= ' — ' . $date;
    }
    if ('' !== $package) {
        $title .= ' — ' . $package;
    }

    $post_id = wp_insert_post([
        'post_type'   => 'demie_lead',
        'post_status' => 'publish',
        'post_title'  => $title,
    ], true);

    if (is_wp_error($post_id)) {
        $respond(false, __('We could not save your enquiry. Please try again in a moment.', 'demie-v2'));
    }

    $data = compact('name', 'whatsapp', 'email', 'date', 'venue', 'package', 'package_id', 'source', 'intent', 'consent');
    foreach ($data as $field => $value) {
        update_post_meta($post_id, '_demie_lead_' . $field, $value);
    }
    update_post_meta($post_id, '_demie_lead_status', 'new');
    update_post_meta($post_id, '_demie_lead_received', current_time('mysql'));

    demie_notify_lead($post_id, $data);

    $respond(true, __('Thank you — we have your details and will reply on WhatsApp shortly.', 'demie-v2'));
}

function demie_notify_lead($post_id, $data) {
    $recipients = array_filter(array_unique([
        get_option('admin_email'),
        function_exists('demie_email') ? demie_email() : '',
    ]));

    if (!$recipients) {
        return;
    }

    $subject = sprintf(
        /* translators: %s: visitor name */
        __('New enquiry — %s', 'demie-v2'),
        $data['name']
    );

    $lines = [
        sprintf(__('Name: %s', 'demie-v2'), $data['name']),
        sprintf(__('WhatsApp: %s', 'demie-v2'), $data['whatsapp']),
    ];
    if ('' !== $data['email']) {
        $lines[] = sprintf(__('Email: %s', 'demie-v2'), $data['email']);
    }
    if ('' !== $data['date']) {
        $lines[] = sprintf(__('Wedding date: %s', 'demie-v2'), $data['date']);
    }
    if ('' !== $data['venue']) {
        $lines[] = sprintf(__('Venue: %s', 'demie-v2'), $data['venue']);
    }
    if ('' !== $data['package']) {
        $lines[] = sprintf(__('Package: %s', 'demie-v2'), $data['package']);
    }
    $lines[] = sprintf(__('Source: %s (%s)', 'demie-v2'), $data['source'], $data['intent']);
    $lines[] = sprintf(__('Consent: %s', 'demie-v2'), '1' === $data['consent'] ? __('yes', 'demie-v2') : __('no', 'demie-v2'));
    $lines[] = sprintf(__('Open in admin: %s', 'demie-v2'), get_edit_post_link($post_id, 'raw'));

    $digits = preg_replace('/\D+/', '', $data['whatsapp']);
    if ('' !== $digits) {
        $lines[] = sprintf(__('Reply on WhatsApp: %s', 'demie-v2'), 'https://wa.me/' . $digits);
    }

    wp_mail($recipients, $subject, implode("\n", $lines), ['Content-Type: text/plain; charset=UTF-8']);
}

/* ---------- Shared form fields ---------- */

/**
 * Render the common contact fields used by the date panel and the dialog.
 *
 * @param array $args source, intent, package, package_id, date, submit, hidden_fields
 */
function demie_render_lead_fields($args = []) {
    $args = wp_parse_args($args, [
        'source'        => 'services',
        'intent'        => 'enquiry',
        'package'       => '',
        'package_id'    => '',
        'date'          => '',
        'submit'        => __('Send my details', 'demie-v2'),
        'show_email'    => true,
        'show_date'     => false,
        'summary'       => '',
    ]);

    $label_class = 'block text-[10px] font-semibold uppercase tracking-[0.08em] text-white/40';
    $input_class = 'mt-1.5 w-full rounded-xl border border-white/10 bg-brand-deep/60 px-3.5 py-2.5 text-sm text-brand-cream placeholder:text-white/30 focus:border-brand-gold/50 focus:outline-none focus:ring-2 focus:ring-brand-gold/40 [color-scheme:dark]';
    ?>
    <div data-lead-scope>
      <form data-demie-lead-form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" novalidate>
        <input type="hidden" name="action" value="demie_lead">
        <input type="hidden" name="demie_lead_nonce" value="<?php echo esc_attr(wp_create_nonce('demie_lead')); ?>">
        <input type="hidden" name="demie_lead_redirect" value="<?php echo esc_url(demie_current_url()); ?>">
        <input type="hidden" name="source" value="<?php echo esc_attr($args['source']); ?>">
        <input type="hidden" name="intent" value="<?php echo esc_attr($args['intent']); ?>">
        <input type="hidden" name="package" value="<?php echo esc_attr($args['package']); ?>">
        <input type="hidden" name="package_id" value="<?php echo esc_attr($args['package_id']); ?>">
        <input type="hidden" name="wedding_date" value="<?php echo esc_attr($args['date']); ?>">

        <div class="hidden" aria-hidden="true">
          <label for="<?php echo esc_attr('demie-hp-' . wp_unique_id()); ?>">Website</label>
          <input type="text" name="demie_hp" tabindex="-1" autocomplete="off">
        </div>

        <?php if ('' !== $args['summary']) : ?>
          <p data-lead-summary class="mb-3 rounded-lg border border-brand-gold/20 bg-brand-gold/5 px-3 py-2 text-[11px] text-brand-champagne"><?php echo esc_html($args['summary']); ?></p>
        <?php else : ?>
          <p data-lead-summary class="mb-3 hidden rounded-lg border border-brand-gold/20 bg-brand-gold/5 px-3 py-2 text-[11px] text-brand-champagne"></p>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <label class="<?php echo esc_attr($label_class); ?>">
            <?php esc_html_e('Your name', 'demie-v2'); ?>
            <input type="text" name="name" required autocomplete="name" class="<?php echo esc_attr($input_class); ?>" placeholder="Your name">
          </label>
          <label class="<?php echo esc_attr($label_class); ?>">
            <?php esc_html_e('WhatsApp number', 'demie-v2'); ?>
            <input type="tel" name="whatsapp" required autocomplete="tel" inputmode="tel" class="<?php echo esc_attr($input_class); ?>" placeholder="+265 884 44 48 02">
          </label>
        </div>

        <?php if ($args['show_email']) : ?>
          <label class="mt-3 <?php echo esc_attr($label_class); ?>">
            <?php esc_html_e('Email (optional)', 'demie-v2'); ?>
            <input type="email" name="email" autocomplete="email" class="<?php echo esc_attr($input_class); ?>" placeholder="you@example.com">
          </label>
        <?php endif; ?>

        <?php if ($args['show_date']) : ?>
          <label class="mt-3 <?php echo esc_attr($label_class); ?>">
            <?php esc_html_e('Wedding date', 'demie-v2'); ?>
            <input type="date" name="wedding_date" class="<?php echo esc_attr($input_class); ?>">
          </label>
        <?php endif; ?>

        <label class="mt-4 flex items-start gap-2 text-[11px] leading-relaxed text-white/50">
          <input type="checkbox" name="consent" value="1" required class="mt-0.5 h-3.5 w-3.5 shrink-0 rounded border-white/20 bg-brand-deep accent-brand-gold">
          <span><?php esc_html_e('I agree to be contacted on WhatsApp about this enquiry. No spam — one reply from a real person.', 'demie-v2'); ?></span>
        </label>

        <button type="submit" class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-brand-champagne px-5 py-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-brand-charcoal transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-60">
          <?php echo esc_html($args['submit']); ?>
        </button>

        <p data-lead-error hidden role="alert" class="mt-2 text-[11px] text-red-300"></p>
      </form>

      <div data-lead-success hidden role="status" class="rounded-xl border border-brand-gold/25 bg-brand-gold/5 p-4 text-center">
        <p data-lead-success-message class="text-sm font-light text-brand-cream"></p>
        <a class="mt-3 inline-flex items-center justify-center rounded-xl border border-brand-champagne/60 px-4 py-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-brand-champagne transition hover:bg-brand-champagne hover:text-brand-charcoal" href="<?php echo esc_url(function_exists('demie_whatsapp_url') ? demie_whatsapp_url() : 'https://wa.me/265884444802'); ?>" target="_blank" rel="noopener">
          <?php esc_html_e('Message us now', 'demie-v2'); ?>
        </a>
      </div>
    </div>
    <?php
}
