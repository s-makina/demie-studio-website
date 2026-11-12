<?php
/**
 * Template Name: Services (V2)
 *
 * Lists the live Slotfy service catalogue as booking-widget-style cards,
 * grouped by the same categories the booking widget shows. WordPress
 * Services posts are no longer the source for this page — Slotfy owns the
 * public catalogue.
 */
get_header();
the_post();
get_template_part('template-parts/page-titlebar', null, ['title' => get_the_title() ?: __('Services', 'demie-v2'), 'subtitle' => __('Films & Stills', 'demie-v2')]);

$catalog    = function_exists('demie_slotfy_service_catalog') ? demie_slotfy_service_catalog() : ['money' => [], 'categories' => []];
$categories = isset($catalog['categories']) ? $catalog['categories'] : [];
$money      = isset($catalog['money']) ? $catalog['money'] : [];
$booking    = function_exists('demie_slotfy_booking_url') ? demie_slotfy_booking_url() : demie_page_url('booking');

$service_total = 0;
foreach ($categories as $category_count) {
    $service_total += count($category_count['services']);
}
?>
<section class="py-20 md:py-28 px-6 md:px-14 bg-brand-deep relative overflow-hidden">
  <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/texture-2.png'); ?>" alt="" aria-hidden="true" class="absolute bottom-0 left-0 w-full h-auto opacity-80 pointer-events-none select-none">
  <div class="max-w-5xl mx-auto demie-v2-prose text-center mb-12 [&_p]:text-white/60 [&_h2]:text-brand-cream [&_h3]:text-brand-cream relative z-10"><?php the_content(); ?></div>

  <?php if ($categories) : ?>
    <div class="max-w-6xl mx-auto relative z-10">

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
                ?>
                <a class="service-card group relative flex flex-col overflow-hidden rounded-2xl border border-white/10 bg-brand-charcoal px-4 py-4 text-brand-cream transition hover:border-brand-gold/45 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-gold/60 before:absolute before:inset-y-0 before:left-0 before:w-[3px] before:bg-gradient-to-b before:from-brand-gold before:to-brand-champagne before:opacity-0 before:transition-opacity hover:before:opacity-100 reveal-on-scroll"
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

                  <span class="mt-3 flex items-center justify-between gap-3 text-[11px] font-semibold text-brand-gold">
                    <span class="font-medium text-white/30"><?php esc_html_e('Bookable online', 'demie-v2'); ?></span>
                    <span class="ml-auto whitespace-nowrap"><?php esc_html_e('Choose package', 'demie-v2'); ?> <span aria-hidden="true">&rarr;</span></span>
                  </span>
                </a>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
      </div>

      <p class="mt-8 text-center text-[11px] font-light text-white/35">
        <?php esc_html_e('Prices are in Malawian Kwacha and confirmed at checkout. Availability updates live from the booking calendar.', 'demie-v2'); ?>
      </p>
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

  <?php get_template_part('template-parts/packages', null, ['spacing' => 'mt-20 md:mt-28']); ?>
</section>
<?php get_template_part('template-parts/booking'); ?>
<?php get_template_part('template-parts/instagram'); ?>
<?php get_footer(); ?>
