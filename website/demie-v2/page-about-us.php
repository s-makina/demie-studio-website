<?php
/**
 * Template Name: About (V2)
 * V2 editorial about page: titlebar, content, pillars, testimonials, booking.
 */
get_header();
the_post();
get_template_part('template-parts/page-titlebar', null, ['title' => get_the_title() ?: __('Our Ethos', 'demie-v2'), 'subtitle' => __('The Demie Philosophy', 'demie-v2')]);
?>
<section class="py-20 md:py-28 px-6 md:px-14 bg-brand-cream">
  <div class="max-w-3xl mx-auto text-center demie-v2-prose">
    <?php if (has_post_thumbnail()) : ?>
      <div class="mb-10 overflow-hidden shadow-xl"><?php the_post_thumbnail('large', ['class' => 'w-full h-auto']); ?></div>
    <?php endif; ?>
    <?php the_content(); ?>
  </div>
</section>
<?php
$faqs = function_exists('demie_get_faqs') ? demie_get_faqs() : [];
if ($faqs) : ?>
<section class="py-20 px-6 md:px-14 bg-brand-bone border-y border-brand-stone/50">
  <div class="max-w-3xl mx-auto">
    <div class="text-center mb-12">
      <span class="text-xs uppercase tracking-widest2 text-brand-gold font-sans font-medium mb-3 block"><?php esc_html_e('Questions', 'demie-v2'); ?></span>
      <h2 class="font-serif text-3xl sm:text-4xl text-brand-charcoal"><?php esc_html_e('Frequently Asked', 'demie-v2'); ?></h2>
    </div>
    <div class="space-y-4">
      <?php foreach ($faqs as $faq) : ?>
      <details class="group bg-brand-cream border border-brand-stone/70 p-6">
        <summary class="font-serif text-xl text-brand-charcoal cursor-pointer list-none flex justify-between items-center"><?php echo esc_html(get_the_title($faq)); ?><span class="text-brand-gold group-open:rotate-45 transition-transform">+</span></summary>
        <div class="text-brand-muted text-sm font-light leading-relaxed mt-3"><?php echo wp_kses_post(get_post_field('post_content', $faq)); ?></div>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php get_template_part('template-parts/why'); ?>
<?php get_template_part('template-parts/testimonials'); ?>
<?php get_template_part('template-parts/booking'); ?>
<?php get_footer(); ?>
