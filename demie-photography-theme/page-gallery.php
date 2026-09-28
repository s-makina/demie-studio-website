<?php
/**
 * Template Name: Gallery
 * Source: project-masonry-1.html
 */

get_header();

get_template_part('template-parts/page-titlebar', null, [
    'title' => get_the_title() ?: __('Gallery', 'demie-photography'),
    'bg'    => 'page-header-bg-6.jpg',
]);

$demie_gallery_items = demie_get_portfolio(12);
$demie_spans = ['col-md-4', 'col-md-4', 'col-md-4', 'col-md-8', 'col-md-8', 'col-md-4', 'col-md-4', 'col-md-4', 'col-md-8', 'col-md-4', 'col-md-4', 'col-md-4'];
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

            <div class="effect-gradient has-radius">
                <div class="grid gutter-10 clearfix">
                    <div class="grid-sizer"></div>
                    <div class="row">
                        <?php if ($demie_gallery_items) :
                            foreach ($demie_gallery_items as $demie_i => $demie_item) :
                                $demie_span  = isset($demie_spans[$demie_i]) ? $demie_spans[$demie_i] : 'col-md-4';
                                $demie_img   = demie_portfolio_img($demie_item, 'full');
                                $demie_src   = $demie_img ? $demie_img : DEMIE_URI . '/assets/img/projects/4/' . (($demie_i % 6) + 1) . '.jpg';
                                $demie_title = get_the_title($demie_item);
                                $demie_date  = get_the_date('', $demie_item);
                                ?>
                                <div class="grid-item <?php echo esc_attr($demie_span); ?>">
                                    <div class="wptb-item--inner">
                                        <div class="wptb-item--image">
                                            <img src="<?php echo esc_url($demie_src); ?>" alt="<?php echo esc_attr($demie_title); ?>">
                                            <a class="wptb-item--link" href="<?php echo esc_url($demie_src); ?>" data-fancybox="gallery" data-caption="<?php echo esc_attr($demie_title); ?>"><i class="bi bi-chevron-right"></i></a>
                                        </div>

                                        <div class="wptb-item--holder">
                                            <div class="wptb-item--meta">
                                                <h4><a href="<?php echo esc_url($demie_src); ?>" data-fancybox="gallery" data-caption="<?php echo esc_attr($demie_title); ?>"><?php echo esc_html($demie_title); ?></a></h4>
                                                <p><?php echo esc_html($demie_date); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach;
                        else : ?>
                            <div class="col-12">
                                <p class="text-center"><?php esc_html_e('Portfolio items are being prepared. Please check back soon.', 'demie-photography'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
