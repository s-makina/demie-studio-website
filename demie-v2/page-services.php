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
  <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10">
    <?php if ($services) : foreach ($services as $i => $svc) :
      $icon = get_the_post_thumbnail_url($svc, 'medium');
    ?>
      <div class="reveal-on-scroll p-10 bg-brand-charcoal border border-white/10 flex gap-6">
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
</section>
<?php get_template_part('template-parts/booking'); ?>
<?php get_template_part('template-parts/instagram'); ?>
<?php get_footer(); ?>
