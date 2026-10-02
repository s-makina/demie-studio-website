<?php
// Featured story: latest post, else latest portfolio image, else placeholder.
$story_img = 'https://images.unsplash.com/photo-1606216794074-735e91aa2c92?auto=format&fit=crop&w=1400&q=80';
$story_title = __('A Love Story in Blantyre', 'demie-v2');
$story_meta  = __('Victoria & Alexander • The Estate', 'demie-v2');
$recent = get_posts(['posts_per_page' => 1, 'post_status' => 'publish']);
if ($recent && has_post_thumbnail($recent[0])) {
    $story_img = get_the_post_thumbnail_url($recent[0], 'large');
} elseif (function_exists('demie_get_portfolio')) {
    $pf = demie_get_portfolio(1);
    if ($pf && ($u = demie_portfolio_img($pf[0], 'large'))) $story_img = $u;
}
?>
<section class="py-24 md:py-36 px-6 md:px-14 bg-brand-charcoal text-brand-cream relative overflow-hidden" id="featured-story">
  <div class="max-w-7xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-center">
      <div class="lg:col-span-7 relative reveal-on-scroll">
        <div class="relative overflow-hidden shadow-2xl bg-brand-deep">
          <img alt="<?php echo esc_attr($story_title); ?>" class="w-full h-auto max-h-[700px] object-cover object-top" loading="lazy" src="<?php echo esc_url($story_img); ?>">
        </div>
        <div class="absolute -bottom-6 -right-4 sm:-right-8 bg-brand-deep text-brand-cream p-5 sm:p-7 shadow-xl border border-white/15 max-w-[240px] hidden sm:block">
          <span class="text-[9px] uppercase tracking-widest2 text-brand-gold font-sans block mb-1"><?php esc_html_e('Issue No. 14', 'demie-v2'); ?></span>
          <p class="font-serif text-lg leading-tight font-medium"><?php esc_html_e('35mm & 16mm Film Archive', 'demie-v2'); ?></p>
          <p class="text-[10px] text-white/60 mt-2 tracking-wide font-sans"><?php esc_html_e('Full cinematic coverage & bespoke leather album.', 'demie-v2'); ?></p>
        </div>
      </div>
      <div class="lg:col-span-5 reveal-on-scroll flex flex-col justify-center">
        <div class="flex items-center space-x-3 mb-4">
          <span class="w-6 h-[1px] bg-brand-gold"></span>
          <span class="text-xs uppercase tracking-widest2 text-brand-champagne font-sans"><?php esc_html_e('Featured Love Story', 'demie-v2'); ?></span>
        </div>
        <h2 class="font-serif text-3xl sm:text-4xl md:text-5xl font-normal leading-tight text-white mb-3"><?php echo esc_html($story_title); ?></h2>
        <span class="text-sm font-serif italic text-brand-champagne mb-6 block"><?php echo esc_html($story_meta); ?></span>
        <p class="text-brand-stone/80 text-sm md:text-base font-light leading-relaxed mb-6 font-sans">
          <?php esc_html_e('Set against warm evening light and joyful company, this celebration unfolded over heartfelt vows, family embraces, and a dance floor that refused to empty. Our team documented every chapter — from quiet morning preparations to the final sparkler farewell.', 'demie-v2'); ?>
        </p>
        <p class="text-brand-stone/80 text-sm md:text-base font-light leading-relaxed mb-8 font-sans">
          <?php esc_html_e('Shot across 35mm film stock and medium-format digital cameras, the resulting archive pairs nostalgic film texture with luminous modern clarity.', 'demie-v2'); ?>
        </p>
        <blockquote class="border-l-2 border-brand-gold pl-5 py-1 mb-8 italic font-serif text-lg text-white/95">
          <?php esc_html_e('“Looking back at our gallery felt like stepping right back into the perfume of the gardens and the sound of our friends cheering.”', 'demie-v2'); ?>
        </blockquote>
        <div>
          <a class="inline-flex items-center space-x-3 px-7 py-3.5 bg-brand-champagne text-brand-charcoal hover:bg-white text-xs uppercase tracking-luxury font-medium transition-colors" href="<?php echo esc_url(demie_page_url('gallery')); ?>">
            <span><?php esc_html_e('View Full Story', 'demie-v2'); ?></span>
            <span>→</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>
