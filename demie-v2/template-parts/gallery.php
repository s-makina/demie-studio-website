<?php
// Editorial masonry grid. Portfolio Items drive the cards; v2 static archives are the fallback.
$layouts = [
    ['span' => 'md:col-span-7', 'aspect' => 'aspect-[16/10]', 'title_size' => 'text-3xl'],
    ['span' => 'md:col-span-5', 'aspect' => 'aspect-[3/4]',  'title_size' => 'text-3xl'],
    ['span' => 'md:col-span-4 md:-mt-8', 'aspect' => 'aspect-square', 'title_size' => 'text-2xl'],
    ['span' => 'md:col-span-4', 'aspect' => 'aspect-[4/5]',   'title_size' => 'text-2xl'],
    ['span' => 'md:col-span-4 md:mt-6', 'aspect' => 'aspect-[4/5]', 'title_size' => 'text-2xl'],
    ['span' => 'md:col-span-8', 'aspect' => 'aspect-[16/9]',  'title_size' => 'text-3xl'],
    ['span' => 'md:col-span-4', 'aspect' => 'aspect-[3/4]',   'title_size' => 'text-2xl'],
];
$fallback = [
    ['title' => 'Clara & Julian',    'loc' => 'Tuscany Estate • Golden Hour',        'tag' => 'Tuscany, Italy • 35mm & Medium Format', 'img' => 'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=1400&q=80'],
    ['title' => 'Elena & Arthur',    'loc' => 'Villa Balbianello • Sunset Cruise',   'tag' => 'Lake Como, Italy • Balbianello Terrace', 'img' => 'https://images.unsplash.com/photo-1511285560929-80b456fea0bc?auto=format&fit=crop&w=1000&q=80'],
    ['title' => 'Heirloom Details',  'loc' => 'Macro Curations • Bespoke Ribbons',   'tag' => 'Fine Art Atelier • Heirlooms', 'img' => 'https://images.unsplash.com/photo-1522673607200-164d1b6ce486?auto=format&fit=crop&w=900&q=80'],
    ['title' => 'Mei & Soren',       'loc' => 'Bamboo Grove • Morning Mist',         'tag' => 'Kyoto, Japan • Temple Sanctuary', 'img' => 'https://images.unsplash.com/photo-1520854221256-17451cc331bf?auto=format&fit=crop&w=900&q=80'],
    ['title' => 'Charlotte & James', 'loc' => 'Mayfair Ballroom • Black Tie',        'tag' => 'London, Mayfair • Vintage Rolls Royce', 'img' => 'https://images.unsplash.com/photo-1465495976277-4387d4b0b4c6?auto=format&fit=crop&w=900&q=80'],
    ['title' => 'Camille & Hugo',    'loc' => 'Château Banquet • Fairy Lights',      'tag' => 'Provence, France • Château Reception', 'img' => 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=1400&q=80'],
    ['title' => 'Fiona & Liam',      'loc' => 'Misty Highlands • Film Archive',      'tag' => 'Scottish Highlands • Glencoe Valley', 'img' => 'https://images.unsplash.com/photo-1507504031003-b417219a0fde?auto=format&fit=crop&w=900&q=80'],
];
$items = function_exists('demie_get_portfolio') ? demie_get_portfolio(7) : [];
$cards = [];
foreach ($layouts as $i => $layout) {
    if (isset($items[$i])) {
        $img = demie_portfolio_img($items[$i], 'large');
        $cards[] = [
            'title' => get_the_title($items[$i]),
            'loc'   => get_the_date('', $items[$i]),
            'tag'   => __('Demie Photography Archive', 'demie-v2'),
            'img'   => $img ?: $fallback[$i]['img'],
        ];
    } else {
        $cards[] = $fallback[$i];
    }
}
?>
<section class="py-20 md:py-32 px-6 md:px-14 bg-brand-bone border-t border-brand-stone/40" id="gallery">
  <div class="max-w-7xl mx-auto">
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-10 reveal-on-scroll">
      <div>
        <div class="flex items-center space-x-2 mb-3">
          <span class="w-5 h-[1px] bg-brand-gold"></span>
          <span class="text-xs uppercase tracking-widest2 text-brand-gold font-sans font-medium"><?php esc_html_e('Curated Archives', 'demie-v2'); ?></span>
        </div>
        <h2 class="font-serif text-3xl sm:text-5xl lg:text-6xl text-brand-charcoal font-normal tracking-tight"><?php esc_html_e('Selected Wedding Portfolios', 'demie-v2'); ?></h2>
      </div>
      <p class="text-brand-muted text-sm max-w-md mt-4 md:mt-0 font-light leading-relaxed">
        <?php esc_html_e('A glimpse into quiet destination vows, lakeside estates, and timeless evening celebrations documented across Malawi and beyond.', 'demie-v2'); ?>
      </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-8 lg:gap-10 items-start">
      <?php foreach ($cards as $i => $card) : ?>
      <div class="editorial-card group relative overflow-hidden bg-brand-stone <?php echo esc_attr($layouts[$i]['span']); ?> reveal-on-scroll cursor-pointer shadow-lg">
        <div class="<?php echo esc_attr($layouts[$i]['aspect']); ?> overflow-hidden bg-brand-charcoal">
          <img alt="<?php echo esc_attr($card['title']); ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105 ease-out" loading="lazy" src="<?php echo esc_url($card['img']); ?>">
        </div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 flex flex-col justify-end p-6 md:p-8 text-white pointer-events-none">
          <span class="text-[10px] tracking-widest2 uppercase text-brand-champagne mb-1 font-sans"><?php echo esc_html($card['tag']); ?></span>
          <h3 class="font-serif <?php echo esc_attr($layouts[$i]['title_size']); ?> font-light tracking-wide text-brand-cream"><?php echo esc_html($card['title']); ?></h3>
        </div>
        <div class="p-5 bg-brand-cream border-t border-brand-stone/60 flex justify-between items-baseline">
          <div>
            <h4 class="font-serif text-xl tracking-wide text-brand-charcoal group-hover:text-brand-gold transition-colors"><?php echo esc_html($card['title']); ?></h4>
            <span class="text-[10px] tracking-widest uppercase text-brand-muted font-sans"><?php echo esc_html($card['loc']); ?></span>
          </div>
          <span class="text-xs font-serif italic text-brand-gold">→</span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="mt-16 text-center reveal-on-scroll">
      <a class="inline-flex items-center space-x-3 px-9 py-4 border border-brand-charcoal text-brand-charcoal text-xs uppercase tracking-luxury font-medium hover:bg-brand-charcoal hover:text-white transition-all shadow-sm" href="<?php echo esc_url(demie_page_url('gallery')); ?>">
        <span><?php esc_html_e('View Complete Portfolio Gallery', 'demie-v2'); ?></span>
        <span>→</span>
      </a>
    </div>
  </div>
</section>
