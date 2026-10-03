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
$demie_mpp = 30;
$demie_mpage = isset($_GET['gpage']) ? max(1, (int) $_GET['gpage']) : 1;
$demie_mpages = 1;
if ($demie_detail_id && function_exists('demie_g_get_media') && function_exists('demie_g_resolve_item')) {
    $demie_gtitle = get_the_title($demie_detail_id);
    foreach (demie_g_get_media($demie_detail_id) as $demie_entry) {
        $demie_r = demie_g_resolve_item($demie_entry);
        if (empty($demie_r['thumb'])) {
            continue;
        }
        $demie_detail_cards[] = [
            'title' => '' !== $demie_r['title'] ? $demie_r['title'] : $demie_gtitle,
            'loc'   => 'video' === $demie_r['kind'] ? __('Film', 'demie-v2') . ' • ' . $demie_gtitle : $demie_gtitle,
            'img'   => $demie_r['thumb'],
            'kind'  => $demie_r['kind'],
        ];
    }
    $demie_mpages = max(1, (int) ceil(count($demie_detail_cards) / $demie_mpp));
    $demie_mpage = min($demie_mpage, $demie_mpages);
    $demie_detail_cards = array_slice($demie_detail_cards, ($demie_mpage - 1) * $demie_mpp, $demie_mpp);
}

$demie_ipp = 9;
$demie_pg = isset($_GET['pg']) ? max(1, (int) $_GET['pg']) : 1;
$demie_ppages = 1;
$demie_galleries = (!$demie_detail_id && function_exists('demie_g_portfolio_galleries')) ? demie_g_portfolio_galleries() : [];
if ($demie_galleries) {
    $demie_ppages = max(1, (int) ceil(count($demie_galleries) / $demie_ipp));
    $demie_pg = min($demie_pg, $demie_ppages);
    $demie_galleries = array_slice($demie_galleries, ($demie_pg - 1) * $demie_ipp, $demie_ipp);
}
?>
<section class="relative overflow-hidden py-20 md:py-28 px-6 md:px-14 bg-brand-deep text-white">
  <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/texture-2.png'); ?>" alt="" aria-hidden="true" class="absolute bottom-0 left-0 w-full h-auto opacity-80 pointer-events-none select-none">
  <div class="max-w-7xl mx-auto demie-v2-prose relative z-10">
    <?php if ($demie_detail_id) : ?>
      <div class="mb-8">
        <a href="<?php echo esc_url($demie_base); ?>" class="text-xs uppercase tracking-widest2 text-white/60 hover:text-white transition-colors">&larr; <?php esc_html_e('Back to Portfolio', 'demie-v2'); ?></a>
      </div>
      <h2 class="font-serif text-3xl md:text-4xl font-light text-center text-brand-cream mb-10"><?php echo esc_html(get_the_title($demie_detail_id)); ?></h2>
      <?php if ($demie_detail_cards) : ?>
        <div class="columns-1 sm:columns-2 lg:columns-3 gap-8">
          <?php foreach ($demie_detail_cards as $card) : ?>
            <div class="group relative block overflow-hidden bg-brand-charcoal mb-8 break-inside-avoid shadow-lg">
              <img src="<?php echo esc_url($card['img']); ?>" alt="<?php echo esc_attr($card['title']); ?>" class="w-full h-auto block group-hover:scale-[1.03] transition-transform duration-700 ease-out" loading="lazy">
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ($demie_mpages > 1) : ?>
          <nav class="flex items-center justify-center flex-wrap gap-2 mt-12" aria-label="<?php esc_attr_e('Gallery pages', 'demie-v2'); ?>">
            <?php if ($demie_mpage > 1) : ?>
              <a href="<?php echo esc_url(add_query_arg(['project' => $demie_slug, 'gpage' => $demie_mpage - 1], $demie_base)); ?>" class="px-4 py-2 border border-white/20 text-xs uppercase tracking-widest2 text-white/70 hover:text-white hover:border-white/60 transition-colors">&larr; <?php esc_html_e('Prev', 'demie-v2'); ?></a>
            <?php endif; ?>
            <?php for ($demie_i = 1; $demie_i <= $demie_mpages; $demie_i++) : ?>
              <?php if ($demie_i === $demie_mpage) : ?>
                <span class="px-4 py-2 border border-white bg-white text-brand-charcoal text-xs uppercase tracking-widest2 font-medium"><?php echo esc_html($demie_i); ?></span>
              <?php else : ?>
                <a href="<?php echo esc_url(add_query_arg(['project' => $demie_slug, 'gpage' => $demie_i], $demie_base)); ?>" class="px-4 py-2 border border-white/20 text-xs uppercase tracking-widest2 text-white/70 hover:text-white hover:border-white/60 transition-colors"><?php echo esc_html($demie_i); ?></a>
              <?php endif; ?>
            <?php endfor; ?>
            <?php if ($demie_mpage < $demie_mpages) : ?>
              <a href="<?php echo esc_url(add_query_arg(['project' => $demie_slug, 'gpage' => $demie_mpage + 1], $demie_base)); ?>" class="px-4 py-2 border border-white/20 text-xs uppercase tracking-widest2 text-white/70 hover:text-white hover:border-white/60 transition-colors"><?php esc_html_e('Next', 'demie-v2'); ?> &rarr;</a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php else : ?>
        <p class="text-center text-white/60 font-light"><?php esc_html_e('Gallery items are being prepared. Please check back soon.', 'demie-v2'); ?></p>
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
              <div class="absolute inset-0 flex items-end justify-start text-left p-8 md:p-10 bg-[linear-gradient(180deg,rgba(0,0,0,0)_40%,rgba(180,83,9,0.95)_100%)] translate-y-full group-hover:translate-y-0 group-focus-within:translate-y-0 transition-transform duration-500 ease-out">
                <span class="absolute top-6 right-6 w-12 h-12 rounded-full bg-white text-[#B45309] flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-500" aria-hidden="true">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16" fill="currentColor"><path fill-rule="evenodd" d="M7.146 2.146a.5.5 0 0 1 .708 0l5 5a.5.5 0 0 1 0 .708l-5 5a.5.5 0 0 1-.708-.708L11.293 8 7.146 3.854a.5.5 0 0 1 0-.708z"/></svg>
                </span>
                <div class="translate-y-3 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:translate-y-0 group-focus-within:opacity-100 transition-all duration-500">
                  <h4 class="font-serif text-3xl font-normal tracking-wide text-white"><?php echo esc_html(get_the_title($demie_gallery)); ?></h4>
                  <p class="text-sm font-medium uppercase tracking-widest2 text-white mt-2" style="color:#fff;font-weight:500">
                    <?php
                    /* translators: %d: number of photos/videos in the gallery */
                    echo esc_html(sprintf(_n('%d item', '%d items', $demie_count, 'demie-v2'), $demie_count));
                    ?>
                  </p>
                  <?php if ('' !== (string) $demie_desc) : ?>
                    <p class="text-base font-medium text-white mt-2" style="color:#fff;font-weight:500"><?php echo esc_html($demie_desc); ?></p>
                  <?php endif; ?>
                </div>
              </div>
            </a>
        <?php endforeach; ?>
      </div>
      <?php if ($demie_ppages > 1) : ?>
        <nav class="flex items-center justify-center flex-wrap gap-2 mt-12" aria-label="<?php esc_attr_e('Portfolio pages', 'demie-v2'); ?>">
          <?php if ($demie_pg > 1) : ?>
            <a href="<?php echo esc_url(add_query_arg('pg', $demie_pg - 1, $demie_base)); ?>" class="px-4 py-2 border border-white/20 text-xs uppercase tracking-widest2 text-white/70 hover:text-white hover:border-white/60 transition-colors">&larr; <?php esc_html_e('Prev', 'demie-v2'); ?></a>
          <?php endif; ?>
          <?php for ($demie_i = 1; $demie_i <= $demie_ppages; $demie_i++) : ?>
            <?php if ($demie_i === $demie_pg) : ?>
              <span class="px-4 py-2 border border-white bg-white text-brand-charcoal text-xs uppercase tracking-widest2 font-medium"><?php echo esc_html($demie_i); ?></span>
            <?php else : ?>
              <a href="<?php echo esc_url(add_query_arg('pg', $demie_i, $demie_base)); ?>" class="px-4 py-2 border border-white/20 text-xs uppercase tracking-widest2 text-white/70 hover:text-white hover:border-white/60 transition-colors"><?php echo esc_html($demie_i); ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          <?php if ($demie_pg < $demie_ppages) : ?>
            <a href="<?php echo esc_url(add_query_arg('pg', $demie_pg + 1, $demie_base)); ?>" class="px-4 py-2 border border-white/20 text-xs uppercase tracking-widest2 text-white/70 hover:text-white hover:border-white/60 transition-colors"><?php esc_html_e('Next', 'demie-v2'); ?> &rarr;</a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php else : ?>
      <p class="text-center text-white/60 font-light"><?php esc_html_e('Portfolio items are being prepared. Please check back soon.', 'demie-v2'); ?></p>
    <?php endif; ?>
    <?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
  </div>
</section>
<?php get_template_part('template-parts/booking'); ?>
<?php get_footer(); ?>
