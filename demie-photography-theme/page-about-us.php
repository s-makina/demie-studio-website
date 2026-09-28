<?php
/**
 * Template Name: About Us
 * Source: about.html
 */

get_header();

get_template_part('template-parts/page-titlebar', null, [
    'title' => get_the_title() ?: __('About Us', 'demie-photography'),
    'bg'    => 'page-header-bg-4.jpg',
]);

$demie_faqs = demie_get_faqs();
?>

<!-- About Demie Photography -->
<section class="wptb-about-one bg-image-2" style="background-image: url('<?php echo esc_url(DEMIE_URI . '/assets/img/more/texture.png'); ?>');">
    <div class="container">
        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <div class="wptb-image-single mr-bottom-90 wow fadeInUp">
            <div class="wptb-item--inner">
                <div class="wptb-item--image">
                    <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/7.png'); ?>" alt="img">
                </div>
            </div>
        </div>

        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="wptb-heading">
                    <div class="wptb-item--inner">
                        <h6 class="wptb-item--subtitle"><?php demie_heading_sub('_demie_h_sub', '01 // About Us'); ?></h6>
                        <h1 class="wptb-item--title"><?php demie_heading_h1('_demie_h_l1', '_demie_h_l2', null, __('About', 'demie-photography'), __('Demie Photography', 'demie-photography')); ?></h1>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mt-4 mt-lg-0">
                <div class="entry-content demie-page-content">
                    <?php the_content(); ?>
                </div>
            </div>
        </div>
        <?php endwhile; endif; ?>
    </div>
</section>

<!-- FAQ -->
<section class="wptb-faq-one bg-image pb-0" style="background-image: url('<?php echo esc_url(DEMIE_URI . '/assets/img/background/bg-8.jpg'); ?>');">
    <div class="container">
        <div class="row">
            <div class="col-lg-5">
                <div class="wptb-heading">
                    <div class="wptb-item--inner">
                        <h6 class="wptb-item--subtitle"><span>02 //</span> <?php esc_html_e('F.A.Q', 'demie-photography'); ?></h6>
                        <h1 class="wptb-item--title"><?php esc_html_e('Frequently', 'demie-photography'); ?> <br> <span><?php esc_html_e('Ask Questions', 'demie-photography'); ?></span></h1>
                    </div>
                </div>
            </div>
            <div class="col-lg-7 mt-5 mt-lg-0">
                <div class="accordion" id="accordionFaq">
                    <?php foreach ($demie_faqs as $demie_i => $demie_faq) :
                        $demie_open = 0 === $demie_i;
                        ?>
                        <div class="accordion-item<?php echo $demie_open ? ' active' : ''; ?>">
                            <h2 class="accordion-header" id="demie-faq-heading-<?php echo esc_attr($demie_faq->ID); ?>">
                                <button class="accordion-button<?php echo $demie_open ? '' : ' collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#demie-faq-collapse-<?php echo esc_attr($demie_faq->ID); ?>" aria-expanded="<?php echo $demie_open ? 'true' : 'false'; ?>">
                                    <?php echo esc_html(get_the_title($demie_faq)); ?>
                                </button>
                            </h2>
                            <div id="demie-faq-collapse-<?php echo esc_attr($demie_faq->ID); ?>" class="accordion-collapse collapse<?php echo $demie_open ? ' show' : ''; ?>" aria-labelledby="demie-faq-heading-<?php echo esc_attr($demie_faq->ID); ?>" data-bs-parent="#accordionFaq">
                                <div class="accordion-body">
                                    <?php echo esc_html(wp_strip_all_tags($demie_faq->post_content)); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact strip -->
<section class="bg-dark-200 pd-bottom-80">
    <div class="container">
        <?php demie_render_contact_form(); ?>
    </div>
</section>

<?php get_footer(); ?>
