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
        <div class="columns-1 sm:columns-2 lg:columns-3 gap-8">
          <?php foreach ($items as $item) :
            $src = function_exists('demie_portfolio_img') ? demie_portfolio_img($item, 'large') : '';
            if (!$src) continue; ?>
            <a href="<?php echo esc_url($src); ?>" target="_blank" rel="noopener" class="masonry-card group relative block overflow-hidden bg-brand-charcoal mb-8 break-inside-avoid shadow-lg">
              <img src="<?php echo esc_url($src); ?>" alt="<?php echo esc_attr(get_the_title($item)); ?>" class="w-full h-auto" loading="lazy">
              <div class="absolute inset-0 flex items-center justify-center text-center p-8">
                <div class="masonry-meta">
                  <h4 class="font-serif text-2xl font-light tracking-wide text-white"><?php echo esc_html(get_the_title($item)); ?></h4>
                  <p class="text-[11px] uppercase tracking-widest2 text-brand-champagne mt-2"><?php echo esc_html(get_the_date('', $item)); ?></p>
                </div>
              </div>
            </a>
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
