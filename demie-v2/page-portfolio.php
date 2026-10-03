<?php
/**
 * Template Name: Portfolio
 * V2: curated index of Galleries (one Gallery = one card) gated by the
 * plugin's "Show on portfolio" checkbox. Detail renders inline via
 * ?project=slug in the v2 masonry-card skin (no Kimono markup — v2 does
 * not load Kimono CSS).
 *
 * Decision: docs/adr/0003-portal-leaves-wordpress-portfolio-stays-curated-index.md
 * Manual WP page assigned this template; no seeder involvement.
 */
get_header();
get_template_part('template-parts/page-titlebar', null, ['title' => get_the_title() ?: __('Portfolio', 'demie-v2'), 'subtitle' => __('Selected Works', 'demie-v2')]);

$demie_base = get_permalink();
$demie_slug = isset($_GET['project']) ? sanitize_title(wp_unslash($_GET['project'])) : '';
$demie_detail_id = 0;
if ('' !== $demie_slug && function_exists('demie_g_resolve_portfolio_gallery_id')) {
    $demie_candidate = demie_g_resolve_portfolio_gallery_id($demie_slug);
    if ($demie_candidate && function_exists('demie_g_can_show_on_portfolio') && demie_g_can_show_on_portfolio($demie_candidate)) {
        $demie_detail_id = $demie_candidate;
    }
}

$demie_detail_cards = [];
if ($demie_detail_id && function_exists('demie_g_get_media') && function_exists('demie_g_resolve_item')) {
    $demie_gtitle = get_the_title($demie_detail_id);
    foreach (demie_g_get_media($demie_detail_id) as $demie_entry) {
        $demie_r = demie_g_resolve_item($demie_entry);
        if (empty($demie_r['thumb'])) {
            continue;
        }
        // Cap the inline detail so a 2,000-media gallery cannot blow up the page.
        if (count($demie_detail_cards) >= 60) {
            break;
        }
        $demie_detail_cards[] = [
            'title' => '' !== $demie_r['title'] ? $demie_r['title'] : $demie_gtitle,
            'loc'   => 'video' === $demie_r['kind'] ? __('Film', 'demie-v2') . ' • ' . $demie_gtitle : $demie_gtitle,
            'img'   => $demie_r['thumb'],
            'kind'  => $demie_r['kind'],
        ];
    }
}

$demie_galleries = (!$demie_detail_id && function_exists('demie_g_portfolio_galleries')) ? demie_g_portfolio_galleries() : [];
?>
<section class="py-20 md:py-28 px-6 md:px-14 bg-brand-bone">
  <div class="max-w-7xl mx-auto demie-v2-prose">
    <?php if ($demie_detail_id) : ?>
      <div class="mb-8">
        <a href="<?php echo esc_url($demie_base); ?>" class="text-xs uppercase tracking-widest2 text-brand-muted hover:text-brand-charcoal transition-colors">&larr; <?php esc_html_e('Back to Portfolio', 'demie-v2'); ?></a>
      </div>
      <h2 class="font-serif text-3xl md:text-4xl font-light text-center mb-10"><?php echo esc_html(get_the_title($demie_detail_id)); ?></h2>
      <?php if ($demie_detail_cards) : ?>
        <div class="columns-1 sm:columns-2 lg:columns-3 gap-8">
          <?php foreach ($demie_detail_cards as $card) : ?>
            <div class="masonry-card group relative block overflow-hidden bg-brand-charcoal mb-8 break-inside-avoid shadow-lg">
              <img src="<?php echo esc_url($card['img']); ?>" alt="<?php echo esc_attr($card['title']); ?>" class="w-full h-auto" loading="lazy">
              <div class="absolute inset-0 flex items-center justify-center text-center p-8">
                <div class="masonry-meta">
                  <?php if ('video' === ($card['kind'] ?? 'photo')) : ?>
                  <span class="inline-block text-[10px] uppercase tracking-widest2 text-brand-charcoal bg-brand-champagne px-3 py-1 mb-3">▶ <?php esc_html_e('Film', 'demie-v2'); ?></span>
                  <?php endif; ?>
                  <h4 class="font-serif text-2xl font-light tracking-wide text-white"><?php echo esc_html($card['title']); ?></h4>
                  <p class="text-[11px] uppercase tracking-widest2 text-brand-champagne mt-2"><?php echo esc_html($card['loc']); ?></p>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else : ?>
        <p class="text-center text-brand-muted font-light"><?php esc_html_e('Gallery items are being prepared. Please check back soon.', 'demie-v2'); ?></p>
      <?php endif; ?>
    <?php elseif ($demie_galleries) : ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($demie_galleries as $demie_gallery) :
            $demie_gid   = (int) $demie_gallery->ID;
            $demie_cover = function_exists('demie_g_cover') ? demie_g_cover($demie_gid) : '';
            $demie_count = function_exists('demie_g_count') ? demie_g_count($demie_gid) : 0;
            $demie_desc  = get_post_meta($demie_gid, '_demie_g_desc', true);
            $demie_link  = add_query_arg('project', $demie_gallery->post_name, $demie_base);
            if (!$demie_cover) {
                continue;
            }
            ?>
            <a href="<?php echo esc_url($demie_link); ?>" class="group relative block overflow-hidden bg-brand-charcoal shadow-lg">
              <div class="aspect-[5/5.5] overflow-hidden">
                <img src="<?php echo esc_url($demie_cover); ?>" alt="<?php echo esc_attr(get_the_title($demie_gallery)); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out" loading="lazy">
              </div>
              <div class="absolute inset-0 flex items-center justify-center text-center p-8 bg-gradient-to-t from-black/70 via-black/20 to-transparent opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity duration-500">
                <div class="masonry-meta">
                  <h4 class="font-serif text-2xl font-light tracking-wide text-white"><?php echo esc_html(get_the_title($demie_gallery)); ?></h4>
                  <p class="text-[11px] uppercase tracking-widest2 text-brand-champagne mt-2">
                    <?php
                    /* translators: %d: number of photos/videos in the gallery */
                    echo esc_html(sprintf(_n('%d item', '%d items', $demie_count, 'demie-v2'), $demie_count));
                    ?>
                  </p>
                  <?php if ('' !== (string) $demie_desc) : ?>
                    <p class="text-sm text-white/80 mt-2"><?php echo esc_html($demie_desc); ?></p>
                  <?php endif; ?>
                </div>
              </div>
            </a>
        <?php endforeach; ?>
      </div>
    <?php else : ?>
      <p class="text-center text-brand-muted font-light"><?php esc_html_e('Portfolio items are being prepared. Please check back soon.', 'demie-v2'); ?></p>
    <?php endif; ?>
    <?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
  </div>
</section>
<?php get_template_part('template-parts/booking'); ?>
<?php get_footer(); ?>
