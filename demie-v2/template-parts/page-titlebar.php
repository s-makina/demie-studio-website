<?php
// Shared inner-page titlebar for Demie v2.
$args = wp_parse_args($args ?? [], ['title' => get_the_title(), 'subtitle' => '']);
?>
<section class="relative bg-[#1E1C1A] text-white pt-40 pb-20 px-6 md:px-14 overflow-hidden">
  <div class="absolute inset-0 opacity-25">
    <img class="w-full h-full object-cover" alt="" src="https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=2000&q=80">
  </div>
  <div class="absolute inset-0 bg-gradient-to-t from-[#1E1C1A] via-[#1E1C1A]/70 to-black/40"></div>
  <div class="absolute inset-0 z-0 pointer-events-none select-none" aria-hidden="true">
    <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/slider/layer-4.png'); ?>" alt="" class="absolute top-0 left-0 w-[20%] max-w-[200px] h-auto opacity-50">
    <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/slider/layer-5.png'); ?>" alt="" class="absolute bottom-0 right-0 w-[20%] max-w-[200px] h-auto opacity-50">
  </div>
  <div class="relative max-w-5xl mx-auto text-center">
    <?php if (!empty($args['subtitle'])) : ?>
    <span class="text-xs uppercase tracking-widest2 text-brand-champagne font-sans font-medium mb-3 block"><?php echo esc_html($args['subtitle']); ?></span>
    <?php endif; ?>
    <h1 class="font-serif text-4xl sm:text-5xl md:text-6xl font-normal text-brand-cream"><?php echo esc_html($args['title']); ?></h1>
    <div class="w-16 h-[1px] bg-brand-gold/60 mx-auto mt-6"></div>
  </div>
</section>
