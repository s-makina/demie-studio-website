<?php
/**
 * Template Name: Portfolio
 *
 * Independent curated index of Galleries (one Gallery = one card), gated by
 * the plugin's "Show on portfolio" checkbox. Detail renders inline on this
 * same page via ?project=slug (masonry, load_more, per_page 24).
 *
 * Decision: docs/adr/0003-portal-leaves-wordpress-portfolio-stays-curated-index.md
 * Manual WP page assigned this template; no seeder involvement.
 */

get_header();

get_template_part('template-parts/page-titlebar', null, [
    'title' => get_the_title() ?: __('Portfolio', 'demie-photography'),
    'bg'    => 'page-header-bg-6.jpg',
]);

$demie_base = get_permalink();
$demie_slug = isset($_GET['project']) ? sanitize_title(wp_unslash($_GET['project'])) : '';
$demie_detail_id = 0;
if ('' !== $demie_slug && function_exists('demie_g_resolve_portfolio_gallery_id')) {
    $demie_candidate = demie_g_resolve_portfolio_gallery_id($demie_slug);
    if ($demie_candidate && function_exists('demie_g_can_show_on_portfolio') && demie_g_can_show_on_portfolio($demie_candidate)) {
        $demie_detail_id = $demie_candidate;
    } elseif ($demie_candidate) {
        // Exists but not flagged visible: fall back to the index (no crash, no leak).
        $demie_detail_id = 0;
        $demie_slug = '';
    }
}
?>

<section>
    <div class="container">
        <div class="wptb-project--inner">
            <div class="wptb-heading">
                <div class="wptb-item--inner text-center">
                    <h6 class="wptb-item--subtitle"><?php demie_heading_sub('_demie_h_sub', 'Our Portfolio'); ?></h6>
                    <h1 class="wptb-item--title"><?php demie_heading_h1('_demie_h_l1', '_demie_h_l2', '_demie_h_l3', __('Demie Photography captures', 'demie-photography'), __('All of Your', 'demie-photography'), __('beautiful memories', 'demie-photography')); ?></h1>
                </div>
            </div>

            <?php if ($demie_detail_id) :
                $demie_detail = get_post($demie_detail_id);
                ?>
                <div class="mb-4">
                    <a class="btn btn-two text-uppercase" href="<?php echo esc_url($demie_base); ?>">
                        <span class="btn-wrap">
                            <span class="text-first"><?php esc_html_e('Back to Portfolio', 'demie-photography'); ?></span>
                            <span class="text-second"> <i class="bi bi-arrow-up-right"></i> <i class="bi bi-arrow-up-right"></i> </span>
                        </span>
                    </a>
                </div>
                <h2 class="text-center mb-4"><?php echo esc_html(get_the_title($demie_detail)); ?></h2>
                <?php
                if (function_exists('demie_g_render_gallery')) {
                    echo demie_g_render_gallery([
                        'gallery_id' => $demie_detail_id,
                        'layout'     => 'masonry',
                        'per_page'   => 24,
                        'pagination' => 'load_more',
                    ]);
                }
                ?>
            <?php else :
                $demie_galleries = function_exists('demie_g_portfolio_galleries') ? demie_g_portfolio_galleries() : [];
                ?>
                <?php if ($demie_galleries) : ?>
                    <div class="has-radius effect-tilt">
                        <div class="grid grid-3 gutter-30 clearfix demie-g-grid">
                            <div class="grid-sizer"></div>
                            <div class="row">
                                <?php foreach ($demie_galleries as $demie_gallery) :
                                    $demie_gid   = (int) $demie_gallery->ID;
                                    $demie_cover = function_exists('demie_g_cover') ? demie_g_cover($demie_gid) : '';
                                    $demie_count = function_exists('demie_g_count') ? demie_g_count($demie_gid) : 0;
                                    $demie_desc  = get_post_meta($demie_gid, '_demie_g_desc', true);
                                    $demie_link  = add_query_arg('project', $demie_gallery->post_name, $demie_base);
                                    $demie_src   = $demie_cover ? $demie_cover : DEMIE_URI . '/assets/img/projects/4/1.jpg';
                                    ?>
                                    <div class="grid-item col-md-4">
                                        <div class="wptb-item--inner">
                                            <div class="wptb-item--image">
                                                <a href="<?php echo esc_url($demie_link); ?>">
                                                    <img src="<?php echo esc_url($demie_src); ?>" alt="<?php echo esc_attr(get_the_title($demie_gallery)); ?>">
                                                </a>
                                            </div>

                                            <div class="wptb-item--holder">
                                                <div class="wptb-item--meta">
                                                    <h4><a href="<?php echo esc_url($demie_link); ?>"><?php echo esc_html(get_the_title($demie_gallery)); ?></a></h4>
                                                    <p>
                                                        <?php
                                                        /* translators: %d: number of photos/videos in the gallery */
                                                        echo esc_html(sprintf(_n('%d item', '%d items', $demie_count, 'demie-photography'), $demie_count));
                                                        ?>
                                                    </p>
                                                    <?php if ('' !== (string) $demie_desc) : ?>
                                                        <p><?php echo esc_html($demie_desc); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="col-12">
                        <p class="text-center"><?php esc_html_e('Portfolio items are being prepared. Please check back soon.', 'demie-photography'); ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php get_footer(); ?>
