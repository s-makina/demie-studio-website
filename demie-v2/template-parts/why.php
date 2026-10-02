<section class="py-24 md:py-32 px-6 md:px-14 bg-brand-bone border-y border-brand-stone/50">
  <div class="max-w-7xl mx-auto">
    <div class="max-w-2xl mb-16 reveal-on-scroll">
      <span class="text-xs uppercase tracking-widest2 text-brand-gold font-sans font-medium mb-3 block"><?php esc_html_e('The Demie Standard', 'demie-v2'); ?></span>
      <h2 class="font-serif text-3xl sm:text-4xl md:text-5xl text-brand-charcoal font-normal"><?php esc_html_e('Why Couples Entrust Us With Their Days', 'demie-v2'); ?></h2>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-10">
      <?php
      $pillars = [
          [__('Authentic Moments', 'demie-v2'), __('We capture unforced, unchoreographed emotions. We step back to let your moments breathe, preserving the true essence of your relationships.', 'demie-v2')],
          [__('Cinematic Storytelling', 'demie-v2'), __('Every wedding is documented as a unique cinematic narrative with deliberate pacing, musical scoring, and intentional composition.', 'demie-v2')],
          [__('Personal Experience', 'demie-v2'), __('From our first consultation to your private anniversary album reveal, we dedicate ourselves to a limited number of weddings per year.', 'demie-v2')],
          [__('Timeless Work', 'demie-v2'), __('We resist passing color trends and heavy filters. Our color science yields natural skin tones and enduring film tones built for generations.', 'demie-v2')],
      ];
      foreach ($pillars as $i => [$title, $text]) :
      ?>
      <div class="p-8 bg-brand-cream border border-brand-stone/70 reveal-on-scroll flex flex-col justify-between">
        <div>
          <span class="font-serif text-3xl text-brand-gold font-light mb-4 block"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></span>
          <h3 class="font-serif text-xl text-brand-charcoal font-normal mb-3"><?php echo esc_html($title); ?></h3>
          <p class="text-brand-muted text-xs sm:text-sm font-light leading-relaxed"><?php echo esc_html($text); ?></p>
        </div>
        <div class="w-8 h-[1px] bg-brand-gold/60 mt-8"></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
