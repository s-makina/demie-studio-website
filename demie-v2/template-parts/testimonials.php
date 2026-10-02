<?php
$testimonials = function_exists('demie_get_testimonials') ? demie_get_testimonials() : [];
$first = $testimonials ? $testimonials[0] : null;
$quote = $first ? (get_post_meta($first->ID, '_demie_quote', true) ?: get_the_title($first)) : __('They didn’t just photograph our wedding. They captured how it felt — the nervous excitement, the tears during my father’s speech, and the wild joy of our final dance.', 'demie-v2');
$author = $first ? get_the_title($first) : __('Sarah & Michael', 'demie-v2');
$location = $first ? get_post_meta($first->ID, '_demie_location', true) : '';
if (!$location) $location = __('Blantyre • Autumn 2024', 'demie-v2');
$count = max(1, count($testimonials));
?>
<section class="py-24 md:py-36 px-6 md:px-14 bg-brand-deep relative overflow-hidden" id="testimonials">
  <div class="absolute -right-20 top-1/2 -translate-y-1/2 font-serif text-[180px] lg:text-[260px] text-white/5 select-none pointer-events-none font-light italic">Love</div>
  <div class="max-w-4xl mx-auto text-center relative z-10 reveal-on-scroll">
    <div class="flex justify-center space-x-1 mb-6 text-brand-gold"><span>★</span><span>★</span><span>★</span><span>★</span><span>★</span></div>
    <blockquote class="font-serif text-3xl sm:text-4xl md:text-5xl text-brand-cream font-normal leading-[1.25] mb-8">“<?php echo esc_html(trim($quote, "“”\"' ")); ?>”</blockquote>
    <div class="flex flex-col items-center justify-center">
      <span class="font-serif text-xl sm:text-2xl text-brand-cream font-medium">— <?php echo esc_html($author); ?></span>
      <span class="text-xs uppercase tracking-widest2 text-white/50 font-sans mt-1"><?php echo esc_html($location); ?></span>
    </div>
    <?php if ($count > 1) : ?>
    <div class="mt-12 flex justify-center items-center space-x-3">
      <?php for ($i = 0; $i < min($count, 5); $i++) : ?>
      <span class="rounded-full transition-all <?php echo 0 === $i ? 'w-2.5 h-2.5 bg-brand-champagne' : 'w-2 h-2 bg-white/25'; ?>"></span>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
