<?php
/**
 * Template Name: Gallery
 * V2: renders the latest plugin Gallery in the v2 masonry-blur skin.
 * Source chain: latest Gallery -> Portfolio Items -> empty notice.
 * (The plugin shortcode is intentionally not used here — its Kimono markup
 * needs Kimono CSS, which the v2 theme does not load.)
 */
get_header();
get_template_part('template-parts/page-titlebar', null, ['title' => get_the_title() ?: __('Gallery', 'demie-v2'), 'subtitle' => __('Curated Archives', 'demie-v2')]);
$cards = function_exists('demie_v2_gallery_cards') ? demie_v2_gallery_cards(0, '') : [];
?>
<section class="py-20 md:py-28 px-6 md:px-14 bg-brand-bone">
  <div class="max-w-7xl mx-auto demie-v2-prose">
    <?php if ($cards) : ?>
      <div class="columns-1 sm:columns-2 lg:columns-3 gap-8">
        <?php foreach ($cards as $card) : ?>
          <div class="masonry-card group relative block overflow-hidden bg-brand-charcoal mb-8 break-inside-avoid shadow-lg">
            <img src="<?php echo esc_url($card['img']); ?>" alt="<?php echo esc_attr($card['title']); ?>" class="w-full h-auto" loading="lazy">
            <div class="absolute inset-0 flex items-center justify-center text-center p-8">
              <div class="masonry-meta">
                <?php if ('video' === ($card['kind'] ?? 'photo')) : ?>
                <span class="inline-block text-[10px] uppercase tracking-widest2 text-brand-charcoal bg-brand-champagne px-3 py-1 mb-3">▶ <?php esc_html_e('Film', 'demie-v2'); ?></span>
                <?php endif; ?>
                <h4 class="font-serif text-2xl font-light tracking-wide text-white"><?php echo esc_html($card['title']); ?></h4>
                <p class="text-[11px] uppercase tracking-widest2 text-brand-champagne mt-2"><?php echo esc_html($card['loc']); ?></p>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else : ?>
      <p class="text-center text-brand-muted font-light"><?php esc_html_e('Gallery items are being prepared. Please check back soon.', 'demie-v2'); ?></p>
    <?php endif; ?>
    <?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
  </div>
</section>
<?php get_template_part('template-parts/booking'); ?>
<?php get_footer(); ?>
