<?php
/**
 * Template Name: Services (V2)
 *
 * Lists the live Slotfy service catalogue as v2 editorial cards, grouped by
 * the same categories the booking widget shows. WordPress Services posts are
 * no longer the source for this page — Slotfy owns the public catalogue.
 */
get_header();
the_post();
get_template_part('template-parts/page-titlebar', null, ['title' => get_the_title() ?: __('Services', 'demie-v2'), 'subtitle' => __('Films & Stills', 'demie-v2')]);

$catalog    = function_exists('demie_slotfy_service_catalog') ? demie_slotfy_service_catalog() : ['money' => [], 'categories' => []];
$categories = isset($catalog['categories']) ? $catalog['categories'] : [];
$money      = isset($catalog['money']) ? $catalog['money'] : [];
$booking    = function_exists('demie_slotfy_booking_url') ? demie_slotfy_booking_url() : demie_page_url('booking');
?>
<section class="py-20 md:py-28 px-6 md:px-14 bg-brand-deep relative overflow-hidden">
  <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/texture-2.png'); ?>" alt="" aria-hidden="true" class="absolute bottom-0 left-0 w-full h-auto opacity-80 pointer-events-none select-none">
  <div class="max-w-5xl mx-auto demie-v2-prose text-center mb-12 [&_p]:text-white/60 [&_h2]:text-brand-cream [&_h3]:text-brand-cream relative z-10"><?php the_content(); ?></div>

  <?php if ($categories) : ?>
    <div class="max-w-7xl mx-auto space-y-16 md:space-y-20 relative z-10">
      <?php foreach ($categories as $category) : ?>
        <div>
          <div class="text-center max-w-2xl mx-auto mb-10 reveal-on-scroll">
            <span class="text-xs uppercase tracking-widest2 text-brand-gold font-sans font-medium mb-3 block"><?php esc_html_e('Bookable Online', 'demie-v2'); ?></span>
            <h2 class="font-serif text-3xl sm:text-4xl text-brand-cream font-normal tracking-tight mb-3"><?php echo esc_html($category['name']); ?></h2>
            <?php if (!empty($category['description'])) : ?>
              <p class="text-white/60 text-sm md:text-base font-light"><?php echo esc_html($category['description']); ?></p>
            <?php endif; ?>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <?php foreach ($category['services'] as $service) : ?>
              <div class="service-card reveal-on-scroll flex flex-col p-8 md:p-10 bg-brand-charcoal border border-white/10 hover:border-brand-gold/60 transition-colors">
                <div class="flex items-start justify-between gap-4 mb-2">
                  <h3 class="font-serif text-2xl text-brand-cream"><?php echo esc_html($service['name']); ?></h3>
                  <?php if ($service['price'] > 0) : ?>
                    <span class="whitespace-nowrap text-sm text-brand-gold font-light"><?php echo esc_html(demie_slotfy_format_price($service['price'], $money)); ?></span>
                  <?php endif; ?>
                </div>

                <?php $duration = demie_slotfy_format_duration($service['duration']); ?>
                <?php if ('' !== $duration) : ?>
                  <p class="text-[11px] uppercase tracking-widest text-white/40 mb-4"><?php echo esc_html($duration); ?></p>
                <?php endif; ?>

                <?php if ('' !== $service['description']) : ?>
                  <p class="text-white/60 text-sm font-light leading-relaxed mb-5"><?php echo esc_html($service['description']); ?></p>
                <?php endif; ?>

                <?php if ($service['features']) : ?>
                  <ul class="text-xs text-white/70 space-y-2 font-light mb-7">
                    <?php foreach ($service['features'] as $feature) : ?>
                      <li class="flex items-start gap-2">
                        <span class="text-brand-gold">•</span>
                        <span><?php echo esc_html($feature); ?></span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>

                <a class="mt-auto inline-flex items-center justify-center px-6 py-3 border border-brand-champagne/60 text-brand-champagne hover:bg-brand-champagne hover:text-brand-charcoal text-[11px] uppercase tracking-luxury font-medium transition-colors" href="<?php echo esc_url(add_query_arg('service', $service['id'], $booking)); ?>">
                  <?php esc_html_e('Book this service', 'demie-v2'); ?>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
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
