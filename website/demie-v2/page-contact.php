<?php
// Contact page: titlebar + studio details + v2 booking form section.
get_header();
the_post();
get_template_part('template-parts/page-titlebar', null, ['title' => get_the_title() ?: __('Contact', 'demie-v2'), 'subtitle' => __('Studio Direct', 'demie-v2')]);
?>
<section class="py-16 px-6 md:px-14 bg-brand-cream">
  <div class="max-w-5xl mx-auto demie-v2-prose text-center"><?php the_content(); ?></div>
  <div class="max-w-5xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-6 mt-10 text-center">
    <div class="p-8 bg-brand-bone border border-brand-stone/60">
      <span class="block text-[10px] uppercase tracking-widest text-brand-gold mb-2"><?php esc_html_e('General Inquiries', 'demie-v2'); ?></span>
      <a class="font-serif text-xl text-brand-charcoal hover:text-brand-gold" href="mailto:<?php echo esc_attr(function_exists('demie_email') ? demie_email() : 'demiestudios@gmail.com'); ?>"><?php echo esc_html(function_exists('demie_email') ? demie_email() : 'demiestudios@gmail.com'); ?></a>
    </div>
    <div class="p-8 bg-brand-bone border border-brand-stone/60">
      <span class="block text-[10px] uppercase tracking-widest text-brand-gold mb-2"><?php esc_html_e('Client Line', 'demie-v2'); ?></span>
      <a class="font-serif text-xl text-brand-charcoal hover:text-brand-gold" href="<?php echo esc_url(function_exists('demie_phone_url') ? demie_phone_url() : 'tel:+265884444802'); ?>"><?php echo esc_html(function_exists('demie_phone') ? demie_phone() : '+265 884 44 48 02'); ?></a>
    </div>
    <div class="p-8 bg-brand-bone border border-brand-stone/60">
      <span class="block text-[10px] uppercase tracking-widest text-brand-gold mb-2"><?php esc_html_e('Main Studio', 'demie-v2'); ?></span>
      <span class="font-serif text-xl text-brand-charcoal"><?php echo esc_html(function_exists('demie_location') ? demie_location() : 'Chilomoni, Blantyre, Malawi'); ?></span>
    </div>
  </div>
</section>
<?php get_template_part('template-parts/booking'); ?>
<?php get_footer(); ?>
