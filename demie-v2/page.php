<?php
// Generic page — v2 titlebar + centered prose + booking CTA.
get_header();
the_post();
get_template_part('template-parts/page-titlebar', null, ['title' => get_the_title()]);
?>
<section class="py-20 md:py-28 px-6 md:px-14 bg-brand-cream">
  <div class="max-w-3xl mx-auto demie-v2-prose">
    <?php if (has_post_thumbnail()) : ?>
      <div class="mb-10 overflow-hidden shadow-xl"><?php the_post_thumbnail('large', ['class' => 'w-full h-auto']); ?></div>
    <?php endif; ?>
    <?php the_content(); ?>
  </div>
</section>
<?php get_template_part('template-parts/booking'); ?>
<?php get_footer(); ?>
