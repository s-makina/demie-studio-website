<?php
/**
 * Template Name: Gallery
 * V2: thin wrapper over the Demie Gallery plugin (latest gallery), with
 * portfolio-item fallback and v2 editorial card styling.
 */
get_header();
get_template_part('template-parts/page-titlebar', null, ['title' => get_the_title() ?: __('Gallery', 'demie-v2'), 'subtitle' => __('Curated Archives', 'demie-v2')]);
?>
<section class="py-20 md:py-28 px-6 md:px-14 bg-brand-bone">
  <div class="max-w-7xl mx-auto demie-v2-prose">
    <?php if (shortcode_exists('demie_gallery')) : ?>
      <?php echo do_shortcode('[demie_gallery layout="masonry" pagination="load_more" per_page="24"]'); ?>
    <?php else :
      $items = function_exists('demie_get_portfolio') ? demie_get_portfolio(12) : [];
      if ($items) : ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
          <?php foreach ($items as $item) :
            $src = function_exists('demie_portfolio_img') ? demie_portfolio_img($item, 'large') : '';
            if (!$src) continue; ?>
            <div class="editorial-card group relative overflow-hidden bg-brand-stone shadow-lg">
              <div class="aspect-[4/5] overflow-hidden bg-brand-charcoal">
                <img src="<?php echo esc_url($src); ?>" alt="<?php echo esc_attr(get_the_title($item)); ?>" class="w-full h-full object-cover editorial-zoom" loading="lazy">
              </div>
              <div class="p-5 bg-brand-cream border-t border-brand-stone/60">
                <h4 class="font-serif text-xl text-brand-charcoal"><?php echo esc_html(get_the_title($item)); ?></h4>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else : ?>
        <p class="text-center text-brand-muted font-light"><?php esc_html_e('Portfolio items are being prepared. Please check back soon.', 'demie-v2'); ?></p>
      <?php endif;
    endif; ?>
    <?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
  </div>
</section>
<?php get_template_part('template-parts/booking'); ?>
<?php get_footer(); ?>
