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
?>

<section>
    <div class="container">
        <div class="wptb-project--inner">
            <div class="wptb-heading">
                <div class="wptb-item--inner text-center">
                    <h6 class="wptb-item--subtitle"><span>01//</span> <?php esc_html_e('Our Portfolio', 'demie-photography'); ?></h6>
                    <h1 class="wptb-item--title"><?php esc_html_e('Demie Photography captures', 'demie-photography'); ?> <span><?php esc_html_e('All of Your', 'demie-photography'); ?></span> <br>
                        <?php esc_html_e('beautiful memories', 'demie-photography'); ?></h1>
                </div>
            </div>

            <div class="effect-gradient has-radius">
                <div class="grid gutter-10 clearfix">
                    <div class="grid-sizer"></div>
                    <div class="row">
                        <?php
                        // Pull the gallery from the "demie_gallery" image category; fall back
                        // to the template's placeholder grid when empty.
                        $demie_gallery = new WP_Query([
                            'post_type'           => 'post',
                            'category_name'       => 'gallery',
                            'posts_per_page'      => 12,
                            'ignore_sticky_posts' => true,
                            'no_found_rows'       => true,
                        ]);
                        if ($demie_gallery->have_posts()) :
                            $demie_spans = ['col-md-4', 'col-md-4', 'col-md-4', 'col-md-8', 'col-md-8', 'col-md-4', 'col-md-4', 'col-md-4', 'col-md-8', 'col-md-4', 'col-md-4', 'col-md-4'];
                            $demie_i = 0;
                            while ($demie_gallery->have_posts()) :
                                $demie_gallery->the_post();
                                $demie_span = isset($demie_spans[$demie_i]) ? $demie_spans[$demie_i] : 'col-md-4';
                                $demie_i++;
                                ?>
                                <div class="grid-item <?php echo esc_attr($demie_span); ?>">
                                    <div class="wptb-item--inner">
                                        <div class="wptb-item--image">
                                            <?php if (has_post_thumbnail()) : ?>
                                                <a href="<?php the_permalink(); ?>">
                                                    <?php the_post_thumbnail('large'); ?>
                                                </a>
                                            <?php else : ?>
                                                <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/projects/4/' . (($demie_i % 6) + 1) . '.jpg'); ?>" alt="<?php the_title_attribute(); ?>">
                                            <?php endif; ?>
                                            <a class="wptb-item--link" href="<?php the_permalink(); ?>"><i class="bi bi-chevron-right"></i></a>
                                        </div>

                                        <div class="wptb-item--holder">
                                            <div class="wptb-item--meta">
                                                <h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
                                                <p><?php echo esc_html(get_the_date()); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            endwhile;
                            wp_reset_postdata();
                        else :
                            // Placeholder grid (same layout as the static build).
                            $demie_placeholders = [
                                [1, 'col-md-4', 'Bright Boho Sunshine'],
                                [2, 'col-md-4', 'California Fall Collection 2023'],
                                [3, 'col-md-4', 'Brown girl next door'],
                                [4, 'col-md-8', 'Fashion next stage'],
                                [5, 'col-md-8', 'Jenifer in green'],
                                [6, 'col-md-4', 'Sunflower Boho girl'],
                            ];
                            foreach ($demie_placeholders as $demie_ph) :
                                ?>
                                <div class="grid-item <?php echo esc_attr($demie_ph[1]); ?>">
                                    <div class="wptb-item--inner">
                                        <div class="wptb-item--image">
                                            <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/projects/4/' . $demie_ph[0] . '.jpg'); ?>" alt="img">
                                            <a class="wptb-item--link" href="<?php echo esc_url(demie_page_url('gallery')); ?>"><i class="bi bi-chevron-right"></i></a>
                                        </div>

                                        <div class="wptb-item--holder">
                                            <div class="wptb-item--meta">
                                                <h4><a href="<?php echo esc_url(demie_page_url('gallery')); ?>"><?php echo esc_html($demie_ph[2]); ?></a></h4>
                                                <p><?php esc_html_e('By Demie Photography', 'demie-photography'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach;
                        endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
