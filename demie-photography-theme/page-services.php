<?php
/**
 * Template Name: Services
 * Source: services-1.html
 */

get_header();

get_template_part('template-parts/page-titlebar', null, [
    'title' => get_the_title() ?: __('Our Services', 'demie-photography'),
    'bg'    => 'page-header-bg-6.jpg',
]);

$demie_services_all = demie_get_services();
?>

<!-- Our Services -->
<section>
    <div class="container">
        <div class="wptb-heading">
            <div class="wptb-item--inner text-center">
                <h6 class="wptb-item--subtitle"><?php demie_heading_sub('_demie_h_sub', '01// Our Services'); ?></h6>
                <h1 class="wptb-item--title"><?php demie_heading_h1('_demie_h_l1', '_demie_h_l2', '_demie_h_l3', __('Demie Photography offers', 'demie-photography'), __('All of the', 'demie-photography'), __('services you need', 'demie-photography')); ?></h1>
            </div>
        </div>

        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
            <?php if (trim(get_the_content())) : ?>
                <div class="entry-content demie-page-content text-center mb-5">
                    <?php the_content(); ?>
                </div>
            <?php endif; ?>
        <?php endwhile; endif; ?>

        <div class="row">
            <?php foreach ($demie_services_all as $demie_i => $demie_service) :
                $demie_icon = get_the_post_thumbnail_url($demie_service, 'full');
                $demie_desc = trim(wp_strip_all_tags($demie_service->post_content));
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="wptb-icon-box6<?php echo 0 === $demie_i ? ' active highlight' : ''; ?> mb-md-0">
                        <div class="wptb-item--inner">
                            <div class="wptb-item--icon">
                                <img src="<?php echo esc_url($demie_icon ? $demie_icon : DEMIE_URI . '/assets/img/services/icon-' . ($demie_i + 1) . '.svg'); ?>" alt="img">
                            </div>
                            <div class="wptb-item--holder">
                                <h4 class="wptb-item--title"><?php echo esc_html(get_the_title($demie_service)); ?></h4>
                                <p class="wptb-item--description"><?php echo esc_html($demie_desc !== '' ? $demie_desc : demie_service_short($demie_service)); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="bg-dark-200 pd-bottom-80">
    <div class="container">
        <?php demie_render_contact_form(); ?>
    </div>
</section>

<?php get_footer(); ?>
