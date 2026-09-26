<?php
// Single post.
get_header();

get_template_part('template-parts/page-titlebar', null, [
    'title' => get_the_title(),
]);
?>

<section class="pd-top-90 pd-bottom-90">
    <div class="container">
        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
            <article <?php post_class(); ?>>
                <div class="wptb-item--date mr-bottom-20"><?php echo esc_html(get_the_date()); ?> &middot; <?php the_category(', '); ?></div>
                <h1 class="mr-bottom-25"><?php the_title(); ?></h1>

                <?php if (has_post_thumbnail()) : ?>
                    <div class="wptb-image-single mr-bottom-40">
                        <div class="wptb-item--image">
                            <?php the_post_thumbnail('large'); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="entry-content demie-page-content">
                    <?php
                    the_content();
                    wp_link_pages([
                        'before' => '<div class="page-links">' . esc_html__('Pages:', 'demie-photography'),
                        'after'  => '</div>',
                    ]);
                    ?>
                </div>

                <div class="wptb-item--button mr-top-40">
                    <a class="btn btn-two text-uppercase" href="<?php echo esc_url(demie_page_url('blog')); ?>">
                        <span class="btn-wrap">
                            <span class="text-first"><?php esc_html_e('Back to Blog', 'demie-photography'); ?></span>
                            <span class="text-second"> <i class="bi bi-arrow-up-right"></i> <i class="bi bi-arrow-up-right"></i> </span>
                        </span>
                    </a>
                </div>
            </article>

            <?php
            if (comments_open() || get_comments_number()) {
                comments_template();
            }
            ?>
        <?php endwhile; endif; ?>
    </div>
</section>

<?php get_footer(); ?>
