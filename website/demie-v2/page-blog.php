<?php
/**
 * Template Name: Blog (V2)
 * Posts listing in v2 editorial card style (also the fallback for the posts page).
 */
get_header();
get_template_part('template-parts/page-titlebar', null, ['title' => get_the_title() ?: __('Journal', 'demie-v2'), 'subtitle' => __('Notes & Stories', 'demie-v2')]);
$query = have_posts() ? null : new WP_Query(['posts_per_page' => 9, 'ignore_sticky_posts' => true]);
?>
<section class="py-20 md:py-28 px-6 md:px-14 bg-brand-cream">
  <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-8">
    <?php
    $loop = $query ?: $GLOBALS['wp_query'];
    if ($loop->have_posts()) : while ($loop->have_posts()) : $loop->the_post(); ?>
      <a href="<?php the_permalink(); ?>" class="group bg-brand-bone border border-brand-stone/60 overflow-hidden shadow-sm hover:shadow-lg transition-shadow">
        <?php if (has_post_thumbnail()) : ?>
          <div class="aspect-[16/10] overflow-hidden bg-brand-stone"><?php the_post_thumbnail('medium_large', ['class' => 'w-full h-full object-cover editorial-zoom']); ?></div>
        <?php endif; ?>
        <div class="p-6">
          <span class="text-[10px] uppercase tracking-widest2 text-brand-gold"><?php echo esc_html(get_the_date()); ?></span>
          <h2 class="font-serif text-2xl text-brand-charcoal group-hover:text-brand-gold transition-colors mt-1"><?php the_title(); ?></h2>
          <p class="text-sm text-brand-muted font-light mt-2"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 20)); ?></p>
        </div>
      </a>
    <?php endwhile; wp_reset_postdata(); else : ?>
      <p class="text-brand-muted font-light md:col-span-3 text-center"><?php esc_html_e('Stories are on their way. Please check back soon.', 'demie-v2'); ?></p>
    <?php endif; ?>
  </div>
</section>
<?php get_footer(); ?>
