<section class="py-24 md:py-36 px-6 md:px-14 bg-brand-deep text-white relative overflow-hidden" id="experience">
  <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 relative z-10">
    <div class="lg:col-span-7">
      <div class="flex items-center space-x-3 mb-4">
        <span class="w-8 h-[1px] bg-brand-gold"></span>
        <span class="text-xs uppercase tracking-widest2 text-brand-champagne font-sans font-medium"><?php esc_html_e('The Demie Experience', 'demie-v2'); ?></span>
      </div>
      <h2 class="font-serif text-4xl sm:text-6xl md:text-7xl font-normal text-brand-cream mb-8 tracking-tight reveal-on-scroll">
        <?php esc_html_e('The Demie', 'demie-v2'); ?> <br>
        <em class="text-brand-champagne"><?php esc_html_e('Storytellers', 'demie-v2'); ?></em>
      </h2>
      <p class="max-w-xl text-base sm:text-lg text-white/80 font-light font-sans leading-relaxed mb-10 reveal-on-scroll">
        <?php esc_html_e('The talent at Demie Photography runs wide and deep. From weddings and intimate portraits to events and drone work, our team is made of some of the finest image-makers in Malawi — capturing beautiful memories across the country and beyond.', 'demie-v2'); ?>
      </p>
      <p class="reveal-on-scroll">
        <span class="font-serif text-5xl text-brand-gold font-light"><?php esc_html_e('15+', 'demie-v2'); ?></span>
        <span class="block text-xs uppercase tracking-widest text-brand-champagne/80 mt-2 font-sans"><?php esc_html_e('Years Experience', 'demie-v2'); ?></span>
      </p>
    </div>

    <div class="lg:col-span-5 flex flex-col justify-center space-y-10 lg:border-l lg:border-white/10 lg:pl-16">
      <?php
      $stats = [
          ['300+', __('Weddings & Events Captured', 'demie-v2')],
          ['10+',  __('Years Behind the Lens', 'demie-v2')],
          ['25+',  __('Destinations Worldwide', 'demie-v2')],
      ];
      foreach ($stats as [$number, $label]) :
      ?>
      <div class="reveal-on-scroll">
        <div class="font-serif text-5xl md:text-6xl text-brand-cream font-light"><?php echo esc_html($number); ?></div>
        <div class="text-xs uppercase tracking-widest text-brand-champagne/80 mt-2 font-sans"><?php echo esc_html($label); ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
