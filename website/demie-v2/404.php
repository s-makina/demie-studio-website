<?php
get_header();
?>
<section class="min-h-screen flex items-center justify-center bg-brand-cream px-6 pt-32 pb-20 text-center">
  <div class="max-w-xl">
    <span class="font-serif italic text-7xl text-brand-gold">404</span>
    <h1 class="font-serif text-4xl text-brand-charcoal mt-4 mb-4"><?php esc_html_e('This page wandered off.', 'demie-v2'); ?></h1>
    <p class="text-brand-muted font-light mb-8"><?php esc_html_e('The story you are looking for has moved or never existed.', 'demie-v2'); ?></p>
    <a class="inline-flex items-center space-x-3 px-9 py-4 bg-brand-charcoal text-white text-xs uppercase tracking-luxury font-medium hover:bg-brand-gold transition-colors" href="<?php echo esc_url(home_url('/')); ?>">
      <span><?php esc_html_e('Back to Home', 'demie-v2'); ?></span><span>→</span>
    </a>
  </div>
</section>
<?php get_footer(); ?>
