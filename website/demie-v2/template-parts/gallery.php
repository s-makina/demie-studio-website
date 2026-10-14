<?php
// Gallery: masonry-2 design — 3-column masonry grid with blur-reveal hover.
// Cards pull from the Demie Gallery plugin (`homepage` gallery, else latest);
// Portfolio Items cover a missing/empty plugin; curated archives are last resort.
$fallback = [
    ['title' => 'Clara & Julian',    'loc' => 'Tuscany Estate • Golden Hour',      'img' => 'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=800&h=1000&q=80'],
    ['title' => 'Elena & Arthur',    'loc' => 'Villa Balbianello • Sunset Cruise', 'img' => 'https://images.unsplash.com/photo-1511285560929-80b456fea0bc?auto=format&fit=crop&w=800&h=600&q=80'],
    ['title' => 'Heirloom Details',  'loc' => 'Macro Curations • Bespoke Ribbons', 'img' => 'https://images.unsplash.com/photo-1522673607200-164d1b6ce486?auto=format&fit=crop&w=800&h=800&q=80'],
    ['title' => 'Mei & Soren',       'loc' => 'Bamboo Grove • Morning Mist',       'img' => 'https://images.unsplash.com/photo-1520854221256-17451cc331bf?auto=format&fit=crop&w=800&h=1100&q=80'],
    ['title' => 'Charlotte & James', 'loc' => 'Mayfair Ballroom • Black Tie',      'img' => 'https://images.unsplash.com/photo-1465495976277-4387d4b0b4c6?auto=format&fit=crop&w=800&h=650&q=80'],
    ['title' => 'Camille & Hugo',    'loc' => 'Château Banquet • Fairy Lights',    'img' => 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=800&h=950&q=80'],
    ['title' => 'Fiona & Liam',      'loc' => 'Misty Highlands • Film Archive',    'img' => 'https://images.unsplash.com/photo-1507504031003-b417219a0fde?auto=format&fit=crop&w=800&h=620&q=80'],
    ['title' => 'Amara & Daniel',    'loc' => 'Lakeside Vows • Golden Light',      'img' => 'https://images.unsplash.com/photo-1583939003579-730e3918a45a?auto=format&fit=crop&w=800&h=1050&q=80'],
    ['title' => 'Sofia & Matteo',    'loc' => 'Vineyard Terrace • Dusk',           'img' => 'https://images.unsplash.com/photo-1537633552985-df8429e8048b?auto=format&fit=crop&w=800&h=700&q=80'],
];
$items = function_exists('demie_v2_gallery_cards') ? demie_v2_gallery_cards(9, 'homepage') : [];
$cards = [];
foreach ($fallback as $i => $fb) {
    if (isset($items[$i])) {
        $cards[] = $items[$i];
    } else {
        $cards[] = $fb + ['kind' => 'photo'];
    }
}
$gallery_url = demie_page_url('gallery');
?>
<section class="py-20 md:py-32 px-6 md:px-14 bg-brand-charcoal border-t border-white/10" id="gallery">
  <div class="max-w-7xl mx-auto">
    <div class="text-center mb-14 reveal-on-scroll">
      <div class="flex items-center justify-center space-x-2 mb-3">
        <span class="w-5 h-[1px] bg-brand-gold"></span>
        <span class="text-xs uppercase tracking-widest2 text-brand-gold font-sans font-medium"><?php esc_html_e('Curated Archives', 'demie-v2'); ?></span>
        <span class="w-5 h-[1px] bg-brand-gold"></span>
      </div>
      <h2 class="font-serif text-3xl sm:text-5xl lg:text-6xl text-brand-cream font-normal tracking-tight"><?php esc_html_e('Selected Wedding Portfolios', 'demie-v2'); ?></h2>
      <p class="text-white/60 text-sm max-w-xl mx-auto mt-4 font-light leading-relaxed">
        <?php esc_html_e('A glimpse into quiet destination vows, lakeside estates, and timeless evening celebrations documented across Malawi and beyond.', 'demie-v2'); ?>
      </p>
    </div>

    <div class="swiper-container swiper-gallery-two has-radius">
      <div class="swiper-wrapper">
        <?php foreach ($cards as $card) : ?>
        <div class="swiper-slide">
          <div class="grid-item group relative block overflow-hidden">
            <div class="wptb-item--inner relative">
              <div class="wptb-item--image">
                <img alt="<?php echo esc_attr($card['title']); ?>" class="w-full h-[260px] md:h-[340px] object-cover" loading="lazy" src="<?php echo esc_url($card['img']); ?>">
                <a class="wptb-item--link" href="<?php echo esc_url($gallery_url); ?>" aria-label="<?php echo esc_attr($card['title']); ?>"><i class="bi bi-chevron-right"></i></a>
              </div>
              <div class="wptb-item--holder">
                <div class="wptb-item--meta">
                  <h4><a href="<?php echo esc_url($gallery_url); ?>"><?php echo esc_html($card['title']); ?></a></h4>
                  <p><?php echo esc_html($card['loc']); ?></p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="wptb-swiper-navigation style2">
        <div class="wptb-swiper-arrow swiper-button-prev"></div>
        <div class="wptb-swiper-arrow swiper-button-next"></div>
      </div>
    </div>

    <div class="mt-16 text-center reveal-on-scroll">
      <a class="inline-flex items-center space-x-3 px-9 py-4 border border-white/40 text-white text-xs uppercase tracking-luxury font-medium hover:bg-white hover:text-brand-charcoal transition-all shadow-sm" href="<?php echo esc_url($gallery_url); ?>">
        <span><?php esc_html_e('View Complete Portfolio Gallery', 'demie-v2'); ?></span>
        <span>→</span>
      </a>
    </div>
  </div>
</section>
