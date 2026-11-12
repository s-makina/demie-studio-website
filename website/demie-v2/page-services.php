<?php
/**
 * Template Name: Services (V2)
 *
 * Lists the live Slotfy service catalogue as booking-widget-style cards,
 * grouped by the same categories the booking widget shows. WordPress
 * Services posts are no longer the source for this page — Slotfy owns the
 * public catalogue.
 *
 * Lead capture sits in three layers:
 *  1. date checker above the catalogue (availability, then contact),
 *  2. "Send to my WhatsApp" on every package card,
 *  3. a planning-guide request below the cards.
 */
get_header();
the_post();
get_template_part('template-parts/page-titlebar', null, ['title' => get_the_title() ?: __('Services', 'demie-v2'), 'subtitle' => __('Films & Stills', 'demie-v2')]);

$catalog    = function_exists('demie_slotfy_service_catalog') ? demie_slotfy_service_catalog() : ['money' => [], 'categories' => []];
$categories = isset($catalog['categories']) ? $catalog['categories'] : [];
$money      = isset($catalog['money']) ? $catalog['money'] : [];
$booking    = function_exists('demie_slotfy_booking_url') ? demie_slotfy_booking_url() : demie_page_url('booking');

$service_total = 0;
$first_service = null;
foreach ($categories as $category_count) {
    $service_total += count($category_count['services']);
    if (null === $first_service && !empty($category_count['services'])) {
        $first_service = $category_count['services'][0];
    }
}

$lead_state = isset($_GET['demie_lead']) ? sanitize_key(wp_unslash($_GET['demie_lead'])) : '';
$lead_date  = isset($_GET['date']) ? sanitize_text_field(wp_unslash($_GET['date'])) : '';
?>
<section class="py-20 md:py-28 px-6 md:px-14 bg-brand-deep relative overflow-hidden">
  <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/texture-2.png'); ?>" alt="" aria-hidden="true" class="absolute bottom-0 left-0 w-full h-auto opacity-80 pointer-events-none select-none">
  <div class="max-w-5xl mx-auto demie-v2-prose text-center mb-12 [&_p]:text-white/60 [&_h2]:text-brand-cream [&_h3]:text-brand-cream relative z-10"><?php the_content(); ?></div>

  <div class="max-w-6xl mx-auto relative z-10">

    <?php if ('success' === $lead_state) : ?>
      <div id="enquiry" role="status" class="mb-8 flex items-start gap-3 rounded-2xl border border-brand-gold/30 bg-brand-gold/[0.07] px-5 py-4 text-left">
        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-gold/15 text-brand-gold" aria-hidden="true">
          <svg class="h-3.5 w-3.5" viewBox="0 0 12 12" fill="none"><path d="M2 6.2l2.4 2.4L10 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <p class="text-sm font-light leading-relaxed text-brand-cream">
          <?php esc_html_e('Thank you — we have your details and will reply on WhatsApp shortly. If it is urgent, message us directly.', 'demie-v2'); ?>
        </p>
      </div>
    <?php elseif ('error' === $lead_state) : ?>
      <div id="enquiry" role="alert" class="mb-8 rounded-2xl border border-red-400/30 bg-red-500/[0.07] px-5 py-4 text-left text-sm font-light text-red-100">
        <?php esc_html_e('We could not send that enquiry. Please check your details and try again, or message us on WhatsApp.', 'demie-v2'); ?>
      </div>
    <?php endif; ?>

    <?php if ($categories && $first_service) : ?>
      <div id="date-check" class="mb-9 reveal-on-scroll">
        <div class="relative overflow-hidden rounded-2xl border border-brand-gold/25 bg-brand-charcoal">
          <span class="absolute inset-y-0 left-0 w-[3px] bg-gradient-to-b from-brand-gold to-brand-champagne" aria-hidden="true"></span>
          <div class="grid gap-7 p-5 md:p-7 lg:grid-cols-[1.05fr_1fr] lg:gap-10 lg:p-8">

            <div>
              <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-brand-gold"><?php esc_html_e('Check availability', 'demie-v2'); ?></p>
              <h2 class="mt-2 font-serif text-2xl leading-tight text-brand-cream md:text-3xl"><?php esc_html_e('Is your wedding date still open?', 'demie-v2'); ?></h2>
              <p class="mt-3 max-w-md text-sm font-light leading-relaxed text-white/55">
                <?php esc_html_e('Tell us your date and the package you have in mind. We will check the live calendar and reply on WhatsApp — usually within one business hour.', 'demie-v2'); ?>
              </p>

              <ul class="mt-5 space-y-2 text-[11.5px] text-white/55">
                <?php
                $date_points = [
                    __('Free, no-obligation date check', 'demie-v2'),
                    __('We only hold a date once you confirm', 'demie-v2'),
                    __('A real person replies — no automated sequence', 'demie-v2'),
                ];
                foreach ($date_points as $point) :
                    ?>
                    <li class="flex items-start gap-2">
                      <svg class="mt-[2px] h-3 w-3 shrink-0 text-emerald-400/80" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2 6.2l2.4 2.4L10 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                      <span><?php echo esc_html($point); ?></span>
                    </li>
                <?php endforeach; ?>
              </ul>
            </div>

            <div>
              <form data-demie-date-form class="rounded-xl border border-white/10 bg-brand-deep/60 p-4" novalidate>
                <div class="grid gap-3 sm:grid-cols-2">
                  <label class="block text-[10px] font-semibold uppercase tracking-[0.08em] text-white/40">
                    <?php esc_html_e('Wedding date', 'demie-v2'); ?>
                    <input type="date" name="date" required min="<?php echo esc_attr(date_i18n('Y-m-d')); ?>" value="<?php echo esc_attr($lead_date); ?>" class="mt-1.5 w-full rounded-xl border border-white/10 bg-brand-charcoal px-3.5 py-2.5 text-sm text-brand-cream focus:border-brand-gold/50 focus:outline-none focus:ring-2 focus:ring-brand-gold/40 [color-scheme:dark]">
                  </label>
                  <label class="block text-[10px] font-semibold uppercase tracking-[0.08em] text-white/40">
                    <?php esc_html_e('Package', 'demie-v2'); ?>
                    <span class="relative mt-1.5 block">
                      <select name="service_id" class="w-full appearance-none rounded-xl border border-white/10 bg-brand-charcoal py-2.5 pl-3.5 pr-9 text-sm font-normal normal-case tracking-normal text-brand-cream hover:border-white/20 focus:border-brand-gold/50 focus:outline-none focus:ring-2 focus:ring-brand-gold/40">
                        <?php foreach ($categories as $category) : ?>
                          <optgroup label="<?php echo esc_attr($category['name']); ?>">
                            <?php foreach ($category['services'] as $service) : ?>
                              <option value="<?php echo esc_attr($service['id']); ?>" data-name="<?php echo esc_attr($service['name']); ?>">
                                <?php echo esc_html($service['name']); ?>
                              </option>
                            <?php endforeach; ?>
                          </optgroup>
                        <?php endforeach; ?>
                      </select>
                      <svg class="pointer-events-none absolute right-3.5 top-1/2 h-2.5 w-2.5 -translate-y-1/2 text-white/40" viewBox="0 0 10 6" fill="none" aria-hidden="true"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </span>
                  </label>
                </div>

                <button type="submit" data-date-submit class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-champagne px-5 py-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-brand-charcoal transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-60">
                  <span data-date-submit-label><?php esc_html_e('Check my date', 'demie-v2'); ?></span>
                  <span aria-hidden="true">&rarr;</span>
                </button>

                <p class="mt-2.5 text-center text-[10.5px] leading-relaxed text-white/30">
                  <?php esc_html_e('No spam. We reply once, from a real person.', 'demie-v2'); ?>
                </p>
                <p data-date-error hidden role="alert" class="mt-2 text-[11px] text-red-300"></p>
              </form>

              <div data-date-result hidden class="mt-4">
                <div data-date-card class="rounded-xl border border-white/10 bg-brand-deep/60 p-4">
                  <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-white/35" data-date-status-label><?php esc_html_e('Live calendar', 'demie-v2'); ?></p>
                  <p data-date-message class="mt-2 text-sm font-light leading-relaxed text-brand-cream"></p>
                </div>

                <div data-date-contact hidden class="mt-4 border-t border-white/10 pt-4">
                  <p class="mb-3 text-xs font-medium text-brand-cream"><?php esc_html_e('Where should we send the confirmation?', 'demie-v2'); ?></p>
                  <?php
                  demie_render_lead_fields([
                      'source'     => 'services-date',
                      'intent'     => 'availability',
                      'package'    => $first_service['name'],
                      'package_id' => $first_service['id'],
                      'submit'     => __('Send my date request', 'demie-v2'),
                      'show_email' => false,
                      'show_date'  => false,
                  ]);
                  ?>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      <div class="flex items-start justify-between gap-5 mb-7 reveal-on-scroll">
        <div>
          <h2 class="font-sans text-xl md:text-2xl font-semibold text-brand-cream tracking-tight"><?php esc_html_e('Choose a Package', 'demie-v2'); ?></h2>
          <p class="mt-1 text-sm text-white/50 font-light"><?php esc_html_e('Select the package you would like to book.', 'demie-v2'); ?></p>
        </div>

        <?php if (count($categories) > 1) : ?>
          <div class="relative shrink-0">
            <label class="sr-only" for="demie-service-filter"><?php esc_html_e('Filter packages by category', 'demie-v2'); ?></label>
            <select id="demie-service-filter" data-service-filter class="appearance-none max-w-[320px] rounded-xl border border-white/10 bg-brand-charcoal py-2.5 pl-4 pr-10 text-xs font-semibold text-brand-cream hover:border-white/20 focus:outline-none focus:ring-2 focus:ring-brand-gold/60">
              <option value=""><?php printf(esc_html__('All packages (%d)', 'demie-v2'), (int) $service_total); ?></option>
              <?php foreach ($categories as $category) : ?>
                <option value="<?php echo esc_attr($category['id']); ?>"><?php echo esc_html($category['name']); ?> (<?php echo (int) count($category['services']); ?>)</option>
              <?php endforeach; ?>
            </select>
            <svg class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 h-2.5 w-2.5 text-white/40" viewBox="0 0 10 6" fill="none" aria-hidden="true"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
          </div>
        <?php endif; ?>
      </div>

      <div data-service-sections>
        <?php foreach ($categories as $category) : ?>
          <section class="mb-7 last:mb-0" data-service-section data-category-id="<?php echo esc_attr($category['id']); ?>">
            <div class="flex items-baseline gap-3 mb-3">
              <h3 class="text-sm font-semibold text-brand-cream/90"><?php echo esc_html($category['name']); ?></h3>
              <span class="whitespace-nowrap text-[11px] text-white/35">
                <?php
                $count = count($category['services']);
                printf(
                    esc_html(_n('%s package', '%s packages', $count, 'demie-v2')),
                    esc_html(number_format_i18n($count))
                );
                ?>
              </span>
              <span class="h-px flex-1 bg-white/10" aria-hidden="true"></span>
            </div>

            <?php if (!empty($category['description'])) : ?>
              <p class="mb-3 max-w-2xl text-xs font-light text-white/45"><?php echo esc_html($category['description']); ?></p>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <?php foreach ($category['services'] as $service) : ?>
                <?php
                $duration        = function_exists('demie_slotfy_format_duration') ? demie_slotfy_format_duration($service['duration']) : '';
                $inclusions      = is_array($service['features']) ? $service['features'] : [];
                $inclusion_count = count($inclusions);

                $meta = [];
                if ('' !== $duration) {
                    $meta[] = $duration;
                }
                if ($inclusion_count > 0) {
                    $meta[] = sprintf(
                        _n('%s inclusion', '%s inclusions', $inclusion_count, 'demie-v2'),
                        number_format_i18n($inclusion_count)
                    );
                }

                $price = $service['price'] > 0 ? demie_slotfy_format_price($service['price'], $money) : '';
                $summary = '' !== $price ? sprintf(__('%s · %s', 'demie-v2'), $service['name'], $price) : $service['name'];
                ?>
                <article class="service-card group relative flex flex-col overflow-hidden rounded-2xl border border-white/10 bg-brand-charcoal text-brand-cream transition hover:border-brand-gold/45 before:absolute before:inset-y-0 before:left-0 before:w-[3px] before:bg-gradient-to-b before:from-brand-gold before:to-brand-champagne before:opacity-0 before:transition-opacity hover:before:opacity-100 reveal-on-scroll">
                  <a class="flex flex-1 flex-col px-4 pb-3 pt-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-gold/60"
                     href="<?php echo esc_url(add_query_arg('service', $service['id'], $booking)); ?>"
                     aria-label="<?php echo esc_attr(sprintf(__('Choose %1$s, %2$s, %3$s', 'demie-v2'), $service['name'], $price, implode(', ', $meta))); ?>">
                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.08em] text-white/35"><?php echo esc_html($category['name']); ?></span>

                    <span class="flex items-start justify-between gap-3">
                      <span class="text-[15px] font-semibold leading-snug text-brand-cream"><?php echo esc_html($service['name']); ?></span>
                      <?php if ('' !== $price) : ?>
                        <span class="whitespace-nowrap font-mono text-base font-bold text-brand-gold"><?php echo esc_html($price); ?></span>
                      <?php endif; ?>
                    </span>

                    <?php if ($meta) : ?>
                      <span class="mt-1 block text-[11px] text-white/40"><?php echo esc_html(implode(' · ', $meta)); ?></span>
                    <?php endif; ?>

                    <?php if ('' !== $service['description']) : ?>
                      <span class="mt-3 block text-xs font-light leading-relaxed text-white/55 line-clamp-2"><?php echo esc_html($service['description']); ?></span>
                    <?php endif; ?>

                    <span class="my-2.5 block h-px bg-white/10" aria-hidden="true"></span>

                    <?php if ($inclusions) : ?>
                      <span class="grid grid-cols-1 sm:grid-cols-2 gap-x-3.5 gap-y-1.5 text-[11px] leading-snug text-white/60">
                        <?php foreach ($inclusions as $feature) : ?>
                          <span class="flex items-start gap-1.5">
                            <svg class="mt-[2px] h-3 w-3 shrink-0 text-emerald-400/80" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2 6.2l2.4 2.4L10 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span><?php echo esc_html($feature); ?></span>
                          </span>
                        <?php endforeach; ?>
                      </span>
                    <?php endif; ?>
                  </a>

                  <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-white/[0.07] px-4 py-2.5 text-[11px]">
                    <a class="inline-flex items-center gap-1.5 font-semibold text-brand-gold transition hover:text-brand-champagne focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-gold/60"
                       href="<?php echo esc_url(add_query_arg('service', $service['id'], $booking)); ?>">
                      <?php esc_html_e('Choose package', 'demie-v2'); ?> <span aria-hidden="true">&rarr;</span>
                    </a>
                    <button type="button"
                            class="inline-flex items-center gap-1.5 rounded-full border border-white/10 px-2.5 py-1 font-medium text-white/55 transition hover:border-brand-gold/40 hover:text-brand-champagne focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-gold/60"
                            data-lead-open
                            data-source="services-card"
                            data-intent="package"
                            data-package="<?php echo esc_attr($service['name']); ?>"
                            data-package-id="<?php echo esc_attr($service['id']); ?>"
                            data-summary="<?php echo esc_attr($summary); ?>"
                            aria-label="<?php echo esc_attr(sprintf(__('Send %s to my WhatsApp', 'demie-v2'), $service['name'])); ?>">
                      <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.39a9.86 9.86 0 0 0 4.74 1.21h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 1.67c2.2 0 4.27.86 5.83 2.42a8.2 8.2 0 0 1 2.41 5.82c0 4.54-3.7 8.23-8.24 8.23a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.18 8.18 0 0 1-1.26-4.37c0-4.54 3.7-8.24 8.24-8.24Zm-2.5 3.72c-.23 0-.6.09-.92.43-.31.34-1.2 1.18-1.2 2.87 0 1.7 1.23 3.34 1.4 3.57.17.23 2.36 3.76 5.8 5.12 2.86 1.13 3.44.9 4.06.85.62-.06 2-.82 2.28-1.6.28-.79.28-1.46.2-1.6-.09-.14-.31-.23-.65-.4-.34-.17-2-.98-2.3-1.09-.31-.11-.54-.17-.77.17-.23.34-.88 1.1-1.08 1.33-.2.23-.4.26-.74.09-.34-.17-1.42-.52-2.7-1.67-1-.89-1.67-1.99-1.87-2.33-.2-.34-.02-.52.15-.69.15-.15.34-.4.51-.6.17-.2.23-.34.34-.57.11-.23.06-.43-.03-.6-.09-.17-.77-1.86-1.06-2.54-.28-.67-.56-.58-.77-.59l-.65-.01Z"/></svg>
                      <?php esc_html_e('Send to my WhatsApp', 'demie-v2'); ?>
                    </button>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
      </div>

      <p class="mt-8 text-center text-[11px] font-light text-white/35">
        <?php esc_html_e('Prices are in Malawian Kwacha and confirmed at checkout. Availability updates live from the booking calendar.', 'demie-v2'); ?>
      </p>

      <div id="guide" class="mt-12 reveal-on-scroll">
        <div class="grid gap-7 rounded-2xl border border-white/10 bg-brand-charcoal p-5 md:p-8 lg:grid-cols-[1.05fr_1fr] lg:gap-10">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-brand-gold"><?php esc_html_e('Free planning guide', 'demie-v2'); ?></p>
            <h2 class="mt-2 font-serif text-2xl leading-tight text-brand-cream md:text-3xl"><?php esc_html_e('Not ready to book yet?', 'demie-v2'); ?></h2>
            <p class="mt-3 max-w-md text-sm font-light leading-relaxed text-white/55">
              <?php esc_html_e('Get our wedding-day planning checklist and a side-by-side guide to every package — so you can decide with your partner and family, without pressure.', 'demie-v2'); ?>
            </p>

            <ul class="mt-5 space-y-2 text-[11.5px] text-white/55">
              <?php
              $guide_points = [
                  __('A realistic photo + film timeline for the day', 'demie-v2'),
                  __('What to prepare before your photographer arrives', 'demie-v2'),
                  __('The questions to ask any wedding photographer', 'demie-v2'),
                  __('A plain comparison of our packages and add-ons', 'demie-v2'),
              ];
              foreach ($guide_points as $point) :
                  ?>
                  <li class="flex items-start gap-2">
                    <svg class="mt-[2px] h-3 w-3 shrink-0 text-emerald-400/80" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2 6.2l2.4 2.4L10 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span><?php echo esc_html($point); ?></span>
                  </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <div class="rounded-xl border border-white/10 bg-brand-deep/60 p-4">
            <?php
            demie_render_lead_fields([
                'source'     => 'services-guide',
                'intent'     => 'guide',
                'submit'     => __('Send me the guide', 'demie-v2'),
                'show_email' => true,
                'show_date'  => false,
                'summary'    => __('The guide arrives by email or WhatsApp — whichever you prefer.', 'demie-v2'),
            ]);
            ?>
          </div>
        </div>
      </div>

    <?php else : ?>
      <div class="max-w-2xl mx-auto text-center relative z-10">
        <p class="text-white/60 font-light mb-8"><?php esc_html_e('Our live service menu is being updated. Please check back soon, or head straight to booking to see available dates.', 'demie-v2'); ?></p>
        <a class="inline-flex items-center justify-center px-8 py-4 bg-brand-champagne text-brand-charcoal hover:bg-white text-xs uppercase tracking-luxury font-medium transition-colors" href="<?php echo esc_url($booking); ?>">
          <?php esc_html_e('Go to booking', 'demie-v2'); ?>
        </a>

        <?php if (current_user_can('manage_options')) : ?>
          <?php
          $diag           = isset($GLOBALS['demie_slotfy_last_api']) ? $GLOBALS['demie_slotfy_last_api'] : null;
          $diag_base      = function_exists('demie_slotfy_api_base') ? demie_slotfy_api_base() : '';
          $diag_detected  = function_exists('demie_slotfy_detected_api_url') ? demie_slotfy_detected_api_url() : '';
          ?>
          <div class="mt-10 p-5 border border-brand-gold/40 bg-black/30 text-left text-xs font-light text-white/70">
            <p class="text-brand-gold uppercase tracking-widest text-[10px] mb-3"><?php esc_html_e('Admin diagnostics', 'demie-v2'); ?></p>
            <p class="mb-1"><?php esc_html_e('Resolved API base:', 'demie-v2'); ?> <code><?php echo esc_html('' !== $diag_base ? $diag_base : __('(empty)', 'demie-v2')); ?></code></p>
            <?php if ('' !== $diag_detected) : ?>
              <p class="mb-1"><?php esc_html_e('Auto-detected API:', 'demie-v2'); ?> <code><?php echo esc_html($diag_detected); ?></code></p>
            <?php endif; ?>
            <?php if (is_array($diag) && !empty($diag)) : ?>
              <p class="mb-1"><?php esc_html_e('Last request:', 'demie-v2'); ?> <code><?php echo esc_html($diag['url'] ?? ''); ?></code></p>
              <p class="mb-1">
                <?php if (isset($diag['code'])) : ?>
                  <?php esc_html_e('HTTP status:', 'demie-v2'); ?> <?php echo esc_html((string) $diag['code']); ?>
                  (<?php echo esc_html((string) ($diag['bytes'] ?? 0)); ?> <?php esc_html_e('bytes', 'demie-v2'); ?>)
                <?php elseif (!empty($diag['error'])) : ?>
                  <?php esc_html_e('Transport error:', 'demie-v2'); ?> <?php echo esc_html($diag['error']); ?>
                <?php endif; ?>
              </p>
            <?php else : ?>
              <p class="mb-1"><?php esc_html_e('No API request was made. Set a Slotfy base URL and API URL in Demie Settings.', 'demie-v2'); ?></p>
            <?php endif; ?>
            <p class="mt-3">
              <a class="underline text-brand-gold" href="<?php echo esc_url(admin_url('options-general.php?page=demie-settings')); ?>">
                <?php esc_html_e('Open Demie Settings → Booking', 'demie-v2'); ?>
              </a>
            </p>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php get_template_part('template-parts/packages', null, ['spacing' => 'mt-20 md:mt-28']); ?>
</section>

<?php if ($categories && $first_service) : ?>
  <dialog id="demie-lead-dialog" data-lead-dialog class="demie-lead-dialog" aria-labelledby="demie-lead-dialog-title">
    <div class="demie-lead-dialog__panel">
      <button type="button" data-lead-close class="demie-lead-dialog__close" aria-label="<?php esc_attr_e('Close', 'demie-v2'); ?>">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
      </button>

      <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-brand-gold"><?php esc_html_e('Send this package', 'demie-v2'); ?></p>
      <h2 id="demie-lead-dialog-title" data-lead-dialog-title class="mt-2 font-serif text-2xl leading-tight text-brand-cream">
        <?php esc_html_e('Get this package on WhatsApp', 'demie-v2'); ?>
      </h2>
      <p data-lead-dialog-copy class="mt-2 mb-4 text-sm font-light leading-relaxed text-white/55">
        <?php esc_html_e('Leave your name and number and we will send the full package details — price, inclusions and available dates.', 'demie-v2'); ?>
      </p>

      <?php
      demie_render_lead_fields([
          'source'     => 'services-card',
          'intent'     => 'package',
          'package'    => $first_service['name'],
          'package_id' => $first_service['id'],
          'submit'     => __('Send this package to me', 'demie-v2'),
          'show_email' => true,
          'show_date'  => false,
          'summary'    => $first_service['name'],
      ]);
      ?>
    </div>
  </dialog>

  <div data-sticky-cta class="sticky-cta fixed inset-x-0 bottom-0 z-40 px-4 pb-4 md:hidden">
    <a href="#date-check" class="flex items-center justify-between gap-3 rounded-xl border border-brand-gold/30 bg-brand-charcoal/95 px-4 py-3 shadow-2xl backdrop-blur-md">
      <span class="text-[11px] font-medium leading-snug text-brand-cream"><?php esc_html_e('Is your date still open?', 'demie-v2'); ?></span>
      <span class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand-champagne px-3 py-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-brand-charcoal">
        <?php esc_html_e('Check my date', 'demie-v2'); ?> <span aria-hidden="true">&rarr;</span>
      </span>
    </a>
  </div>
<?php endif; ?>

<?php get_template_part('template-parts/booking'); ?>
<?php get_template_part('template-parts/instagram'); ?>
<?php get_footer(); ?>
