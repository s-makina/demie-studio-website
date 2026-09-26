<?php
// Generic page fallback: titlebar + page content.
get_header();

get_template_part('template-parts/page-titlebar', null, [
    'title' => get_the_title(),
]);
?>

<section class="pd-top-90 pd-bottom-90">
    <div class="container">
        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
            <div class="entry-content demie-page-content">
                <?php the_content(); ?>
            </div>
        <?php endwhile; endif; ?>
    </div>
</section>

<?php get_footer(); ?>
