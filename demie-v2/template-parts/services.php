<section class="py-24 md:py-36 px-6 md:px-14 bg-brand-cream" id="services">
  <div class="max-w-7xl mx-auto">
    <div class="text-center max-w-3xl mx-auto mb-16 reveal-on-scroll">
      <span class="text-xs uppercase tracking-widest2 text-brand-gold font-sans font-medium mb-3 block"><?php esc_html_e('Our Disciplines', 'demie-v2'); ?></span>
      <h2 class="font-serif text-3xl sm:text-5xl text-brand-charcoal font-normal tracking-tight mb-4"><?php esc_html_e('Photography & Cinematic Films', 'demie-v2'); ?></h2>
      <p class="text-brand-muted text-sm md:text-base font-light">
        <?php esc_html_e('Two complementary art forms, executed in quiet harmony by a synchronized team of directors, photographers, and audio recordists.', 'demie-v2'); ?>
      </p>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
      <div class="group relative overflow-hidden bg-brand-charcoal min-h-[520px] flex flex-col justify-end p-8 sm:p-12 reveal-on-scroll">
        <img alt="<?php esc_attr_e('Wedding Photography Discipline', 'demie-v2'); ?>" class="absolute inset-0 w-full h-full object-cover object-center opacity-65 group-hover:scale-105 group-hover:opacity-75 transition-all duration-1000 ease-out" loading="lazy" src="https://images.unsplash.com/photo-1537633552985-df8429e8048b?auto=format&fit=crop&w=1400&q=80">
        <div class="absolute inset-0 bg-gradient-to-t from-brand-charcoal via-brand-charcoal/40 to-transparent"></div>
        <div class="relative z-10 text-white">
          <span class="text-xs uppercase tracking-widest2 text-brand-champagne font-sans font-medium mb-2 block"><?php esc_html_e('01 / Fine Art Stills', 'demie-v2'); ?></span>
          <h3 class="font-serif text-3xl sm:text-4xl font-normal mb-3"><?php esc_html_e('Wedding Photography', 'demie-v2'); ?></h3>
          <p class="text-white/80 text-sm font-light leading-relaxed mb-6 max-w-md">
            <?php esc_html_e('Authentic, emotional, and timeless imagery. We prioritize natural light, nuanced expressions, and the organic flow of your celebration over forced poses.', 'demie-v2'); ?>
          </p>
          <ul class="text-xs text-white/70 space-y-2 mb-8 font-light tracking-wide">
            <li class="flex items-center space-x-2"><span class="text-brand-gold">•</span><span><?php esc_html_e('Medium format digital & 35mm film cameras', 'demie-v2'); ?></span></li>
            <li class="flex items-center space-x-2"><span class="text-brand-gold">•</span><span><?php esc_html_e('Online private archive & high-res print rights', 'demie-v2'); ?></span></li>
            <li class="flex items-center space-x-2"><span class="text-brand-gold">•</span><span><?php esc_html_e('Custom handcrafted Italian linen albums', 'demie-v2'); ?></span></li>
          </ul>
          <a class="inline-flex items-center space-x-2 text-xs uppercase tracking-luxury font-medium text-brand-champagne hover:text-white transition-colors pb-1 border-b border-brand-champagne hover:border-white" href="<?php echo esc_url(demie_page_url('services')); ?>">
            <span><?php esc_html_e('Explore Photography Collections', 'demie-v2'); ?></span>
            <span>→</span>
          </a>
        </div>
      </div>
      <div class="group relative overflow-hidden bg-brand-charcoal min-h-[520px] flex flex-col justify-end p-8 sm:p-12 reveal-on-scroll">
        <img alt="<?php esc_attr_e('Wedding Cinematography Discipline', 'demie-v2'); ?>" class="absolute inset-0 w-full h-full object-cover object-center opacity-65 group-hover:scale-105 group-hover:opacity-75 transition-all duration-1000 ease-out" loading="lazy" src="https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?auto=format&fit=crop&w=1400&q=80">
        <div class="absolute inset-0 bg-gradient-to-t from-brand-charcoal via-brand-charcoal/40 to-transparent"></div>
        <div class="relative z-10 text-white">
          <span class="text-xs uppercase tracking-widest2 text-brand-champagne font-sans font-medium mb-2 block"><?php esc_html_e('02 / Motion & Sound', 'demie-v2'); ?></span>
          <h3 class="font-serif text-3xl sm:text-4xl font-normal mb-3"><?php esc_html_e('Wedding Films', 'demie-v2'); ?></h3>
          <p class="text-white/80 text-sm font-light leading-relaxed mb-6 max-w-md">
            <?php esc_html_e('Cinematic films designed to bring the atmosphere, raw voices, and heart-swelling cadence of your vows back to vivid life for decades to come.', 'demie-v2'); ?>
          </p>
          <ul class="text-xs text-white/70 space-y-2 mb-8 font-light tracking-wide">
            <li class="flex items-center space-x-2"><span class="text-brand-gold">•</span><span><?php esc_html_e('4K cinema cameras & Super 8 vintage film reels', 'demie-v2'); ?></span></li>
            <li class="flex items-center space-x-2"><span class="text-brand-gold">•</span><span><?php esc_html_e('Immersive high-fidelity audio recording & scoring', 'demie-v2'); ?></span></li>
            <li class="flex items-center space-x-2"><span class="text-brand-gold">•</span><span><?php esc_html_e('Cinematic highlight trailers & full ceremony cuts', 'demie-v2'); ?></span></li>
          </ul>
          <a class="inline-flex items-center space-x-2 text-xs uppercase tracking-luxury font-medium text-brand-champagne hover:text-white transition-colors pb-1 border-b border-brand-champagne hover:border-white" href="<?php echo esc_url(demie_page_url('services')); ?>">
            <span><?php esc_html_e('Explore Cinema Packages', 'demie-v2'); ?></span>
            <span>→</span>
          </a>
        </div>
      </div>
    </div>

    <?php $services = function_exists('demie_get_services') ? array_slice(demie_get_services(), 0, 4) : []; ?>
    <?php if ($services) : ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-12">
      <?php foreach ($services as $svc) : ?>
      <a href="<?php echo esc_url(demie_page_url('services')); ?>" class="reveal-on-scroll block p-7 bg-brand-bone border border-brand-stone/60 hover:border-brand-gold transition-colors group">
        <h4 class="font-serif text-xl text-brand-charcoal group-hover:text-brand-gold transition-colors mb-2"><?php echo esc_html(get_the_title($svc)); ?></h4>
        <p class="text-brand-muted text-xs font-light leading-relaxed"><?php echo esc_html(function_exists('demie_service_short') ? demie_service_short($svc) : ''); ?></p>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
