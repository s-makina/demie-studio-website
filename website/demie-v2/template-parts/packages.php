<?php
/**
 * Slotfy packages block.
 *
 * Renders the packages pulled live from the booking site, so the homepage and
 * Services page show the same catalogue as the booking widget. Prints nothing
 * when Slotfy is unreachable or has no visible packages.
 *
 * @param array $args {
 *     @type string $spacing Extra wrapper classes (default '').
 *     @type string $columns Extra grid column classes at lg (default 'lg:grid-cols-3').
 *     @type bool   $heading Whether to print the block heading (default true).
 * }
 */

$args = wp_parse_args(isset($args) && is_array($args) ? $args : [], [
    'spacing' => '',
    'columns' => 'lg:grid-cols-3',
    'heading' => true,
]);

$demie_catalog  = function_exists('demie_slotfy_package_catalog') ? demie_slotfy_package_catalog() : [];
$demie_packages = isset($demie_catalog['packages']) ? $demie_catalog['packages'] : [];

if (!$demie_packages) {
    return;
}
?>
<div id="packages" class="relative z-10 <?php echo esc_attr($args['spacing']); ?>">
  <?php if ($args['heading']) : ?>
    <div class="text-center max-w-2xl mx-auto mb-10">
      <span class="text-xs uppercase tracking-widest2 text-brand-gold font-sans font-medium mb-3 block"><?php esc_html_e('Bookable Online', 'demie-v2'); ?></span>
      <h2 class="font-serif text-3xl sm:text-5xl text-brand-cream font-normal tracking-tight mb-3"><?php esc_html_e('Packages', 'demie-v2'); ?></h2>
      <p class="text-white/60 text-sm md:text-base font-light"><?php esc_html_e('The same packages you will find on our booking page, pulled live from the studio calendar.', 'demie-v2'); ?></p>
    </div>
  <?php endif; ?>
  <div class="grid grid-cols-1 md:grid-cols-2 <?php echo esc_attr($args['columns']); ?> gap-6">
    <?php foreach ($demie_packages as $demie_pkg) : ?>
      <div class="flex flex-col p-8 bg-brand-charcoal border border-white/10 hover:border-brand-gold/60 transition-colors reveal-on-scroll">
        <span class="text-[10px] uppercase tracking-widest2 text-brand-champagne mb-3"><?php esc_html_e('Package', 'demie-v2'); ?></span>
        <div class="flex items-start justify-between gap-4 mb-2">
          <h3 class="font-serif text-2xl text-brand-cream"><?php echo esc_html($demie_pkg['name']); ?></h3>
          <span class="whitespace-nowrap text-sm text-brand-gold font-light"><?php echo esc_html(demie_slotfy_format_price($demie_pkg['price'], $demie_catalog)); ?></span>
        </div>
        <?php $demie_meta = demie_slotfy_package_meta($demie_pkg); if ('' !== $demie_meta) : ?>
          <p class="text-[11px] uppercase tracking-widest text-white/40 mb-4"><?php echo esc_html($demie_meta); ?></p>
        <?php endif; ?>
        <?php if ('' !== $demie_pkg['description']) : ?>
          <p class="text-white/60 text-sm font-light leading-relaxed mb-5"><?php echo esc_html($demie_pkg['description']); ?></p>
        <?php endif; ?>
        <?php if ($demie_pkg['inclusions']) : ?>
          <ul class="text-xs text-white/70 space-y-2 font-light mb-7">
            <?php foreach ($demie_pkg['inclusions'] as $demie_inc) : ?>
              <li class="flex items-start gap-2">
                <span class="text-brand-gold">•</span>
                <span><?php echo esc_html(sprintf('%d× %s', $demie_inc['quantity'], $demie_inc['name'])); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php
        $demie_book_url = '' !== $demie_pkg['id']
            ? add_query_arg('package', $demie_pkg['id'], demie_page_url('booking'))
            : demie_page_url('booking');
        ?>
        <a class="mt-auto inline-flex items-center justify-center px-6 py-3 border border-brand-champagne/60 text-brand-champagne hover:bg-brand-champagne hover:text-brand-charcoal text-[11px] uppercase tracking-luxury font-medium transition-colors" href="<?php echo esc_url($demie_book_url); ?>">
          <?php esc_html_e('Book this package', 'demie-v2'); ?>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</div>
