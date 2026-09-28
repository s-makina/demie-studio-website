<?php
// Generic page fallback: titlebar + page content + optional custom heading block.
get_header();

// Optional custom heading from the "Demie Page Headings" metabox. When the
// fields are left empty the titlebar title alone is shown (default behavior).
$demie_h_l1   = demie_current_meta('_demie_h_l1');
$demie_h_l2   = demie_current_meta('_demie_h_l2');
$demie_h_l3   = demie_current_meta('_demie_h_l3');
$demie_h_sub  = demie_current_meta('_demie_h_sub');
$demie_custom = ($demie_h_l1 !== '' || $demie_h_l2 !== '' || $demie_h_l3 !== '' || $demie_h_sub !== '');
?>

<section class="pd-top-90 pd-bottom-90">
    <div class="container">
        <?php if ($demie_custom) : ?>
            <div class="wptb-heading mr-bottom-60">
                <div class="wptb-item--inner">
                    <?php if ($demie_h_sub !== '') : ?>
                        <h6 class="wptb-item--subtitle"><?php demie_heading_sub('_demie_h_sub'); ?></h6>
                    <?php endif; ?>
                    <h1 class="wptb-item--title"><?php demie_heading_h1('_demie_h_l1', '_demie_h_l2', '_demie_h_l3'); ?></h1>
                </div>
            </div>
        <?php endif; ?>

        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
            <div class="entry-content demie-page-content">
                <?php the_content(); ?>
            </div>
        <?php endwhile; endif; ?>
    </div>
</section>

<?php get_footer(); ?>
