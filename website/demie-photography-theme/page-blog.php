<?php
/**
 * Template Name: Blog
 * Source: blog-grid.html
 */

get_header();

get_template_part('template-parts/page-titlebar', null, [
    'title' => get_the_title() ?: __('Blog', 'demie-photography'),
    'bg'    => 'page-header-bg-6.jpg',
]);
?>

<!-- Blog Grid -->
<section class="wptb-blog-grid-one">
    <div class="container">
        <div class="wptb-heading">
            <div class="wptb-item--inner text-center">
                <h6 class="wptb-item--subtitle"><span>01//</span> <?php esc_html_e('Latest News', 'demie-photography'); ?></h6>
                <h1 class="wptb-item--title"><?php esc_html_e('Our Photography', 'demie-photography'); ?> <span><?php esc_html_e('Related Blog', 'demie-photography'); ?></span></h1>
            </div>
        </div>

        <div class="wptb-blog--inner">
            <div class="row">
                <?php if (have_posts()) : ?>
                    <?php
                    $demie_i = 0;
                    while (have_posts()) :
                        the_post();
                        $demie_i++;
                        ?>
                        <div class="col-lg-4 col-sm-6">
                            <div class="wptb-blog-grid1<?php echo 1 === $demie_i ? ' active highlight' : ''; ?> wow fadeInLeft">
                                <div class="wptb-item--inner">
                                    <div class="wptb-item--image">
                                        <a href="<?php the_permalink(); ?>" class="wptb-item-link">
                                            <?php if (has_post_thumbnail()) : ?>
                                                <?php the_post_thumbnail('medium_large'); ?>
                                            <?php else : ?>
                                                <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/blog/' . (($demie_i % 3) + 1) . '.jpg'); ?>" alt="<?php the_title_attribute(); ?>">
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                    <div class="wptb-item--holder">
                                        <div class="wptb-item--date"><?php echo esc_html(get_the_date()); ?></div>
                                        <h4 class="wptb-item--title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>

                                        <div class="wptb-item--meta">
                                            <div class="wptb-item--author"><?php esc_html_e('By', 'demie-photography'); ?> <a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>"><?php the_author(); ?></a></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else : ?>
                    <div class="col-12 text-center">
                        <p><?php esc_html_e('No posts yet. Check back soon!', 'demie-photography'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (have_posts()) : ?>
            <div class="wptb-pagination-wrap text-center">
                <?php
                the_posts_pagination([
                    'mid_size'  => 1,
                    'prev_text' => '<i class="bi bi-chevron-left"></i>',
                    'next_text' => '<i class="bi bi-chevron-right"></i>',
                ]);
                ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
