<section class="text-white relative overflow-hidden bg-cover bg-center" id="experience" style="background-image: url('<?php echo esc_url(DEMIE_URI . '/assets/img/background/bg-13.jpg'); ?>');">
  <div class="absolute inset-0 bg-brand-deep/80 z-0"></div>
  <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 lg:items-stretch relative z-10">
    <div class="lg:col-span-5 py-24 md:py-36 px-6 lg:px-0 lg:pr-12">
      <div class="flex items-center space-x-3 mb-4">
        <span class="w-8 h-[1px] bg-brand-gold"></span>
        <span class="text-xs uppercase tracking-widest2 text-brand-champagne font-sans font-medium"><?php esc_html_e('The Demie Experience', 'demie-v2'); ?></span>
      </div>
      <h2 class="font-serif text-4xl sm:text-6xl md:text-7xl font-normal text-brand-cream mb-8 tracking-tight reveal-on-scroll">
        <?php esc_html_e('The Demie', 'demie-v2'); ?> <br>
        <span class="text-outline"><?php esc_html_e('Storytellers', 'demie-v2'); ?></span>
      </h2>
      <p class="max-w-xl text-base sm:text-lg text-white/80 font-light font-sans leading-relaxed mb-12 reveal-on-scroll">
        <?php esc_html_e('The talent at Demie Photography runs wide and deep. From weddings and intimate portraits to events and drone work, our team is made of some of the finest image-makers in Malawi — capturing beautiful memories across the country and beyond.', 'demie-v2'); ?>
      </p>
      <p class="flex items-center gap-4 text-xl md:text-2xl text-white font-sans font-medium reveal-on-scroll">
        <span class="w-[74px] h-[74px] flex items-center justify-center rounded-full bg-brand-gold text-white text-2xl font-serif shrink-0"><?php esc_html_e('15+', 'demie-v2'); ?></span>
        <?php esc_html_e('Years Experience', 'demie-v2'); ?>
      </p>
    </div>

    <div class="lg:col-span-4 reveal-on-scroll">
      <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/3.png'); ?>" alt="<?php esc_attr_e('Demie Photography', 'demie-v2'); ?>" class="w-full h-full object-cover object-center block">
    </div>

    <div class="lg:col-span-3 py-24 md:py-36 px-6 lg:px-0 lg:pl-12 flex flex-col justify-center space-y-10">
      <?php
      $stats = [
          ['300+', __('Weddings & Events Captured', 'demie-v2')],
          ['10+',  __('Years Behind the Lens', 'demie-v2')],
          ['25+',  __('Destinations Worldwide', 'demie-v2')],
      ];
      foreach ($stats as $stat) :
          list($value, $label) = $stat;
      ?>
      <div class="reveal-on-scroll border-b border-white/15 pb-8 last:border-b-0 last:pb-0">
        <div class="font-serif text-5xl md:text-6xl text-brand-cream font-light"><?php echo esc_html($value); ?></div>
        <div class="text-xs uppercase tracking-widest text-brand-champagne/80 mt-2 font-sans"><?php echo esc_html($label); ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
