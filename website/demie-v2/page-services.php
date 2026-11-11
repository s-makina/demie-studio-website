<?php
/**
 * Template Name: Services (V2)
 * Lists all Service CPT entries as v2 editorial cards.
 */
get_header();
the_post();
get_template_part('template-parts/page-titlebar', null, ['title' => get_the_title() ?: __('Services', 'demie-v2'), 'subtitle' => __('Films & Stills', 'demie-v2')]);
$services = function_exists('demie_get_services') ? demie_get_services() : [];
?>
<section class="py-20 md:py-28 px-6 md:px-14 bg-brand-deep relative overflow-hidden">
  <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/texture-2.png'); ?>" alt="" aria-hidden="true" class="absolute bottom-0 left-0 w-full h-auto opacity-80 pointer-events-none select-none">
  <div class="max-w-5xl mx-auto demie-v2-prose text-center mb-12 [&_p]:text-white/60 [&_h2]:text-brand-cream [&_h3]:text-brand-cream relative z-10"><?php the_content(); ?></div>
  <div id="services-list" class="services-collapsible max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10" data-services-collapsible data-limit="<?php echo esc_attr(demie_v2_services_initial_limit()); ?>">
    <?php if ($services) : foreach ($services as $i => $svc) :
      $icon = get_the_post_thumbnail_url($svc, 'medium');
    ?>
      <div class="service-card reveal-on-scroll p-10 bg-brand-charcoal border border-white/10 flex gap-6">
        <?php if ($icon) : ?><img src="<?php echo esc_url($icon); ?>" alt="" class="w-14 h-14 object-contain shrink-0"><?php endif; ?>
        <div>
          <span class="font-serif text-2xl text-brand-gold font-light"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></span>
          <h2 class="font-serif text-2xl text-brand-cream mb-2"><?php echo esc_html(get_the_title($svc)); ?></h2>
          <p class="text-white/60 text-sm font-light leading-relaxed"><?php echo esc_html(function_exists('demie_service_short') ? demie_service_short($svc) : ''); ?></p>
          <?php $full = get_post_field('post_content', $svc); if ($full) : ?>
            <div class="text-white/60 text-sm font-light leading-relaxed mt-3"><?php echo wp_kses_post($full); ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; else : ?>
      <p class="text-center text-white/60 font-light md:col-span-2"><?php esc_html_e('Services are being prepared. Please check back soon.', 'demie-v2'); ?></p>
    <?php endif; ?>
  </div>
  <?php demie_v2_services_toggle('services-list', count($services)); ?>

  <?php
  $demie_catalog  = function_exists('demie_slotfy_package_catalog') ? demie_slotfy_package_catalog() : [];
  $demie_packages = isset($demie_catalog['packages']) ? $demie_catalog['packages'] : [];
  ?>
  <?php if ($demie_packages) : ?>
    <div id="packages" class="mt-20 md:mt-28 relative z-10">
      <div class="text-center max-w-2xl mx-auto mb-10">
        <span class="text-xs uppercase tracking-widest2 text-brand-gold font-sans font-medium mb-3 block"><?php esc_html_e('Bookable Online', 'demie-v2'); ?></span>
        <h2 class="font-serif text-3xl sm:text-5xl text-brand-cream font-normal tracking-tight mb-3"><?php esc_html_e('Packages', 'demie-v2'); ?></h2>
        <p class="text-white/60 text-sm md:text-base font-light"><?php esc_html_e('The same packages you will find on our booking page, pulled live from the studio calendar.', 'demie-v2'); ?></p>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($demie_packages as $demie_pkg) : ?>
          <div class="flex flex-col p-8 bg-brand-charcoal border border-white/10 hover:border-brand-gold/60 transition-colors reveal-on-scroll">
            <span class="text-[10px] uppercase tracking-widest2 text-brand-champagne mb-3"><?php esc_html_e('Package', 'demie-v2'); ?></span>
            <div class="flex items-start justify-between gap-4 mb-2">
              <h3 class="font-serif text-2xl text-brand-cream"><?php echo esc_html($demie_pkg['name']); ?></h3>
              <span class="whitespace-nowrap text-sm text-brand-gold font-light"><?php echo esc_html(demie_slotfy_format_price($demie_pkg['price'], $demie_catalog)); ?></span>
            </div>
            <?php $demie_meta = demie_slotfy_package_meta($demie_pkg); if ('' !== $demie_meta) : ?>
              <p class="text-[11px] uppercase tracking-widest text-white/40 mb-4"><?php echo esc_html($demie_meta); ?></p>
            <?php endif; ?>
            <?php if ('' !== $demie_pkg['description']) : ?>
              <p class="text-white/60 text-sm font-light leading-relaxed mb-5"><?php echo esc_html($demie_pkg['description']); ?></p>
            <?php endif; ?>
            <?php if ($demie_pkg['inclusions']) : ?>
              <ul class="text-xs text-white/70 space-y-2 font-light mb-7">
                <?php foreach ($demie_pkg['inclusions'] as $demie_inc) : ?>
                  <li class="flex items-start gap-2">
                    <span class="text-brand-gold">•</span>
                    <span><?php echo esc_html(sprintf('%d× %s', $demie_inc['quantity'], $demie_inc['name'])); ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <a class="mt-auto inline-flex items-center justify-center px-6 py-3 border border-brand-champagne/60 text-brand-champagne hover:bg-brand-champagne hover:text-brand-charcoal text-[11px] uppercase tracking-luxury font-medium transition-colors" href="<?php echo esc_url(demie_page_url('booking')); ?>">
              <?php esc_html_e('Book this package', 'demie-v2'); ?>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</section>
<?php get_template_part('template-parts/booking'); ?>
<?php get_template_part('template-parts/instagram'); ?>
<?php get_footer(); ?>
