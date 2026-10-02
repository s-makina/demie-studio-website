<?php
// Hero: full-screen video. Poster falls back to first slide image, then Customizer default.
$slides = function_exists('demie_get_slides') ? demie_get_slides() : [];
$slide_poster = '';
if ($slides) {
    $slide_poster = get_the_post_thumbnail_url($slides[0], 'full') ?: '';
}
$poster = $slide_poster ?: demie_v2_get('demie_v2_hero_poster', DEMIE_URI . '/assets/video/hero-poster.jpg');
// Customizer URL wins; otherwise the bundled hero loop shipped with the theme.
$video  = demie_v2_get('demie_v2_hero_video', DEMIE_URI . '/assets/video/hero.mp4');
?>
<section class="relative w-full h-screen min-h-[640px] flex items-center justify-center overflow-hidden bg-brand-deep" id="home">
  <video autoplay muted loop playsinline preload="metadata" disablepictureinpicture aria-hidden="true" id="heroVideo" poster="<?php echo esc_url($poster); ?>" class="absolute inset-0 w-full h-full object-cover object-center scale-105">
    <source src="<?php echo esc_url($video); ?>" type="video/mp4">
  </video>
  <div class="hero-overlay absolute inset-0 z-10"></div>
  <div class="absolute inset-0 z-10 bg-gradient-to-t from-brand-charcoal via-transparent to-black/40"></div>
  <!-- Kimono style2 inset frame + ring -->
  <div class="hero-frame z-10" aria-hidden="true" style="background: rgba(255,255,255,0.02);">
    <img class="hero-ring" src="<?php echo esc_url(DEMIE_URI . '/assets/img/layer-3.png'); ?>" alt="">
  </div>
  <div class="relative z-20 max-w-5xl mx-auto px-6 text-center text-white flex flex-col items-center mt-12">
    <div class="flex items-center space-x-3 mb-6 opacity-90">
      <span class="w-8 h-[1px] bg-brand-champagne"></span>
      <span class="text-[10px] md:text-xs uppercase tracking-widest2 font-sans text-brand-champagne font-medium"><?php echo esc_html(demie_v2_get('demie_v2_hero_eyebrow', 'Fine Art Wedding Photography & Cinematography')); ?></span>
      <span class="w-8 h-[1px] bg-brand-champagne"></span>
    </div>
    <h1 class="font-serif text-4xl sm:text-6xl md:text-7xl lg:text-8xl font-normal leading-[1.08] tracking-tight mb-6 max-w-4xl text-brand-cream drop-shadow-sm">
      <?php echo esc_html(demie_v2_get('demie_v2_hero_title_a', 'Your Story,')); ?><br class="hidden sm:inline">
      <span class="italic font-light text-brand-champagne"><?php echo esc_html(demie_v2_get('demie_v2_hero_title_b', 'Beautifully Remembered.')); ?></span>
    </h1>
    <p class="max-w-xl mx-auto text-sm sm:text-base md:text-lg text-white/85 font-light font-sans leading-relaxed mb-10 tracking-wide">
      <?php echo esc_html(demie_v2_get('demie_v2_hero_sub', 'Capturing intimate celebrations, quiet romance, and timeless grandeur worldwide with an elevated editorial eye and pure emotional honesty.')); ?>
    </p>
    <div class="flex flex-col sm:flex-row items-center justify-center gap-4 sm:gap-6 w-full max-w-md mb-20">
      <a class="btn-luxury w-full sm:w-auto px-8 py-4 bg-brand-champagne text-brand-charcoal hover:bg-white text-xs uppercase tracking-luxury font-medium transition-all shadow-lg text-center" href="<?php echo esc_url(demie_page_url('contact')); ?>">
        <?php esc_html_e('Start Your Booking', 'demie-v2'); ?>
      </a>
      <a class="w-full sm:w-auto px-8 py-4 bg-transparent border border-white/50 text-white hover:bg-white/10 hover:border-white text-xs uppercase tracking-luxury font-medium transition-all text-center" href="#gallery">
        <?php esc_html_e('Explore Our Work', 'demie-v2'); ?>
      </a>
    </div>
  </div>
  <div class="absolute bottom-8 left-1/2 -translate-x-1/2 z-20 flex flex-col items-center cursor-pointer text-white/70 hover:text-brand-champagne transition-colors" onclick="document.getElementById('statement').scrollIntoView({behavior: 'smooth'})">
    <span class="text-[9px] uppercase tracking-widest2 font-sans mb-2"><?php esc_html_e('Scroll To Discover', 'demie-v2'); ?></span>
    <div class="w-[1px] h-8 bg-white/30 relative overflow-hidden">
      <div class="w-full h-1/2 bg-brand-champagne absolute top-0 animate-pulse"></div>
    </div>
  </div>
</section>
